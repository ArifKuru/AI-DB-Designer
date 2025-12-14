<?php
// 1. Header ve Config
require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/header.php';

// 2. ID ve Yetki Kontrolü
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>window.location.href = '/admin/projects/';</script>";
    exit;
}

$project_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// 3. Mevcut Veriyi Çek
try {
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $project_id, ':user_id' => $user_id]);
    $project = $stmt->fetch();

    if (!$project) {
        echo "<div class='p-8 text-center text-red-600 font-bold'>Proje bulunamadı.</div>";
        require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/footer.php';
        exit;
    }

    // JSON description'ı array'e çevir (Formu doldurmak için)
    $details = json_decode($project['description'], true);

} catch (PDOException $e) {
    die("Hata: " . $e->getMessage());
}
?>

    <div class="max-w-4xl mx-auto">

        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Edit Project</h1>
                <p class="text-slate-500 mt-1">Update your project definition.</p>
            </div>
            <a href="/admin/projects/detail?id=<?php echo $project_id; ?>" class="text-slate-500 hover:text-slate-700 font-medium text-sm flex items-center">
                <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i> Back to Details
            </a>
        </div>

        <div id="alertBox" class="hidden mb-6 p-4 rounded-lg text-sm font-medium"></div>

        <div class="bg-white shadow-sm border border-slate-200 rounded-xl overflow-hidden">
            <form id="editProjectForm" class="p-8">

                <input type="hidden" name="id" value="<?php echo $project_id; ?>">

                <div class="mb-8 border-b border-slate-100 pb-8">
                    <label for="name" class="block text-sm font-semibold text-slate-900 mb-2">Project Name <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" required
                           value="<?php echo htmlspecialchars($project['name']); ?>"
                           class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-2.5 px-4 text-slate-900 border">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Domain <span class="text-red-500">*</span></label>
                        <input type="text" name="domain" required
                               value="<?php echo htmlspecialchars($details['domain'] ?? ''); ?>"
                               class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 border py-2 px-3">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Primary Entity <span class="text-red-500">*</span></label>
                        <input type="text" name="primary_entity" required
                               value="<?php echo htmlspecialchars($details['primary_entity'] ?? ''); ?>"
                               class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 border py-2 px-3">
                    </div>
                </div>

                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Constraints & Rules</label>
                        <textarea name="constraints" rows="3" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 border py-2 px-3"><?php echo htmlspecialchars($details['constraints'] ?? ''); ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Advanced Features</label>
                        <textarea name="advanced_features" rows="2" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 border py-2 px-3"><?php echo htmlspecialchars($details['advanced_features'] ?? ''); ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Security / Access Control</label>
                        <textarea name="security_access" rows="2" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 border py-2 px-3"><?php echo htmlspecialchars($details['security_access'] ?? ''); ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Reporting Requirements</label>
                        <textarea name="reporting" rows="2" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 border py-2 px-3"><?php echo htmlspecialchars($details['reporting'] ?? ''); ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Common Tasks</label>
                        <textarea name="common_tasks" rows="2" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 border py-2 px-3"><?php echo htmlspecialchars($details['common_tasks'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="mt-8 pt-6 border-t border-slate-100 flex items-center justify-end gap-3">
                    <a href="/admin/projects/detail?id=<?php echo $project_id; ?>" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">
                        Cancel
                    </a>
                    <button type="submit" id="updateBtn" class="px-6 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-200 transition-all flex items-center">
                        <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                        Update Project
                    </button>
                </div>

            </form>
        </div>
    </div>

    <script>
        document.getElementById('editProjectForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const form = this;
            const btn = document.getElementById('updateBtn');
            const originalBtnContent = btn.innerHTML;
            const alertBox = document.getElementById('alertBox');

            // UI Yükleniyor Durumu
            btn.disabled = true;
            btn.innerHTML = `<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Updating...`;

            alertBox.classList.add('hidden');

            const formData = new FormData(form);

            // API İsteği
            fetch('/admin/projects/api/update.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    alertBox.classList.remove('hidden');
                    if (data.success) {
                        alertBox.classList.remove('bg-red-50', 'text-red-800', 'border-red-200');
                        alertBox.classList.add('bg-green-50', 'text-green-800', 'border', 'border-green-200');
                        alertBox.innerHTML = `<div class="flex items-center"><i data-lucide="check-circle" class="w-5 h-5 mr-2"></i> ${data.message}</div>`;
                        lucide.createIcons();

                        // Yönlendirme
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 800);
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(error => {
                    alertBox.classList.remove('bg-green-50', 'text-green-800', 'border-green-200');
                    alertBox.classList.add('bg-red-50', 'text-red-800', 'border', 'border-red-200');
                    alertBox.innerHTML = `<div class="flex items-center"><i data-lucide="alert-circle" class="w-5 h-5 mr-2"></i> ${error.message}</div>`;
                    lucide.createIcons();

                    btn.disabled = false;
                    btn.innerHTML = originalBtnContent;
                    lucide.createIcons();
                });
        });
    </script>

<?php
require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/footer.php';
?>