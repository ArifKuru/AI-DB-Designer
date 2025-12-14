<?php
// Zaman Sınırını Artır
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

        // 1. MEVCUT ŞEMAYI ÇEK
        $stmtTables = $db->prepare("SELECT id, table_name FROM project_tables WHERE project_id = :pid");
        $stmtTables->execute([':pid' => $project_id]);
        $tables = $stmtTables->fetchAll(PDO::FETCH_ASSOC);

        $currentSchema = [];
        foreach ($tables as $t) {
            $stmtCols = $db->prepare("SELECT name, data_type, is_primary_key FROM project_columns WHERE table_id = :tid");
            $stmtCols->execute([':tid' => $t['id']]);
            $cols = $stmtCols->fetchAll(PDO::FETCH_ASSOC);
            $currentSchema[] = ['table' => $t['table_name'], 'columns' => $cols];
        }
        $schemaJson = json_encode($currentSchema);

        // 2. PROMPT (DAHA SIKI FORMAT)
        $prompt = "
        ROLE: Database Normalization Expert.
        TASK: Apply 1NF rules to the input schema.
        INPUT SCHEMA: $schemaJson
        
        1NF RULES: 
        1. Atomic values (no comma-separated lists).
        2. No repeating groups.
        3. Create new tables for non-atomic data.

        OUTPUT FORMAT (STRICT JSON):
        {
            \"schema\": [
                {
                    \"table_name\": \"Explicit Name of the Table\",
                    \"description\": \"Short description\",
                    \"columns\": [
                        { \"name\": \"column_name\", \"data_type\": \"VARCHAR(255)\", \"is_primary_key\": true/false, \"is_foreign_key\": false, \"is_nullable\": true, \"is_unique\": false }
                    ]
                }
            ],
            \"changes\": [\"Log of change 1\", \"Log of change 2\"]
        }
        
        CRITICAL: Ensure every table object has a 'table_name' key. Do not use 'name', use 'table_name'.
        ";

        // 3. AI ÇAĞRISI
        $aiService = new GeminiService($db);
        $jsonResult = $aiService->callApi($prompt, true);
        $data = json_decode($jsonResult, true);

        if (!isset($data['schema']) || !is_array($data['schema'])) {
            throw new Exception("AI valid bir şema döndürmedi. Ham Yanıt: " . substr($jsonResult, 0, 100));
        }

        // 4. VERİTABANI GÜNCELLEME
        $db->beginTransaction();

        try {
            // A. FK Kontrolünü Kapat
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");

            // B. Logları EKLE
            $stmtLog = $db->prepare("INSERT INTO project_normalization_logs (project_id, stage, description) VALUES (?, '1NF', ?)");
            if (isset($data['changes'])) {
                foreach($data['changes'] as $change) {
                    $stmtLog->execute([$project_id, $change]);
                }
            }

            // C. Tabloları YENİLE
            $db->prepare("DELETE FROM project_tables WHERE project_id = ?")->execute([$project_id]);

            // D. Yeni Tabloları Ekle
            $stmtTable = $db->prepare("INSERT INTO project_tables (project_id, table_name, description, normalization_level) VALUES (?, ?, ?, '1NF')");
            $stmtCol = $db->prepare("INSERT INTO project_columns (table_id, name, data_type, is_primary_key, is_foreign_key, is_nullable, is_unique) VALUES (?, ?, ?, ?, ?, ?, ?)");

            foreach ($data['schema'] as $table) {
                // HATA ÇÖZÜMÜ: Fallback Kontrolü
                // Eğer AI 'table_name' yerine 'name' gönderdiyse onu kullan, ikisi de yoksa tabloyu atla.
                $tableName = null;
                if (!empty($table['table_name'])) {
                    $tableName = $table['table_name'];
                } elseif (!empty($table['name'])) {
                    $tableName = $table['name'];
                }

                if (!$tableName) {
                    // Tablo adı yoksa bu kaydı atla (Hata vermesini engelle)
                    continue;
                }

                $stmtTable->execute([
                    $project_id,
                    $tableName,
                    $table['description'] ?? 'Normalized to 1NF'
                ]);
                $tableId = $db->lastInsertId();

                if (isset($table['columns']) && is_array($table['columns'])) {
                    foreach ($table['columns'] as $col) {
                        // Kolon adı kontrolü
                        $colName = $col['name'] ?? null;
                        if (!$colName) continue;

                        $stmtCol->execute([
                            $tableId,
                            $colName,
                            $col['data_type'] ?? 'VARCHAR(255)',
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

            // F. STATÜ GÜNCELLEMESİ (normalized)
            $db->prepare("UPDATE projects SET status = 'normalized' WHERE id = ?")->execute([$project_id]);

            $db->commit();
            echo json_encode(['success' => true, 'message' => '1NF Successfully Applied.']);

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