<?php
require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/header.php';

if (!isset($_GET['id'])) exit("<script>window.location='/admin/projects/';</script>");
$project_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// PROJE VERİLERİNİ ÇEK
$stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
$stmt->execute([$project_id, $user_id]);
$project = $stmt->fetch();
$json = json_decode($project['description'], true);

if (!$project) {
    echo "<div class='p-12 text-center text-red-500 font-bold'>Project not found.</div>";
    require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/footer.php';
    exit;
}

// -------------------------------------------------------------------------
// DURUM KONTROLÜ
// -------------------------------------------------------------------------
$hasRules = $db->query("SELECT count(*) FROM project_rules WHERE project_id = $project_id")->fetchColumn() > 0;
$hasTables = $db->query("SELECT count(*) FROM project_tables WHERE project_id = $project_id")->fetchColumn() > 0;

// Bekleyen ve Toplam Hatalar
$pendingIssues = (int)$db->query("SELECT count(*) FROM project_missing_rules WHERE project_id = $project_id AND status = 'pending'")->fetchColumn();
$totalIssues = (int)$db->query("SELECT count(*) FROM project_missing_rules WHERE project_id = $project_id")->fetchColumn();

// Audit durumu: Tablolar var VE (Hiç hata yok VEYA hata vardı ama hepsi çözüldü)
$auditCompleted = ($hasTables && $pendingIssues === 0 && $totalIssues > 0);

$normLogs = $db->query("SELECT stage FROM project_normalization_logs WHERE project_id = $project_id GROUP BY stage")->fetchAll(PDO::FETCH_COLUMN);
$isNormalized = in_array('3NF', $normLogs);
$hasRels = $db->query("SELECT count(*) FROM project_relationships WHERE project_id = $project_id")->fetchColumn() > 0;
$hasSQL = $db->query("SELECT count(*) FROM project_exports WHERE project_id = $project_id")->fetchColumn() > 0;

// İLERLEME HESAPLA
$progress = 10;
if($hasRules) $progress += 15;
if($hasTables) $progress += 15;
if($auditCompleted) $progress += 15;
if($isNormalized) $progress += 15;
if($hasRels) $progress += 15;
if($hasSQL) $progress += 15;
if($progress > 100) $progress = 100;

// STATUS LABEL
$statusLabel = 'Draft';
$statusClass = 'bg-slate-100 text-slate-600 border-slate-200';
if ($hasSQL) { $statusLabel = 'Completed'; $statusClass = 'bg-green-100 text-green-700 border-green-200'; }
elseif ($hasRels) { $statusLabel = 'Diagram Ready'; $statusClass = 'bg-indigo-100 text-indigo-700 border-indigo-200'; }
elseif ($isNormalized) { $statusLabel = 'Normalized'; $statusClass = 'bg-purple-100 text-purple-700 border-purple-200'; }
elseif ($hasTables) { $statusLabel = 'Schema Created'; $statusClass = 'bg-blue-100 text-blue-700 border-blue-200'; }
elseif ($hasRules) { $statusLabel = 'Rules Extracted'; $statusClass = 'bg-amber-100 text-amber-700 border-amber-200'; }

// STAGES
// Eğer bekleyen hata varsa o aşamada kal, yoksa completed say
$auditStatus = ($pendingIssues > 0) ? 'current' : (($totalIssues > 0) ? 'completed' : ($hasTables ? 'current' : 'locked'));

