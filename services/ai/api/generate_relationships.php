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
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $project_id = $_POST['project_id'] ?? null;
        if (!$project_id) throw new Exception("Project ID missing.");

        // ---------------------------------------------------------
        // 1. PROJE KURALLARINI ÇEK (Rules - The Intent)
        // ---------------------------------------------------------
        $stmtRules = $db->prepare("SELECT rule_id, rule_statement, rule_type FROM project_rules WHERE project_id = :pid");
        $stmtRules->execute([':pid' => $project_id]);
        $rules = $stmtRules->fetchAll(PDO::FETCH_ASSOC);

        $rulesText = "";
        foreach ($rules as $r) {
            $rulesText .= "- Rule [{$r['rule_id']}]: {$r['rule_statement']} (Type: {$r['rule_type']})\n";
        }

        // ---------------------------------------------------------
        // 2. MEVCUT TABLO YAPISINI ÇEK (Schema - The Implementation)
        // ---------------------------------------------------------
        $stmtTables = $db->prepare("SELECT id, table_name FROM project_tables WHERE project_id = :pid");
        $stmtTables->execute([':pid' => $project_id]);
        $tables = $stmtTables->fetchAll(PDO::FETCH_ASSOC);

        // ID Eşleşmesi için Map
        $tableMap = [];
        $schemaDesc = "";

        foreach ($tables as $t) {
            $tableMap[$t['table_name']] = $t['id'];

            $stmtCols = $db->prepare("SELECT name, is_primary_key, is_foreign_key, is_unique FROM project_columns WHERE table_id = :tid");
            $stmtCols->execute([':tid' => $t['id']]);
            $cols = $stmtCols->fetchAll(PDO::FETCH_ASSOC);

            $colDesc = [];
            foreach ($cols as $c) {
                $attrs = [];
                if ($c['is_primary_key']) $attrs[] = "PK";
                if ($c['is_foreign_key']) $attrs[] = "FK";
                if ($c['is_unique']) $attrs[] = "UQ"; // Unique constraint 1:1 tespiti için kritiktir
                $colDesc[] = $c['name'] . (!empty($attrs) ? " (" . implode(",", $attrs) . ")" : "");
            }
            $schemaDesc .= "Table '{$t['table_name']}': " . implode(", ", $colDesc) . "\n";
        }

        // ---------------------------------------------------------
        // 3. AI PROMPT (GÜÇLENDİRİLDİ)
        // ---------------------------------------------------------
        $prompt = "
        ROLE: Database Architect & ER Diagram Specialist.
        TASK: Map the Business Rules to the Physical Schema to identify Relationships and accurately determine Cardinality (1:1, 1:N, M:N).

        SOURCE 1: BUSINESS RULES (Logic & Intent):
        $rulesText

        SOURCE 2: DATABASE SCHEMA (Physical Tables & Constraints):
        $schemaDesc

        INSTRUCTIONS:
        1. **Match Logic to Structure:** Connect tables based on Business Rules and Foreign Keys (FK).
        2. **Determine Cardinality (CRITICAL):**
           - **1:1 (One-to-One):** If a Foreign Key (FK) is also marked as **UQ (Unique)**, or the rule says \"One User has exactly One Profile\", output **1:1**.
           - **1:N (One-to-Many):** This is the most common. E.g., \"One Customer places Many Orders\". The FK is on the 'Many' side (Orders).
           - **M:N (Many-to-Many):** If the rule implies a direct Many-to-Many relationship (e.g., \"Students take Courses\") and there is NO intermediate junction table visible yet, output **M:N**.
           - **Junction Tables:** If you see a table containing two FKs pointing to parents (e.g., `student_courses`), define two **1:N** relationships from Parents to the Junction table.

        3. **Assign Labels:** Use the VERB from the Business Rule as the label.

        OUTPUT FORMAT (JSON Only) - EXAMPLE:
        {
            \"relationships\": [
                {
                    \"parent_table\": \"users\",
                    \"child_table\": \"profiles\",
                    \"cardinality\": \"1:1\", 
                    \"label\": \"has details\"
                },
                {
                    \"parent_table\": \"users\",
                    \"child_table\": \"orders\",
                    \"cardinality\": \"1:N\",
                    \"label\": \"places\"
                },
                {
                    \"parent_table\": \"products\",
                    \"child_table\": \"categories\",
                    \"cardinality\": \"M:N\",
                    \"label\": \"belongs to\"
                }
            ]
        }
        ";

        // 4. AI ÇAĞRISI
        $aiService = new GeminiService($db);
        $jsonResult = $aiService->callApi($prompt, true); // JSON Mode
        $data = json_decode($jsonResult, true);

        if (!isset($data['relationships']) || !is_array($data['relationships'])) {
            // Hata durumunda boş array dön, exception fırlatma ki process ölmesin
            $data['relationships'] = [];
        }

        // 5. VERİTABANINA KAYIT
        $db->beginTransaction();

        // Eskileri Temizle
        $db->prepare("DELETE FROM project_relationships WHERE project_id = ?")->execute([$project_id]);

        $stmtInsert = $db->prepare("INSERT INTO project_relationships (project_id, parent_table_id, child_table_id, cardinality, label) VALUES (?, ?, ?, ?, ?)");

        $count = 0;
        foreach ($data['relationships'] as $rel) {
            $parentName = strtolower($rel['parent_table']);
            $childName = strtolower($rel['child_table']);

            // Tablo isimlerini ID'ye çevir (Case-insensitive arama yapalım)
            $parentId = null;
            $childId = null;

            foreach ($tableMap as $name => $id) {
                if (strtolower($name) === $parentName) $parentId = $id;
                if (strtolower($name) === $childName) $childId = $id;
            }

            if ($parentId && $childId) {
                $stmtInsert->execute([
                    $project_id,
                    $parentId,
                    $childId,
                    $rel['cardinality'] ?? '1:1', // Fallback yine 1:N kalsın ama prompt artık doğru üretecek
                    $rel['label'] ?? 'relates to'
                ]);
                $count++;
            }
        }

        // STATÜ GÜNCELLEMESİ (diagram_generated)
        $db->prepare("UPDATE projects SET status = 'diagram_generated' WHERE id = ?")->execute([$project_id]);

        $db->commit();
        echo json_encode(['success' => true, 'message' => "Analyzed Rules & Schema. $count relationships mapped."]);

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>