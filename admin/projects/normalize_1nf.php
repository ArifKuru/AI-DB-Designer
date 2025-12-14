<?php
// Header ve Konfigürasyon
require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/header.php';

// ID ve Yetki Kontrolü
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>window.location='/admin/projects/';</script>";
    exit;
}
$project_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// 1. Proje Bilgisi
$project = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
$project->execute([$project_id, $user_id]);
$project = $project->fetch();

if (!$project) {
    echo "<div class='p-12 text-center text-red-500'>Project not found.</div>";
    require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/footer.php';
    exit;
}

// 2. Mevcut Tabloları Çek (Görselleştirme için)
$stmtTables = $db->prepare("SELECT * FROM project_tables WHERE project_id = :pid");
$stmtTables->execute([':pid' => $project_id]);
$rawTables = $stmtTables->fetchAll(PDO::FETCH_ASSOC);

$tables = [];
if (!empty($rawTables)) {
    foreach ($rawTables as $t) {
        $stmtCols = $db->prepare("SELECT * FROM project_columns WHERE table_id = :tid");
        $stmtCols->execute([':tid' => $t['id']]);
        $t['columns'] = $stmtCols->fetchAll(PDO::FETCH_ASSOC);
        $tables[] = $t;
    }
}

// 3. Logları Çek (Bu stage'e ait işlemler)
$stmtLogs = $db->prepare("SELECT * FROM project_normalization_logs WHERE project_id = ? AND stage = '1NF' ORDER BY id DESC");
$stmtLogs->execute([$project_id]);
$logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

