<?php
// Hata Raporlama
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER["DOCUMENT_ROOT"].'/config/db.php';
require_once $_SERVER["DOCUMENT_ROOT"].'/services/ai/GeminiService.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }

$response = ['success' => false, 'message' => ''];

// Yetki Kontrolü
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $project_id = $_POST['project_id'] ?? null;
        if (!$project_id) throw new Exception("Project ID missing.");

        // Projeyi Çek
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $project_id, ':uid' => $_SESSION['user_id']]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project) throw new Exception("Project not found.");

        $projectRawData = $project['description'];

        // --- GÜNCELLENMİŞ PROMPT ---
        $prompt = "
        TASK: Analyze the project description and extract formal Business Rules.
        
        PROJECT DESCRIPTION:
        $projectRawData

        OUTPUT FORMAT:
        Return ONLY a JSON Array. Each object must have:
        1. rule_id: (e.g., BR-01)
        2. rule_type: One of ['S' (Structural), 'O' (Operational), 'T' (Threshold), 'Y' (Authorization)].
        3. rule_statement: A formal, complete sentence defining the rule.
        4. rule_rationale: The business reason 'WHY' this rule exists (short explanation).
        5. implementation_type: How to enforce this in SQL? Choose BEST of: 
           ['Key/Constraint', 'Trigger', 'Access Control', 'Stored Procedure'].
        6. entity_component: One of ['E', 'R', 'A', 'C'].

        EXAMPLE JSON OBJECT:
        {
            \"rule_id\": \"BR-01\",
            \"rule_type\": \"O\",
            \"rule_statement\": \"A student cannot register for more than 5 courses per semester.\",
            \"rule_rationale\": \"To prevent academic overload and scheduling conflicts.\",
            \"implementation_type\": \"Trigger\",
            \"entity_component\": \"C\"
        }

        CRITICAL: Return ONLY valid raw JSON. No markdown.
        ";

        // AI Çağrısı
        $aiService = new GeminiService($db);
        $jsonResult = $aiService->callApi($prompt, true);

        $rules = json_decode($jsonResult, true);
        if (json_last_error() !== JSON_ERROR_NONE) throw new Exception("Invalid JSON from AI.");
        if (!is_array($rules)) $rules = [$rules];

        // Veritabanı Kaydı
        $db->beginTransaction();

        // Eskileri Sil
        $delStmt = $db->prepare("DELETE FROM project_rules WHERE project_id = :pid");
        $delStmt->execute([':pid' => $project_id]);

        // Yenileri Ekle (Yeni sütun isimleriyle)
        $insStmt = $db->prepare("INSERT INTO project_rules 
            (project_id, rule_id, rule_type, rule_statement, rule_rationale, implementation_type, entity_component) 
            VALUES (:pid, :rid, :rtype, :stmt, :reason, :impl, :comp)");

        foreach ($rules as $rule) {
            $insStmt->execute([
                ':pid' => $project_id,
                ':rid' => $rule['rule_id'] ?? 'BR-XX',
                ':rtype' => $rule['rule_type'] ?? 'S',
                ':stmt' => $rule['rule_statement'] ?? ($rule['description'] ?? ''), // Fallback
                ':reason' => $rule['rule_rationale'] ?? '',
                ':impl' => $rule['implementation_type'] ?? 'Constraint',
                ':comp' => $rule['entity_component'] ?? 'E'
            ]);
        }

        // Durum Güncelle
        $db->prepare("UPDATE projects SET status = 'rules_extracted' WHERE id = ? AND status = 'draft'")->execute([$project_id]);
        $db->commit();
        $response['success'] = true;
        $response['message'] = count($rules) . " rules extracted with rationale and implementation details.";

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $response['message'] = $e->getMessage();
    }
}
echo json_encode($response);
exit;