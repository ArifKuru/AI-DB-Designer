<?php
// /admin/settings/index.php
require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/header.php';

// Kullanıcı ID
$user_id = $_SESSION['user_id'];

// Mevcut ayarları çek (Sadece bu kullanıcıya ait)
try {
    $stmt = $db->prepare("SELECT * FROM settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $settings = [];
}
?>

    <div class="max-w-3xl mx-auto">

        <div class="mb-8">
            <h1 class="text-2xl font-bold text-slate-800">System Settings</h1>
            <p class="text-slate-500 mt-1">Configure your AI engine connection.</p>
        </div>

        <div id="alertBox" class="hidden mb-6 p-4 rounded-lg text-sm font-medium"></div>

        <div class="bg-white shadow-sm border border-slate-200 rounded-xl overflow-hidden">
            <form id="settingsForm" class="p-8 space-y-8">

                <div>
                    <label class="block text-sm font-semibold text-slate-900 mb-2">
                        Google Gemini API Key <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="password" name="gemini_api_key" id="apiKeyInput"
                               value="<?php echo htmlspecialchars($settings['gemini_api_key'] ?? ''); ?>"
                               class="w-full pl-10 pr-10 py-3 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition"
                               placeholder="AIzaSy...">

                        <i data-lucide="key" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>

                        <button type="button" onclick="toggleApiKey()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-indigo-600">
                            <i data-lucide="eye" id="eyeIcon" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <p class="text-xs text-slate-500 mt-2">
                        This key is used to connect to Google's Gemini AI models. It is stored securely linked to your account.
                    </p>
                </div>

                <div class="pt-4 border-t border-slate-100 flex justify-end">
                    <button type="submit" id="saveBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-lg text-sm font-bold shadow-md transition flex items-center">
                        <i data-lucide="save" class="w-4 h-4 mr-2"></i> Save Changes
                    </button>
                </div>

            </form>
        </div>
    </div>

    <script>
        // Şifre Göster/Gizle
        function toggleApiKey() {
            const input = document.getElementById('apiKeyInput');
            const icon = document.getElementById('eyeIcon');

            if (input.type === 'password') {
                input.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off'); // İkonu değiştir (gerekirse)
            } else {
                input.type = 'password';
                icon.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        }

        // Form Gönderimi (AJAX)
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const btn = document.getElementById('saveBtn');
            const originalBtnContent = btn.innerHTML;
            const alertBox = document.getElementById('alertBox');

            // Loading Durumu
            btn.disabled = true;
            btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 mr-2 animate-spin"></i> Saving...`;
            lucide.createIcons();

            const formData = new FormData(this);

            fetch('api/upsert.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    alertBox.classList.remove('hidden');

                    if (data.success) {
                        alertBox.className = 'mb-6 p-4 rounded-lg text-sm font-medium bg-green-50 text-green-800 border border-green-200 flex items-center';
                        alertBox.innerHTML = `<i data-lucide="check-circle" class="w-5 h-5 mr-2"></i> ${data.message}`;
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(error => {
                    alertBox.className = 'mb-6 p-4 rounded-lg text-sm font-medium bg-red-50 text-red-800 border border-red-200 flex items-center';
                    alertBox.innerHTML = `<i data-lucide="alert-circle" class="w-5 h-5 mr-2"></i> ${error.message}`;
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalBtnContent;
                    lucide.createIcons();

                    // 3 saniye sonra mesajı gizle
                    setTimeout(() => {
                        alertBox.classList.add('hidden');
                    }, 3000);
                });
        });
    </script>

<?php require_once $_SERVER["DOCUMENT_ROOT"].'/admin/includes/footer.php'; ?>