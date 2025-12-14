<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Database Architect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="icon" type="image/png" href="/public/images/db_favicon.png">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }

        /* USER REQUEST: Prefix Classes for Sidebar Isolation */
        .sidebar_wrapper { display: flex; flex-direction: column; height: 100vh; justify-content: space-between; }
        .sidebar_link { display: flex; align-items: center; padding: 0.75rem 1rem; border-radius: 0.5rem; transition: all 0.2s; }
        .sidebar_link:hover { background-color: rgba(255,255,255, 0.1); }
        .sidebar_link_active { background-color: #4f46e5; color: white; } /* Indigo-600 */
        .sidebar_user_area { border-top: 1px solid rgba(255,255,255,0.1); padding: 1rem; }
    </style>
</head>