// 4. DURUM KONTROLÜ (KRİTİK DÜZELTME)
// Eğer log varsa, işlem en az bir kere yapılmıştır ve "Completed" sayılır.
$isCompleted = !empty($logs);
?>

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background-color: #94a3b8; }
    </style>

    <div class="mb-8 border-b border-slate-200 pb-6 flex flex-col md:flex-row justify-between items-center gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900">Stage 5: Normalization (1NF)</h1>

                <?php if($isCompleted): ?>
                    <span class="bg-green-100 text-green-700 text-xs px-2.5 py-1 rounded-full font-bold border border-green-200 flex items-center">
                    <i data-lucide="check" class="w-3.5 h-3.5 mr-1.5"></i> Completed
                </span>
                <?php else: ?>
                    <span class="bg-amber-100 text-amber-700 text-xs px-2.5 py-1 rounded-full font-bold border border-amber-200 flex items-center">
                    <i data-lucide="clock" class="w-3.5 h-3.5 mr-1.5"></i> Pending
                </span>
                <?php endif; ?>
            </div>
            <p class="text-slate-500 text-sm mt-1">Goal: Enforce atomic attributes and eliminate repeating groups.</p>
        </div>

        <div class="flex gap-2">
            <a href="missing_rules?id=<?php echo $project_id; ?>" class="btn-sm bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-50 flex items-center transition shadow-sm">
                <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Previous
            </a>

            <?php if($isCompleted): ?>
                <a href="normalize_2nf?id=<?php echo $project_id; ?>" class="btn-sm bg-slate-900 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 flex items-center shadow-lg transform hover:-translate-y-0.5 transition">
                    Next: 2NF <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
                </a>
            <?php else: ?>
                <button disabled class="btn-sm bg-slate-100 text-slate-400 px-5 py-2 rounded-lg text-sm font-medium cursor-not-allowed flex items-center">
                    Next: 2NF <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

        <div class="lg:col-span-4 space-y-6">

            <div class="bg-blue-50 border border-blue-100 rounded-xl p-6 shadow-sm">
                <h3 class="text-blue-900 font-bold mb-2 flex items-center">
                    <i data-lucide="wand-2" class="w-5 h-5 mr-2"></i> 1NF Transformation
                </h3>
                <p class="text-sm text-blue-800/80 mb-6 leading-relaxed">
                    The AI will scan all tables for non-atomic values (e.g., lists in a single cell) and create new tables to separate them.
                </p>

                <button onclick="apply1NF()" id="applyBtn" class="w-full py-3 <?php echo $isCompleted ? 'bg-white border border-blue-200 text-blue-600 hover:bg-blue-50' : 'bg-blue-600 hover:bg-blue-700 text-white'; ?> rounded-lg font-bold shadow-sm transition flex justify-center items-center">
                    <?php if($isCompleted): ?>
                        <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i> Re-Apply 1NF
                    <?php else: ?>
                        <i data-lucide="play" class="w-4 h-4 mr-2"></i> Apply 1NF Logic
                    <?php endif; ?>
                </button>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm flex flex-col h-[500px]">
                <div class="bg-slate-50 px-4 py-3 border-b border-slate-100 font-bold text-slate-700 flex items-center justify-between">
                    <span class="flex items-center"><i data-lucide="history" class="w-4 h-4 mr-2"></i> AI Change Log</span>
                    <span class="text-xs bg-slate-200 text-slate-600 px-2 py-0.5 rounded-full"><?php echo count($logs); ?></span>
                </div>

                <div class="flex-1 overflow-y-auto custom-scrollbar p-0">
                    <?php if(empty($logs)): ?>
                        <div class="h-full flex flex-col items-center justify-center text-center p-6 text-slate-400">
                            <i data-lucide="clipboard-list" class="w-8 h-8 mb-2 opacity-20"></i>
                            <span class="text-sm italic">No changes recorded yet.<br>Click apply to start.</span>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-slate-100">
                            <?php foreach($logs as $log): ?>
                                <div class="p-4 hover:bg-slate-50 transition group">
                                    <div class="flex gap-3">
                                        <div class="mt-0.5 flex-shrink-0">
                                            <i data-lucide="check-circle-2" class="w-5 h-5 text-green-500"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm text-slate-700 leading-snug group-hover:text-slate-900">
                                                <?php echo htmlspecialchars($log['description']); ?>
                                            </p>
                                            <p class="text-[10px] text-slate-400 mt-1 font-mono">
                                                <?php echo date('H:i:s', strtotime($log['created_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="lg:col-span-8">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-slate-700 flex items-center">
                    <i data-lucide="database" class="w-4 h-4 mr-2 text-slate-400"></i> Current Database Schema
                </h3>
                <span class="text-xs text-slate-500 bg-slate-100 px-2 py-1 rounded">
                Tables: <?php echo count($tables); ?>
            </span>
            </div>

            <?php if(empty($tables)): ?>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-16 text-center border-dashed">
                    <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="table-2" class="w-8 h-8 text-slate-300"></i>
                    </div>
                    <p class="text-slate-500 font-medium">No tables found.</p>
                    <p class="text-slate-400 text-sm mt-1">Go back to "Generate Tables" step.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($tables as $table): ?>
                        <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden group hover:shadow-md transition duration-200 flex flex-col h-full max-h-[300px]">

                            <div class="bg-slate-50 px-4 py-2.5 border-b border-slate-100 flex justify-between items-center flex-shrink-0">
                            <span class="font-bold text-slate-700 text-sm flex items-center truncate" title="<?php echo htmlspecialchars($table['table_name']); ?>">
                                <i data-lucide="table" class="w-4 h-4 mr-2 text-indigo-500 flex-shrink-0"></i>
                                <?php echo htmlspecialchars($table['table_name']); ?>
                            </span>
                                <span class="text-[10px] bg-white border border-slate-200 px-1.5 py-0.5 rounded font-mono text-slate-500 flex-shrink-0">
                                <?php echo htmlspecialchars($table['normalization_level']); ?>
                            </span>
                            </div>

                            <div class="p-0 overflow-y-auto custom-scrollbar flex-1 bg-white">
                                <table class="w-full text-left text-xs">
                                    <tbody class="divide-y divide-slate-50">
                                    <?php foreach ($table['columns'] as $col): ?>
                                        <tr class="hover:bg-indigo-50/30 transition-colors">
                                            <td class="px-4 py-2 font-mono text-slate-600 flex items-center gap-1.5">
                                                <?php if ($col['is_primary_key']): ?>
                                                    <i data-lucide="key" class="w-3 h-3 text-amber-500 fill-amber-500 flex-shrink-0" title="Primary Key"></i>
                                                <?php elseif ($col['is_foreign_key']): ?>
                                                    <i data-lucide="link" class="w-3 h-3 text-blue-500 flex-shrink-0" title="Foreign Key"></i>
                                                <?php else: ?>
                                                    <span class="w-3 h-3 inline-block flex-shrink-0"></span>
                                                <?php endif; ?>

                                                <span class="<?php echo $col['is_primary_key'] ? 'font-bold text-slate-900' : ''; ?>">
                                                <?php echo htmlspecialchars($col['name']); ?>
                                            </span>
                                            </td>
                                            <td class="px-4 py-2 text-right text-slate-400 font-mono text-[10px]">
                                                <?php echo htmlspecialchars($col['data_type']); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script>
        function apply1NF() {
            // Re-apply uyarısı
            <?php if($isCompleted): ?>
            if(!confirm('Attention: Re-applying 1NF logic might change the table structure. This is generally safe but check your data if any. Continue?')) return;
            <?php else: ?>
            if(!confirm('AI will analyze the schema and enforce 1NF rules (Atomic values). Continue?')) return;
            <?php endif; ?>

            const btn = document.getElementById('applyBtn');
            const originalHtml = btn.innerHTML;

            // Loading State
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 mr-2 animate-spin"></i> Processing...`;
            lucide.createIcons();

            const formData = new FormData();
            formData.append('project_id', <?php echo $project_id; ?>);

            fetch('/services/ai/api/normalize_1nf.php', {
                method: 'POST',
                body: formData
            })
                .then(async res => {
                    const text = await res.text();
                    try {
                        const data = JSON.parse(text);
                        if(data.success) {
                            // Başarılı ise sayfayı yenile
                            window.location.reload();
                        } else {
                            alert('API Error: ' + data.message);
                            resetBtn();
                        }
                    } catch(e) {
                        console.error("Server Raw Response:", text);
                        alert('Server Error (Check Console for details).');
                        resetBtn();
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Connection Error: ' + err.message);
                    resetBtn();
                });

            function resetBtn() {
                btn.disabled = false;
                btn.classList.remove('opacity-75', 'cursor-not-allowed');
                btn.innerHTML = originalHtml;
                lucide.createIcons();
            }
        }
    </script>

<?php
// Footer Dahil Et
require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/footer.php';
?>