<?php
// Header'ı kök dizinden çağırıyoruz
require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/header.php';
?>

    <div class="max-w-4xl mx-auto">

        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Create New Project</h1>
                <p class="text-slate-500 mt-1">Define your project requirements for the AI Architect.</p>
            </div>
            <a href="/admin/projects" class="text-slate-500 hover:text-slate-700 font-medium text-sm flex items-center">
                <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i> Back to Dashboard
            </a>
        </div>

        <div id="alertBox" class="hidden mb-6 p-4 rounded-lg text-sm font-medium"></div>

        <div class="bg-white shadow-sm border border-slate-200 rounded-xl overflow-hidden">
            <form id="createProjectForm" class="p-8">

                <div class="mb-8 border-b border-slate-100 pb-8">
                    <label for="name" class="block text-sm font-semibold text-slate-900 mb-2">Project Name <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" required placeholder="e.g. University Library System"
                           class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-2.5 px-4 text-slate-900 placeholder-slate-400 border">
                    <p class="mt-2 text-xs text-slate-500">Give your project a descriptive title to identify it later.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Domain <span class="text-red-500">*</span></label>
                        <input type="text" name="domain" required placeholder="e.g. Education, E-commerce"
                               class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 border py-2 px-3">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Primary Entity <span class="text-red-500">*</span></label>
                        <input type="text" name="primary_entity" required placeholder="e.g. Student, Product"
                               class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 border py-2 px-3">
                    </div>
                </div>

                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Constraints & Rules</label>
                        <textarea name="constraints" rows="3" placeholder="e.g. A student can borrow max 3 books. ISBN must be unique."
                                  class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 border py-2 px-3"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Advanced Features</label>
                        <textarea name="advanced_features" rows="2" placeholder="e.g. Search history tracking, overdue fine calculation."
                                  class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 border py-2 px-3"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Security / Access Control</label>
                        <textarea name="security_access" rows="2" placeholder="e.g. Admin can delete books, Students can only view."
                                  class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 border py-2 px-3"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Reporting Requirements</label>
                        <textarea name="reporting" rows="2" placeholder="e.g. Monthly most borrowed books list."
                                  class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 border py-2 px-3"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Common Tasks</label>
                        <textarea name="common_tasks" rows="2" placeholder="e.g. Add new book, Register new member."
                                  class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 border py-2 px-3"></textarea>
                    </div>
                </div>

                <div class="mt-8 pt-6 border-t border-slate-100 flex items-center justify-end gap-3">
                    <a href="/admin/projects" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">
                        Cancel
                    </a>
                    <button type="submit" id="submitBtn" class="px-6 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-200 transition-all flex items-center">
                        <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                        Create Project
                    </button>
                </div>

            </form>
        </div>
    </div>

    <script>
        document.getElementById('createProjectForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const form = this;
            const btn = document.getElementById('submitBtn');
            const originalBtnContent = btn.innerHTML;
            const alertBox = document.getElementById('alertBox');

            // UI Loading
            btn.disabled = true;
            btn.innerHTML = `<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Saving...`;

            alertBox.classList.add('hidden');
            alertBox.classList.remove('bg-red-50', 'text-red-800', 'bg-green-50', 'text-green-800');

            // API İsteği
            const formData = new FormData(form);

            fetch('/admin/projects/api/create.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    alertBox.classList.remove('hidden');
                    if (data.success) {
                        alertBox.classList.add('bg-green-50', 'text-green-800', 'border', 'border-green-200');
                        alertBox.innerHTML = `<div class="flex items-center"><i data-lucide="check-circle" class="w-5 h-5 mr-2"></i> ${data.message}</div>`;
                        lucide.createIcons();

                        // Yönlendirme
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1000);
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(error => {
                    alertBox.classList.add('bg-red-50', 'text-red-800', 'border', 'border-red-200');
                    alertBox.innerHTML = `<div class="flex items-center"><i data-lucide="alert-circle" class="w-5 h-5 mr-2"></i> ${error.message}</div>`;
                    lucide.createIcons();

                    btn.disabled = false;
                    btn.innerHTML = originalBtnContent;
                    // İkonları tekrar yükle çünkü buton HTML'i resetlendi
                    lucide.createIcons();
                });
        });
    </script>

<?php
require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/footer.php';
?>