$stages = [
    ['id'=>1, 'title'=>'Project Definition', 'desc'=>'Initial scope and requirements.', 'icon'=>'file-text', 'link'=>"edit.php?id=$project_id", 'status'=>'completed', 'btn'=>'Edit Definition'],
    ['id'=>2, 'title'=>'Business Rules', 'desc'=>'Extract Entities & Logic.', 'icon'=>'brain-circuit', 'link'=>"business_rules.php?id=$project_id", 'status'=>$hasRules?'completed':'current', 'btn'=>$hasRules?'View Rules':'Extract Rules'],
    ['id'=>3, 'title'=>'Table Definition (ER Model)', 'desc'=>'Generate DB Schema.', 'icon'=>'table', 'link'=>"tables_design.php?id=$project_id", 'status'=>$hasTables?'completed':($hasRules?'current':'locked'), 'btn'=>$hasTables?'View Schema':'Generate Tables'],
    ['id'=>4, 'title'=>'Missing Rule Detection', 'desc'=>'Audit Schema vs Rules.', 'icon'=>'microscope', 'link'=>"missing_rules.php?id=$project_id", 'status'=>$auditStatus, 'btn'=>'Run Audit'],
    ['id'=>5, 'title'=>'Normalization (3NF)', 'desc'=>'Optimize Structure.', 'icon'=>'layers', 'link'=>"normalize_1nf.php?id=$project_id", 'status'=>$isNormalized?'completed':($auditStatus=='completed'?'current':'locked'), 'btn'=>'Normalize'],
    ['id'=>6, 'title'=>"Crow's Foot Diagram", 'desc'=>'Visualize Relationships.', 'icon'=>'network', 'link'=>"er_diagram.php?id=$project_id", 'status'=>$hasRels?'completed':($isNormalized?'current':'locked'), 'btn'=>'View Diagram'],
    ['id'=>7, 'title'=>'SQL Code Generation', 'desc'=>'Production Ready Code.', 'icon'=>'database', 'link'=>"export_sql.php?id=$project_id", 'status'=>$hasSQL?'completed':($hasRels?'current':'locked'), 'btn'=>'Export SQL']
];
?>

    <style>
        /* Önceki CSS stilleri aynen korundu */
        .stage-card { transition: all 0.3s ease; height: 100%; display: flex; flex-direction: column; }
        .stage-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px -3px rgba(0,0,0,0.1); }
        .stage-locked { opacity: 0.5; pointer-events: none; filter: grayscale(100%); background: #f8fafc; }
        .progress-bar-striped { background-image: linear-gradient(45deg,rgba(255,255,255,.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,.15) 50%,rgba(255,255,255,.15) 75%,transparent 75%,transparent); background-size: 1rem 1rem; animation: progress-bar-stripes 1s linear infinite; }
        @keyframes progress-bar-stripes { 0% { background-position: 1rem 0; } 100% { background-position: 0 0; } }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #1e293b; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #475569; border-radius: 10px; }
        .wizard-log { font-family: 'Fira Code', monospace; font-size: 13px; line-height: 1.7; }
        .log-item { margin-bottom: 6px; display: flex; align-items: start; gap: 10px; padding: 4px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .log-text { color: #cbd5e1; }
        .log-success { color: #34d399; }
        .log-info { color: #818cf8; }
        .log-warn { color: #fbbf24; }
        .log-error { color: #f87171; }
        .log-process { color: #cbd5e1; }
    </style>

    <div class="mb-10">
        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-6 mb-8">
            <div class="flex-1">
                <div class="flex items-start gap-4">
                    <div class="w-14 h-14 bg-indigo-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-indigo-200 flex-shrink-0">
                        <i data-lucide="box" class="w-7 h-7"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h1 class="text-3xl font-bold text-slate-900 leading-tight"><?php echo htmlspecialchars($project['name']); ?></h1>
                            <span class="px-3 py-1 rounded-full text-xs font-bold border uppercase tracking-wide <?php echo $statusClass; ?>">
                            <?php echo $statusLabel; ?>
                        </span>
                        </div>
                        <div class="flex items-center gap-4 text-sm text-slate-500">
                            <span class="flex items-center"><i data-lucide="folder" class="w-4 h-4 mr-1.5 text-slate-400"></i><?php echo htmlspecialchars($json['domain'] ?? 'Database Project'); ?></span>
                            <span class="flex items-center"><i data-lucide="calendar" class="w-4 h-4 mr-1.5 text-slate-400"></i><?php echo date('d M Y, H:i', strtotime($project['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button onclick="openModal()" class="btn-sm bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 px-4 py-2 rounded-lg text-sm font-medium transition flex items-center shadow-sm">
                    <i data-lucide="info" class="w-4 h-4 mr-2"></i> Info
                </button>
                <a href="/admin/projects/edit.php?id=<?php echo $project_id; ?>" class="btn-sm bg-white border border-slate-300 text-slate-600 hover:bg-slate-50 px-4 py-2 rounded-lg text-sm font-medium transition flex items-center shadow-sm">
                    <i data-lucide="edit-3" class="w-4 h-4 mr-2"></i> Edit
                </a>
                <?php if(!$hasSQL): ?>
                    <button onclick="startAutoPilot()" class="btn-sm bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-md transition flex items-center animate-pulse">
                        <i data-lucide="wand-2" class="w-4 h-4 mr-2"></i> Magic Auto-Complete
                    </button>
                <?php endif; ?>
                <button onclick="deleteProject(<?php echo $project_id; ?>)" class="btn-sm bg-red-50 border border-red-200 text-red-700 hover:bg-red-100 px-4 py-2 rounded-lg text-sm font-medium transition flex items-center shadow-sm">
                    <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i> Delete
                </button>
                <a href="/admin/projects/" class="btn-sm bg-slate-800 text-white hover:bg-slate-900 px-5 py-2 rounded-lg text-sm font-medium shadow-md transition flex items-center">Back to List</a>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden">
            <div class="flex justify-between items-center mb-3 relative z-10">
                <div>
                <span class="text-sm font-bold text-slate-700 block flex items-center gap-2">
                    <i data-lucide="activity" class="w-4 h-4 text-indigo-500"></i> Development Progress
                </span>
                </div>
                <span class="text-3xl font-black text-indigo-600 tracking-tight"><?php echo $progress; ?>%</span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-4 overflow-hidden shadow-inner relative z-10">
                <div class="bg-indigo-600 h-4 rounded-full transition-all duration-1000 ease-out flex items-center justify-end pr-2 <?php echo $progress < 100 ? 'progress-bar-striped' : ''; ?>" style="width: <?php echo $progress; ?>%"></div>
            </div>
            <div class="absolute right-0 top-0 h-full w-32 bg-gradient-to-l from-indigo-50 to-transparent pointer-events-none"></div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 pb-24">
        <?php foreach($stages as $stage):
            $isLocked = $stage['status'] === 'locked';
            $isCompleted = $stage['status'] === 'completed';
            $isCurrent = $stage['status'] === 'current';

            $borderColor = $isCurrent ? 'border-indigo-500 ring-4 ring-indigo-50/50' : ($isCompleted ? 'border-green-200' : 'border-slate-200');
            $iconBg = $isCompleted ? 'bg-green-50 text-green-600' : ($isCurrent ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-200' : 'bg-slate-100 text-slate-400');
            $statusIcon = $isCompleted ? 'check-circle-2' : ($isCurrent ? 'loader-2' : 'lock');
            $statusColor = $isCompleted ? 'text-green-500' : ($isCurrent ? 'text-indigo-500 animate-spin' : 'text-slate-300');
            $cardOpacity = $isLocked ? 'opacity-60 grayscale' : 'opacity-100';
            ?>
            <div class="relative bg-white rounded-2xl border <?php echo $borderColor; ?> p-6 stage-card <?php echo $cardOpacity; ?>">
                <div class="absolute -top-3 -left-3 w-8 h-8 bg-white border border-slate-200 rounded-full flex items-center justify-center text-xs font-bold text-slate-400 shadow-sm z-10">0<?php echo $stage['id']; ?></div>
                <div class="absolute top-5 right-5 <?php echo $statusColor; ?>"><i data-lucide="<?php echo $statusIcon; ?>" class="w-5 h-5"></i></div>
                <div class="w-14 h-14 rounded-2xl <?php echo $iconBg; ?> flex items-center justify-center mb-5 transition-colors"><i data-lucide="<?php echo $stage['icon']; ?>" class="w-7 h-7"></i></div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-slate-900 mb-2 leading-tight"><?php echo $stage['title']; ?></h3>
                    <p class="text-sm text-slate-500 leading-relaxed mb-6"><?php echo $stage['desc']; ?></p>
                </div>
                <div class="mt-auto">
                    <a href="<?php echo $stage['link']; ?>" class="w-full flex items-center justify-center py-3 rounded-xl text-sm font-bold transition-all transform active:scale-95 <?php echo $isCurrent ? 'bg-indigo-600 text-white hover:bg-indigo-700 shadow-md hover:shadow-lg' : ($isCompleted ? 'bg-white border-2 border-slate-200 text-slate-600 hover:border-indigo-200 hover:text-indigo-600' : 'bg-slate-50 text-slate-400 border border-slate-200 cursor-not-allowed pointer-events-none'); ?>">
                        <?php if($isCompleted): ?><i data-lucide="check" class="w-4 h-4 mr-2"></i> Review Step<?php elseif($isCurrent): ?>Continue <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i><?php else: ?>Locked<?php endif; ?>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="projectInfoModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal()"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl border border-slate-200">
                    <div class="bg-slate-50 px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-800 flex items-center"><i data-lucide="file-text" class="w-5 h-5 mr-2 text-indigo-600"></i> Project Definition</h3>
                        <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition"><i data-lucide="x" class="w-5 h-5"></i></button>
                    </div>
                    <div class="px-6 py-6 max-h-[70vh] overflow-y-auto custom-scrollbar space-y-5">
                        <?php
                        function renderField($label, $value) {
                            $val = $value ? nl2br(htmlspecialchars($value)) : '<span class="text-slate-400 italic">Not defined</span>';
                            echo "<div><span class='text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1'>$label</span><div class='text-sm text-slate-700 bg-slate-50/50 p-3 rounded border border-slate-100 leading-relaxed'>$val</div></div>";
                        }
                        renderField("Domain", $json['domain'] ?? '');
                        renderField("Primary Entity", $json['primary_entity'] ?? '');
                        renderField("Constraints & Rules", $json['constraints'] ?? '');
                        renderField("Advanced Features", $json['advanced_features'] ?? '');
                        renderField("Security Requirements", $json['security_access'] ?? '');
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="wizardModal" class="fixed inset-0 bg-slate-950/90 backdrop-blur-md z-50 hidden flex items-center justify-center">
        <div class="bg-slate-900 border border-slate-700 rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[80vh]">
            <div class="bg-slate-800 px-6 py-4 flex justify-between items-center border-b border-slate-700 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-500/20 rounded-lg ring-1 ring-indigo-500/30"><i data-lucide="bot" class="w-6 h-6 text-indigo-400"></i></div>
                    <div>
                        <h3 class="text-lg font-bold text-white">AI Auto-Pilot</h3>
                        <p class="text-slate-400 text-xs">Completing pending stages...</p>
                    </div>
                </div>
                <button onclick="stopWizard()" class="text-slate-400 hover:text-white transition bg-slate-800 hover:bg-slate-700 p-1 rounded"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <div class="flex-1 bg-slate-950 p-6 overflow-y-auto custom-scrollbar" id="wizardContent">
                <div class="wizard-log" id="logContainer">
                    <div class="log-item text-slate-500 border-none"><i data-lucide="terminal" class="w-4 h-4 mt-0.5"></i> Initializing Auto-Pilot sequence...</div>
                </div>
            </div>

            <div class="bg-slate-800 px-6 py-4 border-t border-slate-700">
                <div class="flex justify-between text-xs text-slate-300 mb-2 font-medium">
                    <span id="wizardStepText">Waiting to start...</span>
                    <span id="wizardPercent">0%</span>
                </div>
                <div class="w-full bg-slate-700 rounded-full h-2 overflow-hidden shadow-inner">
                    <div id="wizardBar" class="bg-indigo-500 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- MEVCUT JS ---
        function openModal() { document.getElementById('projectInfoModal').classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
        function closeModal() { document.getElementById('projectInfoModal').classList.add('hidden'); document.body.style.overflow = 'auto'; }
        document.addEventListener('keydown', function(event) { if (event.key === "Escape") closeModal(); });

        function deleteProject(id) {
            if(confirm('Are you sure you want to delete this project?')) {
                const formData = new FormData(); formData.append('id', id);
                fetch('/admin/projects/api/delete.php', { method: 'POST', body: formData })
                    .then(res => res.json()).then(data => { if(data.success) window.location.href = '/admin/projects/'; else alert(data.message); });
            }
        }

        // --- YENİ SİHİRBAZ JS ---
        const state = {
            hasRules: <?php echo $hasRules ? 'true' : 'false'; ?>,
            hasTables: <?php echo $hasTables ? 'true' : 'false'; ?>,
            hasAudit: <?php echo $auditCompleted ? 'true' : 'false'; ?>,
            pendingIssuesCount: <?php echo $pendingIssues; ?>, // BURASI EKLENDİ
            isNormalized: <?php echo $isNormalized ? 'true' : 'false'; ?>,
            hasRels: <?php echo $hasRels ? 'true' : 'false'; ?>,
            hasSQL: <?php echo $hasSQL ? 'true' : 'false'; ?>
        };

        const projectId = <?php echo $project_id; ?>;
        let wizardStopped = false;

        function addLog(message, type = 'info') {
            const container = document.getElementById('logContainer');
            const iconMap = { 'success': 'check-circle', 'info': 'info', 'warn': 'alert-triangle', 'error': 'x-circle', 'process': 'loader-2' };
            const colorClass = `log-${type}`;
            const iconClass = type === 'process' ? 'animate-spin' : '';
            const html = `<div class="log-item ${colorClass}"><i data-lucide="${iconMap[type]}" class="w-4 h-4 mt-0.5 flex-shrink-0 ${iconClass}"></i><span class="${type === 'process' || type === 'info' ? 'text-slate-300' : ''}">${message}</span></div>`;
            container.insertAdjacentHTML('beforeend', html);
            container.scrollTop = container.scrollHeight;
            lucide.createIcons();
        }

        function updateProgress(stepName, percent) {
            document.getElementById('wizardStepText').innerText = stepName;
            document.getElementById('wizardPercent').innerText = percent + '%';
            document.getElementById('wizardBar').style.width = percent + '%';
        }

        async function startAutoPilot() {
            if(!confirm('Start Auto-Pilot? AI will complete all remaining steps automatically.')) return;
            document.getElementById('wizardModal').classList.remove('hidden');
            wizardStopped = false; document.body.style.overflow = 'hidden';

            try {
                // 1. Business Rules
                if (!state.hasRules) {
                    updateProgress('Extracting Business Rules...', 10); addLog('Analyzing project scope...', 'process');
                    await callApi('/services/ai/api/extract_rules.php'); addLog('Business rules extracted.', 'success');
                }
                if(wizardStopped) return;

                // 2. Tables
                if (!state.hasTables) {
                    updateProgress('Generating Tables...', 30); addLog('Designing ER Model...', 'process');
                    await callApi('/services/ai/api/generate_tables.php'); addLog('Tables created.', 'success');
                }
                if(wizardStopped) return;

                // 3. Gap Analysis & Fixing (DÜZELTİLEN BÖLÜM)
                if (!state.hasAudit || state.pendingIssuesCount > 0) {
                    updateProgress('Auditing & Fixing...', 45);

                    let shouldFix = false;

                    // A: Zaten bekleyen hatalar varsa, direkt düzeltmeye geç (tarama yapma)
                    if (state.pendingIssuesCount > 0) {
                        addLog(`${state.pendingIssuesCount} pending issues detected (cached).`, 'warn');
                        shouldFix = true;
                    }
                    // B: Yoksa tara
                    else {
                        addLog('Scanning for missing rules...', 'process');
                        const detectRes = await callApi('/services/ai/api/detect_missing_rules.php');
                        // DÜZELTME: Artık issue_count geliyor
                        if (detectRes.issue_count && detectRes.issue_count > 0) {
                            addLog(`${detectRes.issue_count} new issues detected by AI.`, 'warn');
                            shouldFix = true;
                        } else {
                            addLog('Audit passed. No new issues found.', 'success');
                        }
                    }

                    // C: Düzeltme İşlemi
                    if (shouldFix) {
                        addLog('Fetching pending issues list...', 'process');
                        // İSTEK: Belirttiğiniz yeni dosyadan çekiyor
                        const idsRes = await fetch(`/services/ai/api/check_missing_rules.php?id=${projectId}&ajax_action=get_pending_issues`);
                        const idsData = await idsRes.json();

                        if(idsData.ids && idsData.ids.length > 0) {
                            addLog(`Starting batch fix for ${idsData.ids.length} items...`, 'info');
                            let fixCount = 0;
                            for(const gapId of idsData.ids) {
                                if(wizardStopped) throw new Error('Stopped by user');
                                fixCount++;
                                addLog(`Fixing item ${fixCount}/${idsData.ids.length}...`, 'process');
                                const fixFormData = new FormData(); fixFormData.append('gap_id', gapId);
                                await fetch('/services/ai/api/resolve_missing_rule.php', { method: 'POST', body: fixFormData });
                            }
                            addLog('All issues resolved successfully.', 'success');
                        }
                    }
                }
                if(wizardStopped) return;

                // 4. Normalization
                if (!state.isNormalized) {
                    updateProgress('Normalizing...', 60);
                    addLog('Applying 1NF...', 'process'); await callApi('/services/ai/api/normalize_1nf.php');
                    addLog('Applying 2NF...', 'process'); await callApi('/services/ai/api/normalize_2nf.php');
                    addLog('Applying 3NF...', 'process'); await callApi('/services/ai/api/normalize_3nf.php');
                    addLog('Normalization (3NF) complete.', 'success');
                }
                if(wizardStopped) return;

                // 5. Rels
                if (!state.hasRels) {
                    updateProgress('Drawing Diagram...', 80); addLog('Mapping relationships...', 'process');
                    await callApi('/services/ai/api/generate_relationships.php'); addLog('Diagram generated.', 'success');
                }
                if(wizardStopped) return;

                // 6. SQL
                if (!state.hasSQL) {
                    updateProgress('Generating SQL...', 90); addLog('Writing Final DDL & Logic...', 'process');
                    await callApi('/services/sql/api/generate.php'); addLog('Production SQL ready.', 'success');
                }

                updateProgress('Completed', 100); addLog('🚀 Project Successfully Completed!', 'success');
                setTimeout(() => { window.location.reload(); }, 1500);

            } catch (e) { addLog(e.message, 'error'); updateProgress('Failed', 0); }
        }

        async function callApi(url) {
            if(wizardStopped) throw new Error('Stopped by user');
            const formData = new FormData(); formData.append('project_id', projectId);
            const res = await fetch(url, { method: 'POST', body: formData });
            const text = await res.text();
            try {
                const data = JSON.parse(text);
                if(!data.success) throw new Error(data.message);
                return data;
            } catch(e) { throw new Error(`API Error (${url}): ${text.substring(0, 50)}...`); }
        }

        function stopWizard() { wizardStopped = true; document.getElementById('wizardModal').classList.add('hidden'); document.body.style.overflow = 'auto'; }
    </script>

<?php require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/footer.php'; ?>