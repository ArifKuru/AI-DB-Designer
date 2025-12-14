<?php
require $_SERVER["DOCUMENT_ROOT"]."/includes/head.php";
// Session başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Eğer kullanıcı zaten giriş yapmışsa panele yönlendir
if (isset($_SESSION['user_id'])) {
    header("Location: /admin/projects");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | AI DB Architect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Background pattern matching login.php */
        .bg-pattern {
            background-color: #f8fafc;
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 32px 32px;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 h-screen flex items-center justify-center bg-pattern relative overflow-hidden">

<div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-indigo-200/40 rounded-full blur-[100px] -z-10 pointer-events-none"></div>

<a href="/" class="absolute top-6 left-6 flex items-center gap-2 text-slate-500 hover:text-indigo-600 transition font-medium text-sm bg-white/80 backdrop-blur px-4 py-2 rounded-full border border-slate-200 shadow-sm">
    <i data-lucide="arrow-left" class="w-4 h-4"></i> Home
</a>

<div class="w-full max-w-md bg-white/90 backdrop-blur-xl p-8 rounded-2xl shadow-2xl border border-white/50 relative z-10 mx-4">

    <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-12 h-12 bg-indigo-600 text-white rounded-xl shadow-lg shadow-indigo-200 mb-4">
            <i data-lucide="user-plus" class="w-6 h-6"></i>
        </div>
        <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Create Account</h2>
        <p class="text-slate-500 text-sm mt-1">Start designing databases with AI</p>
    </div>

    <div id="alertBox" class="hidden mb-4 p-3 bg-red-50 border border-red-100 text-red-600 text-sm rounded-lg flex items-center gap-2 animate-pulse">
        <i data-lucide="alert-circle" class="w-4 h-4"></i>
        <span id="alertMessage">An error occurred.</span>
    </div>

    <form id="registerForm" class="space-y-4">

        <div>
            <label for="username" class="block text-sm font-medium text-slate-700 mb-1">Username</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <i data-lucide="user" class="w-5 h-5"></i>
                </div>
                <input type="text" id="username" name="username" required
                       class="block w-full pl-10 pr-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition sm:text-sm"
                       placeholder="Choose a username">
            </div>
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email Address</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <i data-lucide="mail" class="w-5 h-5"></i>
                </div>
                <input type="email" id="email" name="email" required
                       class="block w-full pl-10 pr-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition sm:text-sm"
                       placeholder="you@example.com">
            </div>
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <i data-lucide="lock" class="w-5 h-5"></i>
                </div>
                <input type="password" id="password" name="password" required
                       class="block w-full pl-10 pr-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition sm:text-sm"
                       placeholder="••••••••">
            </div>
        </div>

        <button type="submit" id="registerBtn" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-lg shadow-indigo-200 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all transform active:scale-95 mt-2">
            Sign Up & Login
        </button>
    </form>

    <div class="relative my-6">
        <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-slate-200"></div>
        </div>
        <div class="relative flex justify-center text-sm">
            <span class="px-2 bg-white text-slate-500">Already have an account?</span>
        </div>
    </div>

    <a href="/login" class="w-full flex justify-center py-3 px-4 border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50 hover:text-indigo-600 hover:border-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all transform active:scale-95 shadow-sm">
        Sign In instead
    </a>

    <div class="mt-6 text-center">
        <p class="text-xs text-slate-400">
            &copy; <?php echo date('Y'); ?> Database Systems Project.
        </p>
    </div>
</div>

<script>
    // Initialize Icons
    lucide.createIcons();

    // Register Logic
    const registerForm = document.getElementById('registerForm');
    const registerBtn = document.getElementById('registerBtn');
    const alertBox = document.getElementById('alertBox');
    const alertMessage = document.getElementById('alertMessage');

    registerForm.addEventListener('submit', function (e) {
        e.preventDefault();

        // Collect Form Data
        const formData = new FormData(registerForm);

        // UI Loading State
        const originalBtnText = registerBtn.innerHTML;
        registerBtn.disabled = true;
        registerBtn.innerHTML = `<i data-lucide="loader-2" class="w-5 h-5 animate-spin mr-2"></i> Creating Account...`;
        lucide.createIcons();
        alertBox.classList.add('hidden');

        // API Request
        fetch('/auth/api/register.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Success State
                    registerBtn.innerHTML = `<i data-lucide="check" class="w-5 h-5 mr-2"></i> Created! Redirecting...`;
                    lucide.createIcons();

                    registerBtn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
                    registerBtn.classList.add('bg-green-600', 'hover:bg-green-700');

                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    // Error State
                    throw new Error(data.message || 'Unknown error occurred.');
                }
            })
            .catch(error => {
                // Reset UI & Show Error
                registerBtn.disabled = false;
                registerBtn.innerHTML = originalBtnText;

                alertMessage.innerText = error.message;
                alertBox.classList.remove('hidden');
            });
    });
</script>
</body>
</html>