<?php
require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/header.php';

// ID Kontrol
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>window.location.href = '/admin/projects/';</script>";
    exit;
}

$project_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    // Projeyi Çek
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $project_id, ':user_id' => $user_id]);
    $project = $stmt->fetch();

    if (!$project) {
        echo "<div class='p-12 text-center text-red-500 font-bold'>Project not found.</div>";
        require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/footer.php';
        exit;
    }

    $details = json_decode($project['description'], true);

    // Kuralları Çek
    $stmtRules = $db->prepare("SELECT * FROM project_rules WHERE project_id = :pid ORDER BY id ASC");
    $stmtRules->execute([':pid' => $project_id]);
    $rules = $stmtRules->fetchAll();
    $hasRules = count($rules) > 0;
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

// Renk Haritaları
$typeColors = [
    'S' => 'bg-blue-100 text-blue-700 border-blue-200',
    'O' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
    'T' => 'bg-amber-100 text-amber-700 border-amber-200',
    'Y' => 'bg-purple-100 text-purple-700 border-purple-200'
];
$typeLabels = ['S'=>'Structural', 'O'=>'Operational', 'T'=>'Threshold', 'Y'=>'Auth'];

$compLabels = ['E'=>'Entity', 'R'=>'Relationship', 'A'=>'Attribute', 'C'=>'Constraint'];
$compColors = [
    'E' => 'text-indigo-700 bg-indigo-50 border-indigo-200',
    'R' => 'text-rose-700 bg-rose-50 border-rose-200',
    'A' => 'text-slate-600 bg-slate-100 border-slate-200',
    'C' => 'text-amber-700 bg-amber-50 border-amber-200'
];
?>
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 8px; height: 8px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background-color: #94a3b8; }

        /* Sayfa taşmasını engellemek için body'ye müdahale */
        body { overflow: hidden; }
    </style>

    <div class="flex flex-col h-[calc(100vh-80px)] overflow-hidden pr-1">
        <div class="mb-8 border-b border-slate-200 pb-6 flex flex-col md:flex-row justify-between items-center gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <i data-lucide="brain-circuit" class="w-6 h-6 text-indigo-600"></i>
                    <h1 class="text-2xl font-bold text-slate-900">Stage 2: Business Rule Extraction</h1>
                    <?php if($hasRules): ?>
                        <span class="bg-green-100 text-green-700 text-xs px-2.5 py-1 rounded-full font-bold border border-green-200 flex items-center">
                    <i data-lucide="check" class="w-3.5 h-3.5 mr-1.5"></i> Completed
                </span>
                    <?php else: ?>
                        <span class="bg-amber-100 text-amber-700 text-xs px-2.5 py-1 rounded-full font-bold border border-amber-200 flex items-center">
                    <i data-lucide="clock" class="w-3.5 h-3.5 mr-1.5"></i> Pending
                </span>
                    <?php endif; ?>
                </div>
                <p class="text-slate-500 text-sm mt-1">AI-powered analysis to identify Entities, Relationships, and Constraints.</p>
            </div>

            <div class="flex gap-2">
                <a href="/admin/projects/detail?id=<?php echo $project_id; ?>" class="btn-sm bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-50 flex items-center shadow-sm transition">
                    <i data-lucide="layout-dashboard" class="w-4 h-4 mr-2"></i> Project Dashboard
                </a>

                <?php if($hasRules): ?>
                    <a href="/admin/projects/tables_design?id=<?php echo $project_id; ?>" class="btn-sm bg-slate-900 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 flex items-center shadow-lg transform hover:-translate-y-0.5 transition">
                        Proceed to Table Design <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
                    </a>
                <?php else: ?>
                    <button disabled class="btn-sm bg-slate-100 text-slate-400 px-5 py-2 rounded-lg text-sm font-medium cursor-not-allowed flex items-center">
                        Next Step <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex-1 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col relative">

            <div class="px-5 py-3 border-b border-slate-100 bg-slate-50 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-3">
                    <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider flex items-center">
                        <i data-lucide="brain-circuit" class="w-4 h-4 mr-2 text-indigo-600"></i> Business Rules
                    </h3>
                    <?php if (!empty($rules)): ?>
                        <span class="bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded text-[11px] font-bold border border-indigo-200">
                        <?php echo count($rules); ?> Rules
                    </span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($rules)): ?>
                    <button id="retryBtn" onclick="regenerateRules()" class="text-xs flex items-center text-indigo-600 hover:text-indigo-800 bg-white hover:bg-indigo-50 border border-indigo-200 px-3 py-1.5 rounded transition shadow-sm">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5 mr-1.5"></i> Regenerate Rules
                    </button>
                <?php endif; ?>
            </div>

            <div class="flex-1 overflow-auto custom-scrollbar bg-white relative">

                <?php if (empty($rules)): ?>
                    <div class="h-full flex flex-col items-center justify-center text-center p-8">
                        <div class="w-20 h-20 bg-indigo-50 rounded-full flex items-center justify-center mb-6 relative group cursor-pointer" onclick="startAiAnalysis()">
                            <div class="absolute inset-0 bg-indigo-200 rounded-full opacity-20 animate-ping"></div>
                            <i data-lucide="sparkles" class="w-10 h-10 text-indigo-600 transition-transform group-hover:scale-110"></i>
                        </div>
                        <h4 class="text-xl font-bold text-slate-900 mb-2">Ready to Analyze</h4>
                        <p class="text-slate-500 max-w-md mx-auto mb-8 leading-relaxed">
                            The AI engine is ready to extract detailed business rules, rationale, and implementation types from your project definition.
                        </p>
                        <button id="startAiBtn" onclick="startAiAnalysis()" class="px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold shadow-lg shadow-indigo-200 flex items-center mx-auto transition-all transform hover:-translate-y-1">
                            <i data-lucide="zap" class="w-5 h-5 mr-2 text-indigo-200"></i>
                            Start Extraction
                        </button>
                    </div>

                <?php else: ?>
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50 sticky top-0 z-10 text-xs font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200 shadow-sm">
                        <tr>
                            <th class="px-4 py-3 w-16 whitespace-nowrap">ID</th>
                            <th class="px-4 py-3">Rule Definition (Statement & Rationale)</th>
                            <th class="px-4 py-3 w-40 text-center whitespace-nowrap">Impl. Type</th>
                            <th class="px-4 py-3 w-16 text-center whitespace-nowrap">Comp.</th>
                            <th class="px-4 py-3 w-28 text-center whitespace-nowrap">Type</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                        <?php foreach($rules as $rule):
                            $rType = $rule['rule_type'] ?? 'S';
                            $rColor = $typeColors[$rType] ?? 'bg-slate-100 text-slate-600';

                            $implType = $rule['implementation_type'] ?? 'Constraint';
                            $implColor = 'text-slate-500 bg-slate-100 border-slate-200';
                            if(stripos($implType, 'Key') !== false) $implColor = 'text-emerald-700 bg-emerald-50 border-emerald-200';
                            if(stripos($implType, 'Trigger') !== false) $implColor = 'text-rose-700 bg-rose-50 border-rose-200';
                            if(stripos($implType, 'Access') !== false) $implColor = 'text-purple-700 bg-purple-50 border-purple-200';

                            $cCode = $rule['entity_component'] ?? 'E';
                            $cLabel = $compLabels[$cCode] ?? 'Unknown';
                            $cColor = $compColors[$cCode] ?? 'text-slate-500 bg-slate-50 border-slate-200';
                            ?>
                            <tr class="hover:bg-slate-50/80 transition-colors group">

                                <td class="px-4 py-3 align-top font-mono text-xs font-bold text-slate-500 pt-5 whitespace-nowrap">
                                    <?php echo htmlspecialchars($rule['rule_id']); ?>
                                </td>

                                <td class="px-4 py-3 align-top">
                                    <p class="text-slate-800 font-medium text-[14px] leading-relaxed">
                                        <?php echo htmlspecialchars($rule['rule_statement']); ?>
                                    </p>
                                    <?php if(!empty($rule['rule_rationale'])): ?>
                                        <div class="flex items-start mt-2 gap-2 bg-slate-50 p-2 rounded-lg border border-slate-100 max-w-4xl">
                                            <i data-lucide="info" class="w-3.5 h-3.5 text-indigo-400 mt-0.5 flex-shrink-0"></i>
                                            <p class="text-xs text-slate-600 leading-relaxed italic">
                                                <span class="font-bold not-italic text-slate-500 text-[10px] uppercase mr-1">Rationale:</span>
                                                <?php echo htmlspecialchars($rule['rule_rationale']); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="px-4 py-3 align-top text-center pt-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border whitespace-nowrap <?php echo $implColor; ?>">
                                    <?php echo htmlspecialchars($implType); ?>
                                </span>
                                </td>

                                <td class="px-4 py-3 align-top text-center pt-4">
                                <span title="<?php echo $cLabel; ?>" class="cursor-help inline-flex items-center justify-center w-7 h-7 rounded-full text-[10px] font-bold border shadow-sm <?php echo $cColor; ?>">
                                    <?php echo htmlspecialchars($cCode); ?>
                                </span>
                                </td>

                                <td class="px-4 py-3 align-top text-center pt-4">
                                <span title="<?php echo $typeLabels[$rType] ?? ''; ?>" class="cursor-help inline-block px-2 py-0.5 rounded text-[10px] font-bold border whitespace-nowrap <?php echo $rColor; ?>">
                                    <?php echo $typeLabels[$rType] ?? $rType; ?>
                                </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <?php if (!empty($rules)): ?>
                <div class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex justify-between items-center flex-shrink-0 z-20">
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i>
                        <span>Review rules carefully before proceeding.</span>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    </div>

    <script>
        function regenerateRules() {
            if(confirm('Delete all rules and regenerate?')) {
                const btn = document.getElementById('retryBtn');
                const originalHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = `<i data-lucide="loader-2" class="w-3.5 h-3.5 mr-1.5 animate-spin"></i> Processing...`;
                lucide.createIcons();
                callExtractApi(() => { btn.disabled = false; btn.innerHTML = originalHtml; lucide.createIcons(); });
            }
        }
        function startAiAnalysis() {
            const btn = document.getElementById('startAiBtn');
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btn.innerHTML = `<i data-lucide="loader-2" class="w-5 h-5 mr-2 animate-spin"></i> Analyzing...`;
            lucide.createIcons();
            callExtractApi(() => { btn.disabled = false; btn.classList.remove('opacity-75', 'cursor-not-allowed'); btn.innerHTML = originalContent; lucide.createIcons(); });
        }
        function callExtractApi(onErrorCallback) {
            const formData = new FormData();
            formData.append('project_id', <?php echo $project_id; ?>);
            fetch('/services/ai/api/extract_rules.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
                if (data.success) window.location.reload();
                else { alert('AI Error: ' + data.message); if(onErrorCallback) onErrorCallback(); }
            }).catch(error => { console.error('Error:', error); alert('Connection error.'); if(onErrorCallback) onErrorCallback(); });
        }
    </script>

<?php require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/footer.php'; ?>