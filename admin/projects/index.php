<?php
require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/header.php';
?>

    <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Dashboard</h1>
            <p class="text-slate-500 mt-1">Manage your database projects based on SDLC stages.</p>
        </div>

        <div class="flex gap-3">
            <a href="/admin/projects/create" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center transition shadow-sm">
                <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                New Project
            </a>
        </div>
    </div>

    <div class="mb-6 bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex flex-col sm:flex-row gap-4 items-center justify-between">

        <div class="relative w-full sm:w-64">
            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
            <input type="text" id="searchInput" placeholder="Search projects..."
                   class="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500 transition"
                   onkeyup="debounceSearch()">
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto">
            <span class="text-sm text-slate-500 font-medium">Status:</span>
            <select id="statusFilter" onchange="fetchProjects(1)" class="border border-slate-200 rounded-lg text-sm py-2 pl-3 pr-8 focus:outline-none focus:border-indigo-500 bg-white">
                <option value="all">All Projects</option>
                <option value="draft">Draft (Stage 1)</option>
                <option value="rules_extracted">Rules Extracted (Stage 2)</option>
                <option value="tables_created">Tables Ready (Stage 3)</option>
                <option value="audit_passed">Audit Passed (Stage 4)</option>
                <option value="normalized">Normalized (Stage 5)</option>
                <option value="diagram_generated">Diagram Ready (Stage 6)</option>
                <option value="completed">Completed (SQL Ready)</option>
            </select>
        </div>
    </div>

    <div id="projectsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <div class="col-span-full text-center py-12">
            <i data-lucide="loader-2" class="w-8 h-8 animate-spin mx-auto text-indigo-500 mb-2"></i>
            <p class="text-slate-500">Loading projects...</p>
        </div>
    </div>

    <div id="pagination" class="flex justify-center items-center gap-2"></div>

    <script>
        let searchTimeout;

        document.addEventListener('DOMContentLoaded', () => {
            fetchProjects();
        });

        function debounceSearch() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                fetchProjects(1);
            }, 300);
        }

        function fetchProjects(page = 1) {
            const search = document.getElementById('searchInput').value;
            const status = document.getElementById('statusFilter').value;
            const limit = 9;

            const grid = document.getElementById('projectsGrid');
            grid.innerHTML = '<div class="col-span-full text-center py-12"><i data-lucide="loader-2" class="w-8 h-8 animate-spin mx-auto text-indigo-500"></i></div>';
            lucide.createIcons();

            fetch(`/admin/projects/api/read.php?page=${page}&limit=${limit}&search=${encodeURIComponent(search)}&status=${status}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderProjects(data.data);
                        renderPagination(data.pagination);
                    } else {
                        grid.innerHTML = `<div class="col-span-full text-center text-red-500 py-10">${data.message}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    grid.innerHTML = `<div class="col-span-full text-center text-red-500 py-10">Connection error.</div>`;
                });
        }

        // STATÜ ROZETİ OLUŞTURUCU (RENK KODLARI)
        function getStatusBadge(status) {
            const configs = {
                'draft':             { label: 'Draft', color: 'bg-slate-100 text-slate-600 border-slate-200' },
                'rules_extracted':   { label: 'Rules Extracted', color: 'bg-amber-100 text-amber-700 border-amber-200' },
                'tables_created':    { label: 'Tables Ready', color: 'bg-blue-100 text-blue-700 border-blue-200' },
                'audit_passed':      { label: 'Audit Passed', color: 'bg-cyan-100 text-cyan-700 border-cyan-200' },
                'normalized':        { label: 'Normalized', color: 'bg-purple-100 text-purple-700 border-purple-200' },
                'diagram_generated': { label: 'Diagram Ready', color: 'bg-indigo-100 text-indigo-700 border-indigo-200' },
                'completed':         { label: 'Completed', color: 'bg-green-100 text-green-700 border-green-200' }
            };

            const config = configs[status] || configs['draft'];
            return `<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border uppercase tracking-wider ${config.color}">${config.label}</span>`;
        }

        function renderProjects(projects) {
            const grid = document.getElementById('projectsGrid');
            grid.innerHTML = '';

            if (projects.length === 0) {
                grid.innerHTML = `
                    <div class="col-span-full flex flex-col items-center justify-center text-center py-16 bg-slate-50 rounded-xl border border-dashed border-slate-300">
                        <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mb-4 shadow-sm">
                            <i data-lucide="folder-open" class="w-8 h-8 text-slate-300"></i>
                        </div>
                        <h3 class="text-lg font-medium text-slate-900">No projects found</h3>
                        <p class="text-slate-500 mt-1 max-w-sm">Try adjusting your search or filters to find what you're looking for.</p>
                    </div>
                `;
                lucide.createIcons();
                return;
            }

            projects.forEach(project => {
                let domain = 'Database Project';
                try {
                    const desc = JSON.parse(project.description);
                    if(desc.domain) domain = desc.domain;
                } catch(e) {}

                // Yeni Detail Sayfasına Link (detail.php)
                const detailLink = `/admin/projects/detail?id=${project.id}`;
                const badge = getStatusBadge(project.status);

                const card = `
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition group overflow-hidden flex flex-col">
                    <div class="p-5 flex-1">
                        <div class="flex justify-between items-start mb-3">
                            <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center flex-shrink-0 group-hover:bg-indigo-600 group-hover:text-white transition">
                                <i data-lucide="database" class="w-5 h-5"></i>
                            </div>
                            ${badge}
                        </div>

                        <h3 class="font-bold text-slate-900 text-lg mb-1 group-hover:text-indigo-600 transition">
                            <a href="${detailLink}">${project.name}</a>
                        </h3>
                        <p class="text-sm text-slate-500 flex items-center gap-2 mb-4">
                            <i data-lucide="folder" class="w-3 h-3"></i> ${domain}
                        </p>

                        <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                            <div class="bg-indigo-500 h-1.5 rounded-full" style="width: ${getProgressPercent(project.status)}%"></div>
                        </div>
                    </div>

                    <div class="bg-slate-50 px-5 py-3 border-t border-slate-100 flex justify-between items-center">
                        <span class="text-xs text-slate-400 font-mono">ID: ${project.id}</span>
                        <a href="${detailLink}" class="text-sm font-medium text-slate-600 hover:text-indigo-600 flex items-center transition">
                            Open Project <i data-lucide="arrow-right" class="w-4 h-4 ml-1"></i>
                        </a>
                    </div>
                </div>
                `;
                grid.innerHTML += card;
            });

            lucide.createIcons();
        }

        // Statüye göre tahmini yüzde (Visual Feedback)
        function getProgressPercent(status) {
            const map = {
                'draft': 10,
                'rules_extracted': 25,
                'tables_created': 40,
                'audit_passed': 55,
                'normalized': 70,
                'diagram_generated': 85,
                'completed': 100
            };
            return map[status] || 5;
        }

        function renderPagination(pagination) {
            const div = document.getElementById('pagination');
            div.innerHTML = '';

            if (pagination.total_pages <= 1) return;

            const prevDisabled = pagination.current_page == 1 ? 'opacity-50 pointer-events-none' : '';
            const nextDisabled = pagination.current_page == pagination.total_pages ? 'opacity-50 pointer-events-none' : '';

            div.innerHTML = `
                <button onclick="fetchProjects(${pagination.current_page - 1})" class="px-3 py-1 border rounded hover:bg-slate-50 bg-white text-sm ${prevDisabled}">Previous</button>
                <span class="px-3 py-1 text-sm text-slate-600">Page ${pagination.current_page} of ${pagination.total_pages}</span>
                <button onclick="fetchProjects(${pagination.current_page + 1})" class="px-3 py-1 border rounded hover:bg-slate-50 bg-white text-sm ${nextDisabled}">Next</button>
            `;
        }
    </script>

<?php require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/footer.php'; ?>