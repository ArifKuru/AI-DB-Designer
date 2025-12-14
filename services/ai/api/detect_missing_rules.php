<?php
// /services/ai/api/detect_missing_rules.php
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

        // 1. Mevcut Verileri Topla
        $stmtRules = $db->prepare("SELECT rule_id, rule_statement FROM project_rules WHERE project_id = :pid");
        $stmtRules->execute([':pid' => $project_id]);
        $rules = $stmtRules->fetchAll(PDO::FETCH_ASSOC);

        $stmtTables = $db->prepare("
            SELECT t.table_name, c.name, c.data_type, c.is_primary_key, c.is_foreign_key 
            FROM project_tables t 
            JOIN project_columns c ON t.id = c.table_id 
            WHERE t.project_id = :pid
        ");
        $stmtTables->execute([':pid' => $project_id]);
        $schemaRows = $stmtTables->fetchAll(PDO::FETCH_ASSOC);

        $contextRules = "";
        foreach($rules as $r) $contextRules .= "- [{$r['rule_id']}] {$r['rule_statement']}\n";

        $contextSchema = "";
        foreach($schemaRows as $s) {
            $pk = $s['is_primary_key'] ? "(PK)" : "";
            $fk = $s['is_foreign_key'] ? "(FK)" : "";
            $contextSchema .= "Table: {$s['table_name']} -> Col: {$s['name']} $pk $fk\n";
        }

        // 2. Prompt
        $prompt = "
        ROLE: Database Auditor.
        TASK: Compare Business Rules vs. Current Draft Schema to find MISSING elements.
        RULES:
        $contextRules
        CURRENT SCHEMA:
        $contextSchema
        GOAL: Identify what is MISSING in the schema based on the rules.
        OUTPUT JSON: [{ \"missing_rule\": \"...\", \"related_br\": \"...\", \"solution\": \"...\" }]
        Return empty [] if schema is perfect.
        ";

        // 3. AI Çağrısı
        $aiService = new GeminiService($db);
        $jsonResult = $aiService->callApi($prompt, true);
        $suggestions = json_decode($jsonResult, true);

        if (!is_array($suggestions)) {
            if(isset($suggestions['missing_rules'])) $suggestions = $suggestions['missing_rules'];
            else $suggestions = [];
        }

        // 4. Veritabanına Kaydet
        $db->beginTransaction();
        $db->prepare("DELETE FROM project_missing_rules WHERE project_id = ? AND status = 'pending'")->execute([$project_id]);

        $insStmt = $db->prepare("INSERT INTO project_missing_rules (project_id, missing_rule, related_br, solution, status) VALUES (?, ?, ?, ?, 'pending')");

        foreach($suggestions as $s) {
            $insStmt->execute([
                $project_id,
                $s['missing_rule'] ?? 'Gap Detected',
                $s['related_br'] ?? 'General',
                $s['solution'] ?? 'Check schema'
            ]);
        }
        $db->prepare("UPDATE projects SET status = 'audit_passed' WHERE id = ? and status IN ('draft', 'rules_extracted','tables_created')")->execute([$project_id]);
        $db->commit();

        // DÜZELTME BURADA: issue_count eklendi
        echo json_encode([
            'success' => true,
            'issue_count' => count($suggestions),
            'message' => count($suggestions) . " gaps detected."
        ]);

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>