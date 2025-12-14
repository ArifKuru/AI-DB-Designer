<?php
require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/header.php';

// ID Kontrol
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>window.location.href = '/admin/projects/';</script>";
    exit;
}

$project_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Proje Verisini Çek
$stmt = $db->prepare("SELECT * FROM projects WHERE id = :id AND user_id = :uid");
$stmt->execute([':id' => $project_id, ':uid' => $user_id]);
$project = $stmt->fetch();

if (!$project) {
    echo "<div class='p-12 text-center text-red-500 font-bold'>Proje bulunamadı.</div>";
    require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/footer.php';
    exit;
}

// Tabloları ve Sütunları Çek
$tables = [];
try {
    $stmtTables = $db->prepare("SELECT * FROM project_tables WHERE project_id = :pid ORDER BY id ASC");
    $stmtTables->execute([':pid' => $project_id]);
    $rawTables = $stmtTables->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rawTables as $t) {
        $stmtCols = $db->prepare("SELECT * FROM project_columns WHERE table_id = :tid");
        $stmtCols->execute([':tid' => $t['id']]);
        $t['columns'] = $stmtCols->fetchAll(PDO::FETCH_ASSOC);
        $tables[] = $t;
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

    <style>
        /* Kartların içindeki listeler için ince scrollbar */
        .card-scrollbar::-webkit-scrollbar { width: 4px; }
        .card-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .card-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 4px; }
        .card-scrollbar::-webkit-scrollbar-thumb:hover { background-color: #94a3b8; }
    </style>

    <div class="mb-8 border-b border-slate-200 pb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">

        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 flex items-center">
                    <i data-lucide="layout-grid" class="w-6 h-6 mr-3 text-indigo-600"></i>
                    Stage 3: Database Schema Design
                </h1>

                <?php if (!empty($tables)): ?>
                    <span class="bg-green-100 text-green-700 text-xs px-2.5 py-1 rounded-full font-bold border border-green-200 flex items-center">
                    <i data-lucide="check" class="w-3.5 h-3.5 mr-1.5"></i> Completed
                </span>
                <?php else: ?>
                    <span class="bg-amber-100 text-amber-700 text-xs px-2.5 py-1 rounded-full font-bold border border-amber-200 flex items-center">
                    <i data-lucide="clock" class="w-3.5 h-3.5 mr-1.5"></i> Pending
                </span>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-3 text-slate-500 text-sm mt-1 ml-9">
                <span class="font-medium text-slate-700"><?php echo htmlspecialchars($project['name']); ?></span>
                <span class="text-slate-300">•</span>
                <span>ER Model</span>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/admin/projects/business_rules?id=<?php echo $project_id; ?>" class="btn-sm bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 px-3 py-2 rounded-lg text-sm font-medium transition flex items-center shadow-sm">
                <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Business Rules
            </a>

            <?php if (!empty($tables)): ?>
                <button onclick="generateTables()" class="btn-sm bg-white border border-indigo-200 text-indigo-600 hover:bg-indigo-50 px-3 py-2 rounded-lg text-sm font-medium transition flex items-center shadow-sm">
                    <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i> Regenerate
                </button>

                <a href="/admin/projects/missing_rules?id=<?php echo $project_id; ?>" class="btn-sm bg-slate-900 text-white hover:bg-slate-800 px-5 py-2 rounded-lg text-sm font-medium transition flex items-center shadow-lg transform hover:-translate-y-0.5">
                    Check Missing Rules <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>

<?php if (empty($tables)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-16 text-center max-w-2xl mx-auto mt-12">
        <div class="w-24 h-24 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-6 relative group cursor-pointer" onclick="generateTables()">
            <div class="absolute inset-0 bg-indigo-100 rounded-full opacity-50 animate-pulse"></div>
            <i data-lucide="table-2" class="w-10 h-10 text-indigo-600 relative z-10 transition-transform group-hover:scale-110"></i>
        </div>
        <h2 class="text-2xl font-bold text-slate-900 mb-2">Schema Not Generated Yet</h2>
        <p class="text-slate-500 mb-8 max-w-md mx-auto leading-relaxed">
            The AI is ready to convert your defined business rules into a relational database schema. This process will create tables, columns, and identify constraints like PK, FK, and UNIQUE.
        </p>
        <button onclick="generateTables()" class="px-8 py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold shadow-lg shadow-indigo-200 flex items-center mx-auto transition-all transform hover:-translate-y-1">
            <i data-lucide="wand-2" class="w-5 h-5 mr-2"></i> Generate Tables with AI
        </button>
    </div>

<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 items-start pb-20">
        <?php foreach ($tables as $table): ?>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition-shadow group flex flex-col h-full max-h-[500px]">

                <div class="bg-slate-50 px-4 py-3 border-b border-slate-100 flex justify-between items-start flex-shrink-0">
                    <div class="overflow-hidden mr-2">
                        <h3 class="font-bold text-slate-800 text-sm flex items-center truncate" title="<?php echo htmlspecialchars($table['table_name']); ?>">
                            <i data-lucide="table" class="w-4 h-4 mr-2 text-indigo-500 flex-shrink-0"></i>
                            <span class="truncate"><?php echo htmlspecialchars($table['table_name']); ?></span>
                        </h3>
                        <?php if($table['description']): ?>
                            <p class="text-[10px] text-slate-500 mt-1 truncate" title="<?php echo htmlspecialchars($table['description']); ?>">
                                <?php echo htmlspecialchars($table['description']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <span class="text-[10px] font-mono font-bold bg-white border border-slate-200 px-1.5 py-0.5 rounded text-slate-500 flex-shrink-0">
                        <?php echo $table['normalization_level']; ?>
                    </span>
                </div>

                <div class="p-0 overflow-y-auto card-scrollbar bg-white flex-1">
                    <table class="w-full text-left border-collapse">
                        <tbody class="divide-y divide-slate-50 text-xs">
                        <?php foreach ($table['columns'] as $col): ?>
                            <tr class="hover:bg-indigo-50/30 transition-colors">
                                <td class="px-4 py-2.5 font-mono text-slate-700 align-top">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <?php if ($col['is_primary_key']): ?>
                                            <i data-lucide="key" class="w-3.5 h-3.5 text-amber-500 fill-amber-500 flex-shrink-0" title="Primary Key"></i>
                                        <?php elseif ($col['is_foreign_key']): ?>
                                            <i data-lucide="link" class="w-3.5 h-3.5 text-blue-500 flex-shrink-0" title="Foreign Key"></i>
                                        <?php else: ?>
                                            <span class="w-3.5 h-3.5 inline-block flex-shrink-0"></span> <?php endif; ?>

                                        <span class="<?php echo $col['is_primary_key'] ? 'font-bold text-slate-900' : ''; ?>">
                                                <?php echo htmlspecialchars($col['name']); ?>
                                            </span>

                                        <?php if ($col['is_unique']): ?>
                                            <span class="text-[9px] bg-purple-50 text-purple-700 border border-purple-200 px-1 rounded font-bold cursor-help" title="Unique Constraint">UQ</span>
                                        <?php endif; ?>

                                        <?php if (!$col['is_nullable'] && !$col['is_primary_key']): ?>
                                            <span class="text-[9px] bg-red-50 text-red-600 border border-red-100 px-1 rounded font-bold cursor-help" title="Not Null">NN</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="px-4 py-2.5 text-right align-top">
                                    <div class="flex flex-col items-end gap-0.5">
                                            <span class="text-slate-500 font-mono text-[10px] whitespace-nowrap">
                                                <?php echo htmlspecialchars($col['data_type']); ?>
                                            </span>

                                        <?php if (!empty($col['check_constraint'])): ?>
                                            <span class="text-[9px] text-slate-400 italic max-w-[80px] truncate border-b border-dotted border-slate-300 cursor-help" title="Check: <?php echo htmlspecialchars($col['check_constraint']); ?>">
                                                    Check...
                                                </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="bg-slate-50 px-4 py-2 border-t border-slate-100 text-[10px] text-slate-400 text-center flex-shrink-0">
                    <?php echo count($table['columns']); ?> Columns defined
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

    <div id="loadingOverlay" class="fixed inset-0 bg-white/90 z-50 hidden flex-col items-center justify-center backdrop-blur-sm">
        <div class="relative mb-6">
            <div class="w-24 h-24 border-4 border-indigo-100 rounded-full"></div>
            <div class="w-24 h-24 border-4 border-indigo-600 rounded-full animate-spin absolute top-0 left-0 border-t-transparent"></div>
            <i data-lucide="database" class="w-8 h-8 text-indigo-600 absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2"></i>
        </div>
        <h3 class="text-2xl font-bold text-slate-900 mb-2">Designing Schema...</h3>
        <p class="text-slate-500 text-center max-w-sm px-4">
            AI is strictly following business rules to create entities, relationships, and constraints.
        </p>
    </div>

    <script>
        function generateTables() {
            if(confirm('This will DELETE current tables and regenerate them based on rules. Continue?')) {
                const overlay = document.getElementById('loadingOverlay');
                overlay.classList.remove('hidden');
                overlay.classList.add('flex');

                const formData = new FormData();
                formData.append('project_id', <?php echo $project_id; ?>);

                fetch('/services/ai/api/generate_tables.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert('Error: ' + data.message);
                            overlay.classList.add('hidden');
                            overlay.classList.remove('flex');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Connection error occurred.');
                        overlay.classList.add('hidden');
                        overlay.classList.remove('flex');
                    });
            }
        }
    </script>

<?php require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/footer.php'; ?>