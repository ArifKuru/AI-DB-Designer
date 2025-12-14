<?php
// Hata Raporlama
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Gerekli Dosyalar
require_once $_SERVER["DOCUMENT_ROOT"].'/config/db.php';
require_once $_SERVER["DOCUMENT_ROOT"].'/services/ai/GeminiService.php';

// Session Başlat
if (session_status() == PHP_SESSION_NONE) { session_start(); }

$response = ['success' => false, 'message' => ''];

// 1. Yetki Kontrolü
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $project_id = $_POST['project_id'] ?? null;
        if (!$project_id) throw new Exception("Project ID is missing.");

        // 2. KURAL DETAYLARINI ÇEK
        // Sadece tanım değil, gerekçe ve uygulama tipini de alıyoruz.
        $stmt = $db->prepare("SELECT * FROM project_rules WHERE project_id = :pid ORDER BY id ASC");
        $stmt->execute([':pid' => $project_id]);
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rules)) {
            throw new Exception("No business rules found. Please extract rules first.");
        }

        // 3. AI İÇİN ZENGİN İÇERİK HAZIRLA
        $rulesText = "";
        $hasAuthRules = false;

        foreach ($rules as $r) {
            $rulesText .= "RULE [{$r['rule_id']}] (Type: {$r['rule_type']}):\n";
            $rulesText .= " - Statement: {$r['rule_statement']}\n";
            $rulesText .= " - Rationale: {$r['rule_rationale']}\n";
            $rulesText .= " - Impl. Type: {$r['implementation_type']}\n\n";

            if ($r['rule_type'] === 'Y') $hasAuthRules = true;
        }

        // 4. GELİŞMİŞ PROMPT (Döküman Standartlarına Uygun)
        $prompt = "
        ROLE: Senior Database Architect.
        TASK: Design a relational database schema (Draft / 0NF) based on the provided Business Rules.

        BUSINESS RULES:
        $rulesText

        CRITICAL DESIGN REQUIREMENTS (STRICTLY FOLLOW):
        1. **Entities & Relationships:** Create tables for all entities found in rules. Create junction tables for M:N relationships.
        2. **Naming Convention:** - Table names MUST be in **PLURAL** form and **English** (e.g., 'users', 'order_items', 'products').
           - Use snake_case for all names.
        3. **Attributes:** Define appropriate SQL data types (INT, VARCHAR, TEXT, BOOLEAN, DECIMAL, DATE, TIMESTAMP).
        4. **Constraints:** Identify PRIMARY KEY, FOREIGN KEY, UNIQUE, NOT NULL, and CHECK constraints.

        SPECIAL RULE HANDLING:
        - **If Rule Type is 'Y' (Authorization):** You MUST create security tables like `roles`, `permissions`, `user_roles`.
        - **If Rule Type is 'O' (Operational):** If the rule implies tracking or auditing, create a `logs` or `audit_trails` table.
        - **If Rule Type is 'C' (Constraint):** Apply this as a CHECK or UNIQUE constraint.

        OUTPUT FORMAT:
        Return ONLY a JSON Object. Minimize whitespace.
        Structure:
        {
            \"tables\": [
                {
                    \"table_name\": \"table_name_plural\",
                    \"description\": \"Purpose of the table\",
                    \"columns\": [
                        {
                            \"name\": \"column_name\",
                            \"data_type\": \"DATA_TYPE\",
                            \"is_primary_key\": true/false,
                            \"is_foreign_key\": true/false,
                            \"is_nullable\": true/false,
                            \"is_unique\": true/false,
                            \"check_constraint\": \"expression OR null\" (e.g. 'age > 18')
                        }
                    ]
                }
            ]
        }
        ";

        // 5. AI Servisini Çağır
        $aiService = new GeminiService($db);
        // JSON modu true, token limiti Service içinde zaten 8192 olarak ayarlandı.
        $jsonResult = $aiService->callApi($prompt, true);

        $schema = json_decode($jsonResult, true);

        // JSON Doğrulama
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Hata durumunda loglama yapılabilir veya ham veri kısaca gösterilir
            throw new Exception("AI did not return valid JSON. Error: " . json_last_error_msg());
        }

        // Root key kontrolü (Bazen AI direkt array dönebilir)
        if (!isset($schema['tables']) && isset($schema[0]['table_name'])) {
            $schema = ['tables' => $schema];
        }

        if (!isset($schema['tables']) || !is_array($schema['tables'])) {
            throw new Exception("Invalid JSON structure received from AI.");
        }

        // 6. VERİTABANINA KAYIT (Atomik İşlem)
        $db->beginTransaction();

        try {
            // A. Önceki tabloları temizle (Yeniden oluşturma senaryosu)
            // Tablolar silinince Foreign Key (CASCADE) sayesinde sütunlar da silinir.
            $delStmt = $db->prepare("DELETE FROM project_tables WHERE project_id = :pid");
            $delStmt->execute([':pid' => $project_id]);

            // B. Hazırlık Sorguları
            $stmtTable = $db->prepare("INSERT INTO project_tables (project_id, table_name, description, normalization_level) VALUES (:pid, :name, :desc, '0NF')");

            // Tüm constraint alanlarını içeren insert sorgusu
            $stmtCol = $db->prepare("INSERT INTO project_columns 
                (table_id, name, data_type, is_primary_key, is_foreign_key, is_nullable, is_unique, check_constraint) 
                VALUES (:tid, :name, :type, :pk, :fk, :null, :uniq, :check)");

            foreach ($schema['tables'] as $table) {
                // Tabloyu Ekle
                $stmtTable->execute([
                    ':pid' => $project_id,
                    ':name' => $table['table_name'],
                    ':desc' => $table['description'] ?? ''
                ]);
                $tableId = $db->lastInsertId();

                // Sütunları Ekle
                if (isset($table['columns']) && is_array($table['columns'])) {
                    foreach ($table['columns'] as $col) {
                        $stmtCol->execute([
                            ':tid' => $tableId,
                            ':name' => $col['name'],
                            ':type' => $col['data_type'],
                            ':pk' => ($col['is_primary_key'] ?? false) ? 1 : 0,
                            ':fk' => ($col['is_foreign_key'] ?? false) ? 1 : 0,
                            ':null' => ($col['is_nullable'] ?? true) ? 1 : 0, // Varsayılan Nullable: True
                            ':uniq' => ($col['is_unique'] ?? false) ? 1 : 0,
                            ':check' => $col['check_constraint'] ?? null
                        ]);
                    }
                }
            }

            // C. Proje Durumunu Güncelle
            // ... tablolar oluşturuldu ...

// STATÜ GÜNCELLEMESİ (tables_created)
// Eğer status 'audit_passed' veya daha ilerideyse geri alma, sadece ilerlet
            $db->prepare("UPDATE projects SET status = 'tables_created' WHERE id = ? AND status IN ('draft', 'rules_extracted')")->execute([$project_id]);

            $db->commit();

            $response['success'] = true;
            $response['message'] = count($schema['tables']) . " tables generated based on business rules.";

        } catch (Exception $dbEx) {
            $db->rollBack();
            throw new Exception("Database Error: " . $dbEx->getMessage());
        }

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
exit;