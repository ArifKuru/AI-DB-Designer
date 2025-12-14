<?php
require $_SERVER["DOCUMENT_ROOT"]."/includes/head.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Database Architect | Arif Kuru</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Elegant background pattern */
        .bg-pattern {
            background-color: #f8fafc;
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 32px 32px;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 h-screen flex flex-col overflow-hidden bg-pattern relative">

<div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[500px] bg-indigo-200/40 rounded-full blur-[120px] -z-10 pointer-events-none"></div>

<nav class="w-full py-6 px-8 flex justify-between items-center max-w-7xl mx-auto z-10">
    <div class="flex items-center gap-2">
        <div class="bg-indigo-600 text-white p-2 rounded-lg shadow-md">
            <i data-lucide="database" class="w-5 h-5"></i>
        </div>
        <span class="font-bold text-lg text-slate-800 tracking-tight">AI DB Architect</span>
    </div>
    <div>
            <span class="text-xs font-semibold text-slate-500 bg-white border border-slate-200 px-3 py-1 rounded-full shadow-sm">
                v1.0.0 Beta
            </span>
    </div>
</nav>

<main class="flex-1 flex flex-col items-center justify-center text-center px-6 relative z-10">

    <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 text-sm font-medium mb-8 shadow-sm animate-fade-in-up">
        <span class="w-2 h-2 rounded-full bg-indigo-600 animate-pulse"></span>
        2025-2026 Fall Semester • Database Systems Project
    </div>

    <h1 class="text-4xl md:text-6xl font-extrabold text-slate-900 tracking-tight mb-6 max-w-4xl leading-tight">
        AI-Powered <br>
        <span class="text-indigo-600">Database Architecture Design</span>
    </h1>

    <p class="text-lg text-slate-600 max-w-2xl mx-auto mb-10 leading-relaxed font-light">
        This project is developed to automate database design processes using natural language processing (NLP) technologies. It offers rule extraction, 3NF normalization, and SQL generation in a unified platform.
    </p>

    <div>
        <a href="/login" class="px-10 py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-semibold text-base transition-all shadow-lg shadow-indigo-200 flex items-center justify-center gap-2 transform hover:-translate-y-1">
            Login to System <i data-lucide="arrow-right" class="w-4 h-4"></i>
        </a>
    </div>

</main>

<footer class="py-6 border-t border-slate-200 bg-white/50 backdrop-blur-sm z-10">
    <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row justify-between items-center gap-4 text-sm text-slate-500">

        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-slate-100 rounded-full flex items-center justify-center border border-slate-200 font-bold text-indigo-600 text-xs">AK</div>
            <div class="flex flex-col text-left">
                <span class="font-bold text-slate-700">Arif Kuru</span>
                <span class="text-xs">Student ID: 2104010053</span>
            </div>
        </div>

        <div class="flex items-center gap-2 opacity-80">
            <i data-lucide="graduation-cap" class="w-4 h-4"></i>
            <span class="font-medium">Beykoz University</span>
        </div>

    </div>
</footer>

<script>
    lucide.createIcons();
</script>
</body>
</html>
