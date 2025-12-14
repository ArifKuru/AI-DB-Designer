<?php
// /services/ai/api/resolve_missing_rule.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER["DOCUMENT_ROOT"].'/config/db.php';
require_once $_SERVER["DOCUMENT_ROOT"].'/services/ai/GeminiService.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $gap_id = $_POST['gap_id'] ?? null;
        if (!$gap_id) throw new Exception("Gap ID missing.");

        // 1. Eksiklik Bilgisini Çek
        $stmt = $db->prepare("SELECT * FROM project_missing_rules WHERE id = :id");
        $stmt->execute([':id' => $gap_id]);
        $gap = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$gap) throw new Exception("Issue not found.");
        if ($gap['status'] === 'resolved') throw new Exception("This issue is already resolved.");

        $project_id = $gap['project_id'];

        // 2. Mevcut Tabloları Çek (AI yanlış tablo ismi uydurmasın diye)
        $stmtTables = $db->prepare("SELECT id, table_name FROM project_tables WHERE project_id = :pid");
        $stmtTables->execute([':pid' => $project_id]);
        $currentTables = $stmtTables->fetchAll(PDO::FETCH_KEY_PAIR); // [id => name, ...]
        $tableNames = implode(", ", $currentTables); // "users, orders, ..."

        // 3. Prompt (Tamirci Modu)
        // AI'dan sadece EKLENECEK veya GÜNCELLENECEK yapıyı istiyoruz.
        $prompt = "
        ROLE: Database Architect (Fixer Mode).
        TASK: Generate the schema definition to FIX a specific missing rule.

        ISSUE TO FIX:
        - Problem: {$gap['missing_rule']}
        - Suggested Solution: {$gap['solution']}

        CURRENT TABLES:
        $tableNames

        INSTRUCTION:
        Provide the JSON definition for the NEW table to be created OR the NEW columns to be added to an existing table.
        
        OUTPUT FORMAT (JSON):
        {
            \"target_table\": \"table_name_plural\",
            \"action\": \"CREATE_TABLE\" or \"ADD_COLUMNS\",
            \"description\": \"Description of this fix\",
            \"columns\": [
                {
                    \"name\": \"column_name\",
                    \"data_type\": \"VARCHAR(255) | INT...\",
                    \"is_primary_key\": true/false,
                    \"is_foreign_key\": true/false,
                    \"is_nullable\": true/false,
                    \"is_unique\": true/false
                }
            ]
        }
        ";

        // 4. AI Çağrısı
        $aiService = new GeminiService($db);
        $jsonResult = $aiService->callApi($prompt, true);
        $fixData = json_decode($jsonResult, true);

        if (!$fixData || !isset($fixData['target_table'])) {
            throw new Exception("AI could not generate a valid fix schema.");
        }

        // 5. Veritabanına Uygula (Patch)
        $db->beginTransaction();

        $tableName = $fixData['target_table'];
        $tableId = null;

        // Tablo ID'sini bul veya Yeni Tablo Oluştur
        // Mevcut tablolarda var mı diye bak (Array search)
        $existingTableId = array_search($tableName, $currentTables); // Değerden key'i bulur

        if ($existingTableId) {
            // Tablo zaten var, sadece sütun ekleyeceğiz
            $tableId = $existingTableId;
        } else {
            // Tablo yok, oluşturalım
            $stmtNewTable = $db->prepare("INSERT INTO project_tables (project_id, table_name, description, normalization_level) VALUES (?, ?, ?, '0NF')");
            $stmtNewTable->execute([$project_id, $tableName, $fixData['description'] ?? 'AI Generated Fix']);
            $tableId = $db->lastInsertId();
        }

        // Sütunları Ekle
        $stmtCol = $db->prepare("INSERT INTO project_columns (table_id, name, data_type, is_primary_key, is_foreign_key, is_nullable, is_unique) VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($fixData['columns'] as $col) {
            // Sütun zaten var mı kontrol et (Duplicate hatası vermesin)
            $checkCol = $db->prepare("SELECT id FROM project_columns WHERE table_id = ? AND name = ?");
            $checkCol->execute([$tableId, $col['name']]);

            if (!$checkCol->fetch()) {
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

        // 6. Eksikliği 'resolved' olarak işaretle
        $db->prepare("UPDATE project_missing_rules SET status = 'resolved' WHERE id = ?")->execute([$gap_id]);

        $db->commit();

        echo json_encode(['success' => true, 'message' => "Fixed successfully! Table '$tableName' updated."]);

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>