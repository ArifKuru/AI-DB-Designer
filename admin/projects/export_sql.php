<?php
require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/header.php';

if (!isset($_GET['id'])) exit("<script>window.location='/admin/projects/';</script>");
$project_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Proje Bilgisi
$project = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
$project->execute([$project_id, $user_id]);
$project = $project->fetch();

// Geçmiş Exportları Çek
$stmtExports = $db->prepare("SELECT * FROM project_exports WHERE project_id = ? ORDER BY id DESC");
$stmtExports->execute([$project_id]);
$history = $stmtExports->fetchAll(PDO::FETCH_ASSOC);

// En son SQL var mı?
$latestSQL = !empty($history) ? $history[0]['sql_content'] : "-- No SQL generated yet.\n-- Click 'Generate SQL' to start.";
$hasExport = !empty($history);
?>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-sql.min.js"></script>

    <style>
        /* Editör Görünümü */
        .code-editor {
            background: #2d2d2d;
            border-radius: 8px;
            overflow: hidden;
            height: 600px;
            position: relative;
            border: 1px solid #4b5563;
        }
        .code-editor pre {
            margin: 0;
            height: 100%;
            border-radius: 0;
            padding: 1.5rem;
            font-family: 'Fira Code', monospace;
            font-size: 14px;
            overflow: auto;
        }
        .history-item.active {
            background-color: #eff6ff;
            border-left: 4px solid #4f46e5;
        }
        /* Scrollbar */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background-color: #94a3b8; }
    </style>

    <div class="mb-6 border-b border-slate-200 pb-5 flex flex-col md:flex-row justify-between items-center gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 flex items-center">
                    <i data-lucide="database" class="w-7 h-7 mr-3 text-indigo-600"></i>
                    Stage 7: SQL Export
                </h1>
                <?php if($hasExport): ?>
                    <span class="bg-green-100 text-green-700 text-xs px-2.5 py-1 rounded-full font-bold border border-green-200 flex items-center">
                    <i data-lucide="check" class="w-3.5 h-3.5 mr-1.5"></i> Completed
                </span>
                <?php else: ?>
                    <span class="bg-amber-100 text-amber-700 text-xs px-2.5 py-1 rounded-full font-bold border border-amber-200 flex items-center">
                    <i data-lucide="clock" class="w-3.5 h-3.5 mr-1.5"></i> Pending
                </span>
                <?php endif; ?>
            </div>
            <p class="text-slate-500 text-sm mt-1">Final Output: Production-ready SQL Schema & Logic.</p>
        </div>

        <div class="flex gap-2">
            <a href="er_diagram?id=<?php echo $project_id; ?>" class="btn-sm bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-50 flex items-center shadow-sm transition">
                <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Previous
            </a>

            <button onclick="generateSQL()" class="btn-sm bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-sm font-bold flex items-center shadow-md transition">
                <i data-lucide="zap" class="w-4 h-4 mr-2"></i> Generate New SQL
            </button>

            <?php if($hasExport): ?>
                <a href="detail?id=<?php echo $project_id; ?>" class="btn-sm bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded-lg text-sm font-bold flex items-center shadow-lg transform hover:-translate-y-0.5 transition border border-emerald-700">
                    <i data-lucide="layout-dashboard" class="w-4 h-4 mr-2"></i> Project Dashboard
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 h-full">

        <div class="lg:col-span-3 flex flex-col h-[600px]">
            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm flex-1 flex flex-col">
                <div class="bg-slate-50 px-4 py-3 border-b border-slate-100 font-bold text-slate-700 flex items-center justify-between">
                    <span class="flex items-center"><i data-lucide="history" class="w-4 h-4 mr-2"></i> Version History</span>
                    <span class="text-xs bg-slate-200 text-slate-600 px-2 py-0.5 rounded-full"><?php echo count($history); ?></span>
                </div>

                <div class="flex-1 overflow-y-auto custom-scrollbar">
                    <?php if(empty($history)): ?>
                        <div class="p-6 text-center text-slate-400 text-sm italic">
                            <i data-lucide="file-code" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                            No exports generated yet.
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-slate-100" id="history-list">
                            <?php foreach($history as $index => $item):
                                $activeClass = ($index === 0) ? 'active' : '';
                                ?>
                                <div class="history-item p-4 hover:bg-slate-50 cursor-pointer transition <?php echo $activeClass; ?>" onclick="loadSQL(this, `<?php echo base64_encode($item['sql_content']); ?>`)">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="text-sm font-bold text-slate-700">Version #<?php echo $item['id']; ?></p>
                                            <p class="text-xs text-slate-500 mt-1 flex items-center">
                                                <i data-lucide="clock" class="w-3 h-3 mr-1"></i>
                                                <?php echo date('d M, H:i', strtotime($item['created_at'])); ?>
                                            </p>
                                        </div>
                                        <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300"></i>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="lg:col-span-9">
            <div class="relative code-editor shadow-lg group">
                <div class="absolute top-4 right-4 flex gap-2 z-10 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                    <button onclick="copyToClipboard()" class="bg-slate-700/80 hover:bg-slate-600 text-white p-2 rounded-lg backdrop-blur-sm transition shadow-sm" title="Copy to Clipboard">
                        <i data-lucide="copy" class="w-4 h-4"></i>
                    </button>
                    <button onclick="downloadSQL()" class="bg-indigo-600/90 hover:bg-indigo-500 text-white p-2 rounded-lg backdrop-blur-sm transition shadow-sm" title="Download .sql File">
                        <i data-lucide="download" class="w-4 h-4"></i>
                    </button>
                </div>

                <pre><code class="language-sql" id="sql-display"><?php echo htmlspecialchars($latestSQL); ?></code></pre>
            </div>
        </div>

    </div>

    <textarea id="hidden-sql" class="hidden"><?php echo htmlspecialchars($latestSQL); ?></textarea>

    <div id="loadingOverlay" class="fixed inset-0 bg-white/90 z-50 hidden flex-col items-center justify-center backdrop-blur-sm">
        <div class="relative">
            <div class="w-16 h-16 border-4 border-indigo-100 rounded-full"></div>
            <div class="w-16 h-16 border-4 border-indigo-600 rounded-full animate-spin absolute top-0 left-0 border-t-transparent"></div>
        </div>
        <h3 class="text-lg font-bold text-slate-800 mt-4">Generating SQL Schema...</h3>
        <p class="text-slate-500 text-sm">Combining Structure, Relations, and Business Logic</p>
    </div>

    <script>
        function generateSQL() {
            if(!confirm('Generate a new SQL version based on current schema and rules?')) return;

            const overlay = document.getElementById('loadingOverlay');
            overlay.classList.remove('hidden'); overlay.classList.add('flex');

            const formData = new FormData();
            formData.append('project_id', <?php echo $project_id; ?>);

            fetch('/services/sql/api/generate.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                        overlay.classList.add('hidden'); overlay.classList.remove('flex');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Connection error.');
                    overlay.classList.add('hidden'); overlay.classList.remove('flex');
                });
        }

        function loadSQL(element, base64Sql) {
            document.querySelectorAll('.history-item').forEach(el => el.classList.remove('active'));
            element.classList.add('active');

            const sql = atob(base64Sql);
            const codeBlock = document.getElementById('sql-display');
            const hiddenText = document.getElementById('hidden-sql');

            codeBlock.textContent = sql;
            hiddenText.value = sql;
            Prism.highlightElement(codeBlock);
        }

        function copyToClipboard() {
            const sql = document.getElementById('hidden-sql').value;
            navigator.clipboard.writeText(sql).then(() => {
                alert('SQL copied to clipboard!');
            });
        }

        function downloadSQL() {
            const sql = document.getElementById('hidden-sql').value;
            const blob = new Blob([sql], {type: "text/plain;charset=utf-8"});
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = url;
            link.download = "Project_<?php echo $project_id; ?>_Full_Schema.sql";
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>

<?php require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/footer.php'; ?>