<?php
// Zaman Sınırını Artır (AI ve DB işlemleri için)
set_time_limit(300);
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER["DOCUMENT_ROOT"].'/config/db.php';
require_once $_SERVER["DOCUMENT_ROOT"].'/services/ai/GeminiService.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $project_id = $_POST['project_id'] ?? null;
        if (!$project_id) throw new Exception("Project ID missing.");

        // 1. MEVCUT ŞEMAYI ÇEK (Muhtemelen 1NF halini)
        $stmtTables = $db->prepare("SELECT id, table_name FROM project_tables WHERE project_id = :pid");
        $stmtTables->execute([':pid' => $project_id]);
        $tables = $stmtTables->fetchAll(PDO::FETCH_ASSOC);

        $currentSchema = [];
        foreach ($tables as $t) {
            $stmtCols = $db->prepare("SELECT name, data_type, is_primary_key, is_foreign_key FROM project_columns WHERE table_id = :tid");
            $stmtCols->execute([':tid' => $t['id']]);
            $cols = $stmtCols->fetchAll(PDO::FETCH_ASSOC);
            $currentSchema[] = ['table' => $t['table_name'], 'columns' => $cols];
        }

        if(empty($currentSchema)) throw new Exception("No tables found to normalize.");

        $schemaJson = json_encode($currentSchema);

        // 2. PROMPT (2NF Odaklı)
        $prompt = "
        ROLE: Database Normalization Expert.
        TASK: Convert the following Schema to Second Normal Form (2NF).
        
        INPUT SCHEMA: $schemaJson

        2NF RULES:
        1. Must be in 1NF.
        2. **Remove Partial Dependencies:** In tables with Composite Primary Keys (multi-column PK), move attributes that depend on only PART of the key to a new table.
        3. Tables with single-column PK are already 2NF.

        OUTPUT FORMAT (JSON):
        {
            \"schema\": [
                {
                    \"table_name\": \"...\",
                    \"description\": \"...\",
                    \"columns\": [
                        { \"name\": \"...\", \"data_type\": \"...\", \"is_primary_key\": true/false, \"is_foreign_key\": true/false, \"is_nullable\": true/false, \"is_unique\": true/false }
                    ]
                }
            ],
            \"changes\": [
                \"Explanation 1\", \"Explanation 2\"
            ]
        }
        ";

        // 3. AI ÇAĞRISI
        $aiService = new GeminiService($db);
        $jsonResult = $aiService->callApi($prompt, true);
        $data = json_decode($jsonResult, true);

        if (!isset($data['schema'])) throw new Exception("AI valid bir şema döndürmedi.");

        // 4. VERİTABANI GÜNCELLEME (Atomic Transaction)
        $db->beginTransaction();

        try {
            // A. FK Kontrolünü Kapat (Silme hatasını önle)
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");

            // B. Logları EKLE (Silmek yok, append var)
            $stmtLog = $db->prepare("INSERT INTO project_normalization_logs (project_id, stage, description) VALUES (?, '2NF', ?)");
            if (isset($data['changes'])) {
                foreach($data['changes'] as $change) {
                    $stmtLog->execute([$project_id, $change]);
                }
            }

            // C. Tabloları YENİLE (Mevcutları sil)
            $db->prepare("DELETE FROM project_tables WHERE project_id = ?")->execute([$project_id]);

            // D. Yeni Tabloları Ekle (normalization_level = '2NF')
            $stmtTable = $db->prepare("INSERT INTO project_tables (project_id, table_name, description, normalization_level) VALUES (?, ?, ?, '2NF')");
            $stmtCol = $db->prepare("INSERT INTO project_columns (table_id, name, data_type, is_primary_key, is_foreign_key, is_nullable, is_unique) VALUES (?, ?, ?, ?, ?, ?, ?)");

            foreach ($data['schema'] as $table) {
                $stmtTable->execute([
                    $project_id,
                    $table['table_name'],
                    $table['description'] ?? 'Normalized to 2NF'
                ]);
                $tableId = $db->lastInsertId();

                if (isset($table['columns'])) {
                    foreach ($table['columns'] as $col) {
                        $stmtCol->execute([
                            $tableId,
                            $col['name'],
                            $col['data_type'],
                            ($col['is_primary_key'] ?? false) ? 1 : 0,
                            ($col['is_foreign_key'] ?? false) ? 1 : 0,
                            ($col['is_nullable'] ?? true) ? 1 : 0,
                            ($col['is_unique'] ?? false) ? 1 : 0
                        ]);
                    }
                }
            }

            // E. FK Kontrolünü Aç
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");

            $db->commit();
            echo json_encode(['success' => true, 'message' => '2NF Applied Successfully.']);

        } catch (Exception $dbEx) {
            $db->rollBack();
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            throw new Exception("DB Error: " . $dbEx->getMessage());
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>