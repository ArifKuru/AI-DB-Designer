<?php
require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/header.php';

// ID Kontrolü
if (!isset($_GET['id'])) exit("<script>window.location='/admin/projects/';</script>");
$project_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Proje Bilgisi
$project = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
$project->execute([$project_id, $user_id]);
$project = $project->fetch();
// Durum Kontrolü: Eğer ilişkiler (relationships) tablosunda kayıt varsa "Completed" sayılır.
$stmtCheck = $db->prepare("SELECT COUNT(*) FROM project_relationships WHERE project_id = ?");
$stmtCheck->execute([$project_id]);
$isCompleted = $stmtCheck->fetchColumn() > 0;
?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/viz.js/2.1.2/viz.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/viz.js/2.1.2/full.render.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/svg-pan-zoom@3.6.1/dist/svg-pan-zoom.min.js"></script>

    <style>
        /* Ana Çerçeve */
        #diagram-wrapper {
            width: 100%;
            height: 750px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
            box-shadow: inset 0 2px 4px 0 rgb(0 0 0 / 0.05);
        }

        #diagram-wrapper::before {
            content: "";
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: radial-gradient(#cbd5e1 1.5px, transparent 1.5px);
            background-size: 24px 24px;
            opacity: 0.6;
            pointer-events: none;
        }

        #diagram-container {
            width: 100%;
            height: 100%;
            outline: none;
        }

        /* Toolbar */
        .diagram-toolbar {
            position: absolute;
            bottom: 24px;
            right: 24px;
            background: white;
            padding: 8px;
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 20;
            border: 1px solid #e2e8f0;
        }
        .tool-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: #475569;
            background: white;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.2s;
        }
        .tool-btn:hover { background-color: #f1f5f9; color: #0f172a; border-color: #cbd5e1; }

        /* Hata Kutusu */
        #error-overlay {
            position: absolute; top: 20px; left: 20px; right: 20px;
            background: #fee2e2; border: 1px solid #fecaca; color: #991b1b;
            padding: 15px; border-radius: 8px; z-index: 50; display: none;
            font-family: monospace; font-size: 12px; white-space: pre-wrap;
        }
    </style>

    <div class="mb-6 border-b border-slate-200 pb-5 flex justify-between items-center bg-white sticky top-0 z-10">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 flex items-center">
                    <i data-lucide="network" class="w-7 h-7 mr-3 text-indigo-600"></i>
                    Stage 6: ER Diagram
                </h1>

                <?php if ($isCompleted): ?>
                    <span class="bg-green-100 text-green-700 text-xs px-2.5 py-1 rounded-full font-bold border border-green-200 flex items-center">
                    <i data-lucide="check" class="w-3.5 h-3.5 mr-1.5"></i> Completed
                </span>
                <?php else: ?>
                    <span class="bg-amber-100 text-amber-700 text-xs px-2.5 py-1 rounded-full font-bold border border-amber-200 flex items-center">
                    <i data-lucide="clock" class="w-3.5 h-3.5 mr-1.5"></i> Pending
                </span>
                <?php endif; ?>
            </div>

            <p class="text-slate-500 text-sm mt-1">
                Crow's Foot Notation •
                <span class="text-indigo-600 font-bold">1:1</span> One-to-One •
                <span class="text-indigo-600 font-bold">1:N</span> One-to-Many •
                <span class="text-indigo-600 font-bold">M:N</span> Many-to-Many
            </p>
        </div>

        <div class="flex gap-3">
            <a href="normalize_3nf?id=<?php echo $project_id; ?>" class="btn-sm bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-50 flex items-center shadow-sm">
                <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Previous
            </a>

            <button onclick="generateRelationships()" class="btn-sm bg-white border border-slate-300 text-slate-600 hover:bg-slate-50 px-4 py-2 rounded-lg text-sm font-medium flex items-center shadow-sm transition">
                <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i> Re-Analyze
            </button>
            <?php if ($isCompleted): ?>

            <a href="/services/er/api/export_drawio.php?id=<?php echo $project_id; ?>" class="btn-sm bg-amber-50 border border-amber-200 text-amber-700 hover:bg-amber-100 px-4 py-2 rounded-lg text-sm font-bold flex items-center shadow-sm transition">
                <i data-lucide="file-up" class="w-4 h-4 mr-2"></i> Export Draw.io
            </a>
            <?php endif; ?>


            <a href="export_sql?id=<?php echo $project_id; ?>" class="btn-sm bg-slate-900 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 flex items-center shadow-lg transform hover:-translate-y-0.5 transition">
                Generate SQL <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
            </a>
        </div>
    </div>
    <div id="diagram-wrapper">
        <div id="error-overlay"></div>
        <div id="diagram-container">
            <div class="flex flex-col items-center justify-center h-full text-slate-400">
                <i data-lucide="loader-2" class="w-12 h-12 animate-spin mb-3 text-indigo-400"></i>
                <span class="text-sm font-medium">Rendering Schema...</span>
            </div>
        </div>
        <div class="diagram-toolbar">
            <button class="tool-btn" id="zoom-in" title="Zoom In"><i data-lucide="plus" class="w-5 h-5"></i></button>
            <button class="tool-btn" id="zoom-reset" title="Reset View"><i data-lucide="maximize" class="w-5 h-5"></i></button>
            <button class="tool-btn" id="zoom-out" title="Zoom Out"><i data-lucide="minus" class="w-5 h-5"></i></button>
        </div>
    </div>

    <div id="loadingOverlay" class="fixed inset-0 bg-white/90 z-50 hidden flex-col items-center justify-center backdrop-blur-sm">
        <div class="relative">
            <div class="w-20 h-20 border-4 border-indigo-100 rounded-full"></div>
            <div class="w-20 h-20 border-4 border-indigo-600 rounded-full animate-spin absolute top-0 left-0 border-t-transparent"></div>
        </div>
        <h3 class="text-xl font-bold text-slate-900 mt-6">Analyzing Schema...</h3>
        <p class="text-slate-500 text-sm mt-2">AI is identifying Foreign Keys & Cardinalities</p>
    </div>

    <textarea id="dot-debug" style="display:none;"></textarea>

    <script>
        let panZoomInstance = null;

        document.addEventListener('DOMContentLoaded', () => {
            fetchDataAndRender();
        });

        async function fetchDataAndRender() {
            try {
                const response = await fetch(`/services/er/api/get_schema.php?id=<?php echo $project_id; ?>`);
                const data = await response.json();

                if (!data.success) {
                    showError('API Error: ' + data.message);
                    return;
                }

                const dotString = generateDot(data.tables, data.relationships);
                document.getElementById('dot-debug').value = dotString;
                renderViz(dotString);

            } catch (err) {
                console.error(err);
                showError('Connection error: ' + err.message);
            }
        }

        function renderViz(dotString) {
            const viz = new Viz();

            viz.renderSVGElement(dotString, { engine: 'dot' })
                .then(element => {
                    const container = document.getElementById('diagram-container');
                    container.innerHTML = '';
                    element.id = 'er-svg';
                    element.style.width = "100%";
                    element.style.height = "100%";
                    container.appendChild(element);
                    initPanZoom();
                })
                .catch(error => {
                    console.error("Viz Error:", error);
                    showError('Render Error. Check console for DOT code.');
                });
        }

        // --- STRICT & SAFE DOT GENERATOR ---
        function generateDot(tables, relationships) {
            let dot = `digraph ER {
        graph [rankdir=LR, splines=polyline, nodesep=0.6, ranksep=1.0, fontname="Helvetica", bgcolor="transparent"];
        node [shape=none, margin=0, fontname="Helvetica"];
        edge [fontname="Helvetica", fontsize=9, color="#475569", penwidth=1.0, dir=both, arrowsize=0.8];
    `;

            // TABLOLAR
            for (const id in tables) {
                const t = tables[id];
                const safeId = "t_" + t.id;
                const tableName = escapeHtml(t.table_name.toUpperCase());

                // SAFE HTML TABLE
                let html = `
        <table border="0" cellborder="1" cellspacing="0" cellpadding="4">
            <tr>
                <td bgcolor="#312e81" colspan="4"><font color="white" point-size="14"><b>${tableName}</b></font></td>
            </tr>
        `;

                if (t.columns && t.columns.length > 0) {
                    t.columns.forEach((col) => {
                        const colName = escapeHtml(col.name);
                        const colType = escapeHtml(col.data_type.toUpperCase());

                        let icon = "&nbsp;";
                        let iconColor = "black";
                        let rowBg = "#ffffff";

                        if (col.is_primary_key == 1) {
                            icon = "PK"; iconColor = "#b45309"; rowBg = "#fffbeb";
                        } else if (col.is_foreign_key == 1) {
                            icon = "FK"; iconColor = "#1d4ed8"; rowBg = "#eff6ff";
                        }

                        // Badges (Veri get_schema.php'den gelmezse boş geçer)
                        let badges = [];
                        if (col.is_unique == 1) badges.push("UQ");
                        if (col.is_nullable == 0 && col.is_primary_key == 0) badges.push("NN");
                        let badgeStr = badges.length > 0 ? "[" + badges.join(",") + "]" : " ";

                        html += `
                <tr>
                    <td bgcolor="${rowBg}" align="left"><font color="${iconColor}" point-size="10"><b>${icon}</b></font></td>
                    <td bgcolor="${rowBg}" align="left"><font color="#1e293b">${colName}</font></td>
                    <td bgcolor="${rowBg}" align="left"><font color="#7e22ce" point-size="9">${badgeStr}</font></td>
                    <td bgcolor="${rowBg}" align="right"><font color="#64748b" point-size="10">${colType}</font></td>
                </tr>`;
                    });
                }

                html += `</table>`;
                dot += `"${safeId}" [label=<${html}>];\n`;
            }

            // İLİŞKİLER
            relationships.forEach(rel => {
                const parentId = "t_" + rel.parent_table_id;
                const childId = "t_" + rel.child_table_id;

                if (tables[rel.parent_table_id] && tables[rel.child_table_id]) {
                    let ah = "crow"; let at = "tee";
                    if (rel.cardinality === '1:1') { ah = "tee"; at = "tee"; }
                    else if (rel.cardinality === 'M:N') { ah = "crow"; at = "crow"; }

                    let lbl = rel.label ? escapeHtml(rel.label) : "";
                    if(lbl) lbl = `label=" ${lbl} "`;

                    dot += `"${parentId}" -> "${childId}" [arrowhead=${ah}, arrowtail=${at}, ${lbl}];\n`;
                }
            });

            dot += "}";
            return dot;
        }

        function escapeHtml(text) {
            if (!text) return "";
            return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        function initPanZoom() {
            if(panZoomInstance) panZoomInstance.destroy();
            panZoomInstance = svgPanZoom('#er-svg', { zoomEnabled: true, controlIconsEnabled: false, fit: true, center: true });
            document.getElementById('zoom-in').onclick = () => panZoomInstance.zoomIn();
            document.getElementById('zoom-out').onclick = () => panZoomInstance.zoomOut();
            document.getElementById('zoom-reset').onclick = () => { panZoomInstance.resetZoom(); panZoomInstance.center(); };
        }

        function showError(msg) {
            const el = document.getElementById('error-overlay');
            el.innerText = msg; el.style.display = 'block';
            document.getElementById('diagram-container').innerHTML = '<div class="p-10 text-red-600 font-mono">RENDER FAILED</div>';
        }

        function generateRelationships() {
            if(!confirm('Re-Analyze relationships?')) return;
            const overlay = document.getElementById('loadingOverlay');
            overlay.classList.remove('hidden'); overlay.classList.add('flex');
            const formData = new FormData(); formData.append('project_id', <?php echo $project_id; ?>);
            fetch('/services/ai/api/generate_relationships.php', { method: 'POST', body: formData })
                .then(r => r.json()).then(d => {
                if (d.success) window.location.reload();
                else { alert(d.message); overlay.classList.add('hidden'); overlay.classList.remove('flex'); }
            }).catch(e => { console.error(e); alert('Connection error.'); overlay.classList.add('hidden'); overlay.classList.remove('flex'); });
        }

    </script>

<?php require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/footer.php'; ?>