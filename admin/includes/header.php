<?php
// 1. Session ve Güvenlik Kontrolü
$root = $_SERVER["DOCUMENT_ROOT"];
require_once $root.'/config/db.php';
require_once $root.'/config/session_control.php';

// Aktif sayfayı bul
$current_page = basename($_SERVER['PHP_SELF']);
$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User';
$email = isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : 'User Email';
$current_folder = basename(dirname($_SERVER['SCRIPT_NAME']));
$current_file = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<?php
require $_SERVER["DOCUMENT_ROOT"]."/includes/head.php";
?>
<body class="flex h-screen overflow-hidden">

<aside id="main-sidebar" class="w-64 bg-slate-900 text-white flex-shrink-0 hidden md:flex flex-col shadow-xl z-50 transition-all duration-300 sidebar_wrapper">

    <div class="p-6 flex items-center gap-3">
        <div class="h-8 w-8 bg-indigo-500 rounded-lg flex items-center justify-center">
            <i data-lucide="database" class="text-white w-5 h-5"></i>
        </div>
        <span class="font-bold text-lg tracking-wide">AI DB Architect</span>
    </div>

    <nav class="flex-1 px-4 space-y-2 mt-4 overflow-y-auto">
        <a href="/admin/projects"
           class="sidebar_link <?php echo ($current_folder === 'projects' && $current_file === 'index.php') ? 'sidebar_link_active' : 'text-slate-300'; ?>">
            <i data-lucide="layout-dashboard" class="w-5 h-5 mr-3"></i>
            <span class="font-medium">Projects</span>
        </a>

        <a href="/admin/projects/create"
           class="sidebar_link <?php echo ($current_folder === 'projects' && $current_file === 'create.php') ? 'sidebar_link_active' : 'text-slate-300'; ?>">
            <i data-lucide="plus-circle" class="w-5 h-5 mr-3"></i>
            <span class="font-medium">New Project</span>
        </a>
        <a href="/admin/settings/"
           class="sidebar_link <?php echo ($current_folder === 'settings' && $current_file === 'index.php') ? 'sidebar_link_active' : 'text-slate-300'; ?>">
            <i data-lucide="settings" class="w-5 h-5 mr-3"></i>
            <span class="font-medium">Settings</span>
        </a>
    </nav>

    <div class="sidebar_user_area bg-slate-800">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-indigo-500 flex items-center justify-center text-sm font-bold">
                <?php echo strtoupper(substr($username, 0, 2)); ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-white truncate">
                    <?php echo $username; ?>
                </p>
                <p class="text-xs text-slate-400 truncate">
                    <?php echo $email ?>
                </p>
            </div>
        </div>

        <a href="/logout" class="sidebar_link text-red-400 hover:text-red-300 hover:bg-red-900/20 justify-center border border-red-900/30">
            <i data-lucide="log-out" class="w-4 h-4 mr-2"></i>
            <span class="text-sm">Sign Out</span>
        </a>
    </div>

</aside>

<div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50">

    <header class="md:hidden bg-white shadow-sm p-4 flex items-center justify-between">
        <span class="font-bold text-slate-800">AI DB Architect</span>

        <button id="mobile-menu-btn" class="text-slate-600 focus:outline-none">
            <i data-lucide="menu" class="w-6 h-6"></i>
        </button>
    </header>

    <main class="flex-1 overflow-y-auto p-8">

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const menuBtn = document.getElementById('mobile-menu-btn');
                const sidebar = document.getElementById('main-sidebar');

                if (menuBtn && sidebar) {
                    menuBtn.addEventListener('click', function(e) {
                        e.stopPropagation(); // Tıklamanın body'ye yayılmasını engeller
                        sidebar.classList.toggle('hidden');

                        // Mobilde düzgün görünmesi için absolute pozisyon ekleyebiliriz
                        // Eğer sidebar normal akışta kalırsa içerik kayabilir,
                        // bu yüzden mobilde absolute yapmak iyi bir pratiktir:
                        sidebar.classList.toggle('absolute');
                        sidebar.classList.toggle('h-full');
                    });

                    // Sidebar açıkken dışarı tıklanırsa kapatsın
                    document.addEventListener('click', function(e) {
                        if (!sidebar.contains(e.target) && !menuBtn.contains(e.target) && !sidebar.classList.contains('hidden')) {
                            sidebar.classList.add('hidden');
                            sidebar.classList.remove('absolute', 'h-full');
                        }
                    });
                }
            });
        </script>