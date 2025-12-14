<?php
require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/header.php';

if (!isset($_GET['id'])) exit("<script>window.location='/admin/projects/';</script>");
$project_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Proje Bilgisi
$project = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
$project->execute([$project_id, $user_id]);
$project = $project->fetch();

// 1. EKSİKLERİ ÇEK
$gaps = $db->prepare("SELECT * FROM project_missing_rules WHERE project_id = ? ORDER BY status ASC, id DESC");
$gaps->execute([$project_id]);
$gaps = $gaps->fetchAll(PDO::FETCH_ASSOC);

// 2. İSTATİSTİKLERİ HESAPLA
$pendingCount = 0;
$totalGaps = count($gaps);

// Bekleyen ID'leri topla (JS için)
$pendingIds = [];

foreach ($gaps as $gap) {
    if ($gap['status'] === 'pending') {
        $pendingCount++;
        $pendingIds[] = $gap['id'];
    }
}

// 3. TAMAMLANMA DURUMU VE YÜZDE HESABI
// Completed: En az 1 kayıt var VE bekleyen sorun 0.
$isCompleted = ($totalGaps > 0 && $pendingCount === 0);

// Yüzde Hesabı
$completionRate = 0;
if ($totalGaps > 0) {
    $resolvedCount = $totalGaps - $pendingCount;
    $completionRate = floor(($resolvedCount / $totalGaps) * 100);
}
?>

    <div class="mb-8 border-b border-slate-200 pb-6 flex flex-col md:flex-row justify-between items-center gap-4">
        <div class="flex-1">
            <div class="flex items-center gap-3 mb-2">
                <h1 class="text-2xl font-bold text-slate-900 flex items-center">
                    <i data-lucide="microscope" class="w-6 h-6 mr-3 text-indigo-600"></i>
                    Stage 4: Missing Rule Detection
                </h1>

                <?php if ($isCompleted): ?>
                    <span class="bg-green-100 text-green-700 text-xs px-2.5 py-1 rounded-full font-bold border border-green-200 flex items-center">
                        <i data-lucide="check-circle-2" class="w-3.5 h-3.5 mr-1.5"></i> 100% Completed
                    </span>
                <?php elseif ($totalGaps > 0): ?>
                    <span class="bg-indigo-100 text-indigo-700 text-xs px-2.5 py-1 rounded-full font-bold border border-indigo-200 flex items-center">
                        <i data-lucide="pie-chart" class="w-3.5 h-3.5 mr-1.5"></i> <?php echo $completionRate; ?>% Resolved
                    </span>
                    <span class="bg-rose-100 text-rose-700 text-xs px-2.5 py-1 rounded-full font-bold border border-rose-200 flex items-center">
                        <i data-lucide="alert-triangle" class="w-3.5 h-3.5 mr-1.5"></i> <?php echo $pendingCount; ?> Pending
                    </span>
                <?php else: ?>
                    <span class="bg-amber-100 text-amber-700 text-xs px-2.5 py-1 rounded-full font-bold border border-amber-200 flex items-center">
                        <i data-lucide="clock" class="w-3.5 h-3.5 mr-1.5"></i> Pending Audit
                    </span>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-4">
                <p class="text-slate-500 text-sm">AI Audit: Comparing Business Rules vs. Database Schema.</p>

                <?php if($totalGaps > 0 && !$isCompleted): ?>
                    <div class="hidden md:flex items-center gap-2 flex-1 max-w-xs">
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="bg-indigo-600 h-2 rounded-full transition-all duration-500" style="width: <?php echo $completionRate; ?>%"></div>
                        </div>
                        <span class="text-xs font-bold text-slate-600 whitespace-nowrap"><?php echo $completionRate; ?>%</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex gap-2">
            <a href="tables_design?id=<?php echo $project_id; ?>" class="btn-sm bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-50 flex items-center shadow-sm transition">
                <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Previous
            </a>

            <?php if($isCompleted): ?>
                <a href="normalize_1nf?id=<?php echo $project_id; ?>" class="btn-sm bg-slate-900 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 flex items-center shadow-lg transform hover:-translate-y-0.5 transition">
                    Next: Normalization <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
                </a>
            <?php else: ?>
                <button disabled class="btn-sm bg-slate-100 text-slate-400 px-5 py-2 rounded-lg text-sm font-medium cursor-not-allowed flex items-center border border-slate-200">
                    Next: Normalization <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <div class="lg:col-span-1 space-y-6">
            <div class="bg-amber-50 rounded-xl p-6 border border-amber-100 shadow-sm">
                <h3 class="text-amber-900 font-bold text-lg mb-2">Automated Audit</h3>
                <p class="text-amber-800/70 text-sm mb-6 leading-relaxed">
                    The AI Auditor scans for:
                <ul class="list-disc ml-5 mt-2 space-y-1 mb-6 text-amber-900/80 text-sm">
                    <li>Missing M:N Junction Tables</li>
                    <li>Missing Foreign Keys</li>
                    <li>Undefined attributes</li>
                </ul>
                </p>

                <?php if ($isCompleted): ?>
                    <div class="bg-white/60 rounded-lg p-4 border border-amber-100 text-center mb-3">
                        <div class="flex items-center justify-center text-green-600 font-bold gap-2 mb-1">
                            <i data-lucide="check-circle" class="w-5 h-5"></i>
                            <span>Analysis Completed</span>
                        </div>
                        <p class="text-xs text-amber-800/60">System rules match the database.</p>
                    </div>

                    <button onclick="runAudit()" id="auditBtn" class="w-full py-2 bg-transparent border border-amber-300 text-amber-700 hover:bg-amber-100 rounded-lg text-sm font-medium transition flex justify-center items-center">
                        <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i> Run Analysis Again
                    </button>
                <?php else: ?>
                    <button onclick="runAudit()" id="auditBtn" class="w-full py-3 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-bold shadow-md flex justify-center items-center transition">
                        <i data-lucide="scan-search" class="w-5 h-5 mr-2"></i> Run Gap Analysis
                    </button>
                <?php endif; ?>
            </div>

            <?php if (!empty($pendingIds)): ?>
                <div class="bg-indigo-50 rounded-xl p-6 border border-indigo-100 shadow-sm">
                    <h3 class="text-indigo-900 font-bold text-lg mb-2">Bulk Actions</h3>
                    <p class="text-indigo-800/70 text-sm mb-4">
                        You have <strong><?php echo count($pendingIds); ?></strong> pending issues.
                        <br> Current Progress: <strong><?php echo $completionRate; ?>%</strong>
                    </p>
                    <button onclick="fixAllIssues()" id="fixAllBtn" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold shadow-md flex justify-center items-center transition">
                        <i data-lucide="wand-2" class="w-5 h-5 mr-2"></i> Fix All Remaining (<?php echo count($pendingIds); ?>)
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden min-h-[400px]">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                    <h3 class="font-bold text-slate-700 flex items-center">
                        <i data-lucide="list-checks" class="w-4 h-4 mr-2 text-slate-400"></i> Detected Issues
                    </h3>
                    <span class="text-xs bg-slate-200 text-slate-600 px-2 py-1 rounded-full font-bold"><?php echo count($gaps); ?> Items</span>
                </div>

                <?php if (empty($gaps)): ?>
                    <div class="p-12 text-center">
                        <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i data-lucide="check-circle-2" class="w-8 h-8 text-green-500 opacity-50"></i>
                        </div>
                        <h4 class="text-lg font-medium text-slate-900">All Clear</h4>
                        <p class="text-slate-500 text-sm mt-1">No gaps detected yet. Click "Run Gap Analysis" to scan.</p>
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-slate-100">
                        <?php foreach ($gaps as $gap):
                            $isResolved = $gap['status'] === 'resolved';
                            ?>
                            <div class="p-6 transition hover:bg-slate-50/50 flex flex-col md:flex-row gap-4" id="row-<?php echo $gap['id']; ?>">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                <span class="text-[10px] font-mono font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100">
                                    <?php echo htmlspecialchars($gap['related_br']); ?>
                                </span>
                                        <?php if($isResolved): ?>
                                            <span class="text-[10px] font-bold text-green-600 bg-green-50 px-2 py-0.5 rounded border border-green-100 flex items-center">
                                        <i data-lucide="check" class="w-3 h-3 mr-1"></i> Resolved
                                    </span>
                                        <?php else: ?>
                                            <span class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded border border-amber-100 status-badge">
                                        Pending
                                    </span>
                                        <?php endif; ?>
                                    </div>
                                    <h4 class="text-slate-800 font-medium mb-1 text-sm"><?php echo htmlspecialchars($gap['missing_rule']); ?></h4>
                                    <p class="text-xs text-slate-500 italic bg-slate-50 p-2 rounded border border-slate-100 inline-block mt-1">
                                        <span class="font-bold not-italic text-slate-400">Suggestion:</span> <?php echo htmlspecialchars($gap['solution']); ?>
                                    </p>
                                </div>

                                <div class="flex items-center">
                                    <?php if(!$isResolved): ?>
                                        <button onclick="fixIssue(<?php echo $gap['id']; ?>, this)" class="px-3 py-1.5 bg-white border border-slate-300 text-slate-700 text-xs font-bold rounded-lg hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-200 transition shadow-sm flex items-center whitespace-nowrap btn-fix">
                                            <i data-lucide="wrench" class="w-3.5 h-3.5 mr-1.5"></i> Apply Fix
                                        </button>
                                    <?php else: ?>
                                        <button disabled class="px-3 py-1.5 bg-slate-50 border border-slate-100 text-slate-400 text-xs font-bold rounded-lg cursor-not-allowed flex items-center">
                                            Fixed
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="progressModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-900/70 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl p-8 max-w-md w-full text-center">
            <div class="w-16 h-16 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-4">
                <i data-lucide="loader-2" class="w-8 h-8 text-indigo-600 animate-spin"></i>
            </div>
            <h3 class="text-xl font-bold text-slate-900 mb-2">Applying Batch Fixes</h3>
            <p class="text-slate-500 text-sm mb-6" id="progressText">Starting process...</p>

            <div class="w-full bg-slate-100 rounded-full h-3 mb-2 overflow-hidden">
                <div id="progressBar" class="bg-indigo-600 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
            </div>
            <p class="text-xs text-slate-400 font-mono" id="progressCount">0 / 0</p>
        </div>
    </div>

    <script>
        // PHP'den gelen Pending ID listesi
        const pendingGapIds = <?php echo json_encode($pendingIds); ?>;

        // --- ACTIONS ---

        function runAudit() {
            const btn = document.getElementById('auditBtn');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<i data-lucide="loader-2" class="w-5 h-5 mr-2 animate-spin"></i> Analyzing...`;
            lucide.createIcons();

            const formData = new FormData();
            formData.append('project_id', <?php echo $project_id; ?>);

            fetch('/services/ai/api/detect_missing_rules.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.success) window.location.reload();
                    else { alert('Error: ' + data.message); btn.disabled = false; btn.innerHTML = originalHtml; lucide.createIcons(); }
                })
                .catch(err => { console.error(err); alert('Connection error.'); btn.disabled = false; btn.innerHTML = originalHtml; lucide.createIcons(); });
        }

        // TEKİL DÜZELTME
        function fixIssue(id, btnElement) {
            if(!confirm('AI will attempt to fix this issue. Continue?')) return;
            performFix(id, btnElement);
        }

        // API ÇAĞRISI (Ortak Fonksiyon)
        async function performFix(id, btnElement = null) {
            if(btnElement) {
                btnElement.disabled = true;
                btnElement.innerHTML = `<i data-lucide="loader-2" class="w-3.5 h-3.5 mr-1.5 animate-spin"></i> Fixing...`;
                lucide.createIcons();
            }

            const formData = new FormData();
            formData.append('gap_id', id);

            try {
                const res = await fetch('/services/ai/api/resolve_missing_rule.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    if(btnElement) {
                        // UI Güncelleme (Reload yapmadan)
                        const row = document.getElementById('row-' + id);
                        // Badge güncelle
                        const badge = row.querySelector('.status-badge');
                        if(badge) {
                            badge.className = "text-[10px] font-bold text-green-600 bg-green-50 px-2 py-0.5 rounded border border-green-100 flex items-center";
                            badge.innerHTML = `<i data-lucide="check" class="w-3 h-3 mr-1"></i> Resolved`;
                        }
                        // Buton güncelle
                        btnElement.className = "px-3 py-1.5 bg-slate-50 border border-slate-100 text-slate-400 text-xs font-bold rounded-lg cursor-not-allowed flex items-center";
                        btnElement.disabled = true;
                        btnElement.innerHTML = "Fixed";
                        lucide.createIcons();
                    }
                    return true;
                } else {
                    if(btnElement) { alert('Error: ' + data.message); window.location.reload(); }
                    return false;
                }
            } catch (err) {
                console.error(err);
                if(btnElement) { alert('Connection error.'); window.location.reload(); }
                return false;
            }
        }

        // --- TOPLU DÜZELTME (BATCH FIX) ---
        async function fixAllIssues() {
            if(pendingGapIds.length === 0) return;
            if(!confirm(`Are you sure you want to fix all ${pendingGapIds.length} issues automatically? This may take some time.`)) return;

            // Modal Aç
            const modal = document.getElementById('progressModal');
            const pBar = document.getElementById('progressBar');
            const pText = document.getElementById('progressText');
            const pCount = document.getElementById('progressCount');

            modal.classList.remove('hidden');

            let successCount = 0;

            // Döngü ile tek tek işle
            for (let i = 0; i < pendingGapIds.length; i++) {
                const id = pendingGapIds[i];
                const currentNum = i + 1;
                const total = pendingGapIds.length;

                // UI Güncelle
                pText.innerText = `Fixing issue ${currentNum} of ${total}...`;
                pCount.innerText = `${currentNum} / ${total}`;
                pBar.style.width = `${(currentNum / total) * 100}%`;

                // API Çağrısı (Await ile bekle)
                const result = await performFix(id);
                if(result) successCount++;
            }

            // Bitti
            pText.innerText = "Process Completed!";
            pBar.className = "bg-green-500 h-3 rounded-full";

            setTimeout(() => {
                alert(`Batch process finished. ${successCount}/${pendingGapIds.length} issues resolved successfully.`);
                window.location.reload();
            }, 1000);
        }
    </script>

<?php require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/footer.php'; ?>