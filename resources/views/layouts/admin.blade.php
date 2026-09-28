<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ asset('img/admin-pro-logo.svg') }}">
    <title>MalCom - SuperAdmin PRO</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', '-apple-system', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            200: '#c7d2fe',
                            300: '#a5b4fc',
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#312e81',
                            950: '#1e1b4b',
                        },
                        primary: {
                            50: '#f5f7ff',
                            100: '#ebf0ff',
                            200: '#d9e1ff',
                            300: '#b8c6ff',
                            400: '#8ca3ff',
                            500: '#5c7aff',
                            600: '#4759ff',
                            700: '#333bff',
                            800: '#2a31d6',
                            900: '#242aab',
                            950: '#161975',
                        },
                        dark: {
                            900: '#0f172a',
                            950: '#0b0f19',
                        }
                    },
                    boxShadow: {
                        'glow-primary': '0 0 25px -5px rgba(79, 70, 229, 0.4)',
                        'glow-emerald': '0 0 25px -5px rgba(16, 185, 129, 0.4)',
                        'glow-amber': '0 0 25px -5px rgba(245, 158, 11, 0.4)',
                        'card-soft': '0 10px 30px -5px rgba(15, 23, 42, 0.05)',
                        'card-hover': '0 20px 40px -10px rgba(15, 23, 42, 0.12)',
                    },
                    borderRadius: {
                        '4xl': '2rem',
                    }
                }
            }
        }
    </script>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom Master Admin Styles -->
    <style>
        /* Base typography & smoothing */
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Glassmorphism card tokens */
        .glass-card {
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.04);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .glass-card-dark {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0b0f19 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 20px 50px -15px rgba(15, 23, 42, 0.4);
        }

        /* Stats Grid System */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 1.5rem;
        }

        @media (min-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        /* Stat Card */
        .stat-card {
            position: relative;
            background: #ffffff;
            border-radius: 1.75rem;
            padding: 1.75rem;
            border: 1px solid rgba(226, 232, 240, 0.85);
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.04);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 35px -10px rgba(15, 23, 42, 0.08);
        }

        .stat-card-indigo {
            background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
        }

        .stat-card-emerald {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
        }

        .stat-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 3.25rem;
            height: 3.25rem;
            border-radius: 1.25rem;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-label {
            font-size: 0.6875rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #64748b;
            display: block;
            margin-bottom: 0.5rem;
        }

        .stat-card-indigo .stat-label {
            color: rgba(255, 255, 255, 0.75);
        }

        .stat-value {
            display: flex;
            align-items: baseline;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .stat-value h2 {
            font-size: 2.25rem;
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: -0.03em;
            color: #0f172a;
        }

        .stat-card-indigo .stat-value h2 {
            color: #ffffff;
        }

        .currency {
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6366f1;
        }

        .stat-card-indigo .currency {
            color: rgba(255, 255, 255, 0.85);
        }

        /* Generic Table Wrapper */
        .card {
            background: #ffffff;
            border-radius: 1.75rem;
            border: 1px solid rgba(226, 232, 240, 0.85);
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.03);
            overflow: hidden;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .table-wrapper table {
            width: 100%;
            text-align: left;
            border-collapse: collapse;
        }

        .table-wrapper thead th {
            padding: 1rem 1.5rem;
            font-size: 0.6875rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #64748b;
            background: #f8fafc;
            border-bottom: 1px solid #edf2f7;
        }

        .table-wrapper tbody td {
            padding: 1.125rem 1.5rem;
            font-size: 0.875rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .table-wrapper tbody tr:last-child td {
            border-bottom: none;
        }

        /* Navigation Links */
        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            padding: 0.875rem 1.125rem;
            border-radius: 1.25rem;
            font-weight: 700;
            font-size: 0.875rem;
            color: #94a3b8;
            transition: all 0.25s ease;
            position: relative;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.06);
            color: #ffffff;
        }

        .nav-link.active {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            color: #ffffff;
            box-shadow: 0 8px 20px -4px rgba(79, 70, 229, 0.45);
        }

        .nav-link.active::before {
            content: '';
            position: absolute;
            left: -1rem;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 1.5rem;
            border-radius: 0 4px 4px 0;
            background: #818cf8;
        }

        /* Buttons */
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.625rem;
            padding: 0.75rem 1.5rem;
            border-radius: 1.125rem;
            font-weight: 800;
            font-size: 0.8125rem;
            letter-spacing: 0.02em;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            cursor: pointer;
        }

        .btn-action:active {
            transform: scale(0.97);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>

<body class="h-full flex overflow-hidden bg-slate-50 text-slate-900 selection:bg-brand-100 selection:text-brand-800">
    <!-- Mobile sidebar backdrop -->
    <div id="sidebar-backdrop"
        class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-md hidden lg:hidden transition-opacity"
        onclick="toggleSidebar()"></div>

    <!-- Sidebar SuperAdmin -->
    <aside id="sidebar"
        class="fixed inset-y-0 left-0 z-50 w-80 bg-slate-950 border-r border-white/5 flex flex-col transition-transform duration-300 transform -translate-x-full lg:relative lg:translate-x-0 shadow-2xl">
        <!-- Close button mobile -->
        <button onclick="toggleSidebar()" class="lg:hidden absolute top-6 right-6 w-9 h-9 rounded-xl bg-white/10 text-slate-300 hover:text-white flex items-center justify-center">
            <i class="bi bi-x-lg text-lg"></i>
        </button>

        <!-- Brand Identity -->
        <div class="px-8 pt-8 pb-6 flex items-center gap-4 border-b border-white/5">
            <div class="relative group">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-brand-600 via-indigo-500 to-indigo-400 flex items-center justify-center shadow-lg shadow-brand-500/30 group-hover:scale-105 transition-transform duration-300">
                    <i class="bi bi-shield-shaded text-2xl text-white"></i>
                </div>
                <div class="absolute -bottom-1 -right-1 w-3.5 h-3.5 bg-emerald-500 rounded-full border-2 border-slate-950"></div>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xl font-black text-white tracking-tight">MalCom</span>
                    <span class="px-2 py-0.5 rounded-full bg-brand-500/20 border border-brand-400/30 text-brand-300 text-[9px] font-black uppercase tracking-wider">PRO</span>
                </div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">SuperAdmin Central</p>
            </div>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 px-4 py-6 space-y-1.5 overflow-y-auto">
            <div class="px-4 mb-3 text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] flex items-center justify-between">
                <span>Navigation Principale</span>
                <span class="w-1.5 h-1.5 rounded-full bg-slate-600"></span>
            </div>

            <a href="{{ route('admin.dashboard') }}"
                class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <i class="bi bi-grid-1x2-fill text-lg"></i>
                <span>Vue d'ensemble</span>
            </a>

            <a href="{{ route('admin.boutiques.index') }}"
                class="nav-link {{ request()->routeIs('admin.boutiques.*') ? 'active' : '' }}">
                <i class="bi bi-shop-window text-lg"></i>
                <span>Réseau Boutiques</span>
                @php $activeCount = \App\Models\Boutique::where('is_active', 1)->count(); @endphp
                <span class="ml-auto text-[10px] font-black px-2 py-0.5 rounded-full {{ request()->routeIs('admin.boutiques.*') ? 'bg-white/20 text-white' : 'bg-white/5 text-slate-400' }}">
                    {{ $activeCount }}
                </span>
            </a>

            <a href="{{ route('admin.admins.index') }}"
                class="nav-link {{ request()->routeIs('admin.admins.*') ? 'active' : '' }}">
                <i class="bi bi-shield-lock-fill text-lg"></i>
                <span>Contrôle Accès</span>
            </a>

            <a href="{{ route('admin.licences.index') }}"
                class="nav-link {{ request()->routeIs('admin.licences.*') ? 'active' : '' }}">
                <i class="bi bi-key-fill text-lg"></i>
                <span>Licences & Abonnements</span>
            </a>

            <div class="pt-6 px-4 mb-2 text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">
                <span>Environnement</span>
            </div>

            <div class="px-4 py-3 rounded-2xl bg-white/[0.03] border border-white/5 space-y-1">
                <div class="flex items-center justify-between text-xs font-bold text-slate-300">
                    <span>Devise Système</span>
                    <span class="px-2 py-0.5 bg-brand-500/20 text-brand-300 rounded text-[10px] font-black">FCFA (UEMOA)</span>
                </div>
                <div class="flex items-center justify-between text-xs font-bold text-slate-300">
                    <span>Plan Comptable</span>
                    <span class="text-[10px] text-slate-400 font-semibold">SYSCOHADA</span>
                </div>
            </div>
        </nav>

        <!-- Current Admin Footer -->
        <div class="p-4 border-t border-white/5 mt-auto">
            <div class="bg-gradient-to-b from-white/[0.06] to-white/[0.02] rounded-3xl p-4 border border-white/10 shadow-inner">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-indigo-600 to-brand-500 flex items-center justify-center text-white font-black text-sm shadow-md">
                        {{ substr(auth()->user()->name ?? 'A', 0, 1) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <div class="text-sm font-black text-white truncate">{{ auth()->user()->name ?? 'Administrateur' }}</div>
                        </div>
                        <div class="flex items-center gap-1.5 text-[10px] text-brand-300 font-bold uppercase tracking-wider">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            {{ auth()->user()->role ?? 'Super Admin' }}
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}" id="logout-form" class="hidden">@csrf</form>
                <button onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                    class="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white text-xs font-black transition-all duration-200 cursor-pointer group">
                    <i class="bi bi-box-arrow-right group-hover:translate-x-0.5 transition-transform"></i>
                    <span>Déconnexion Session</span>
                </button>
            </div>
        </div>
    </aside>

    <!-- Main Workspace Area -->
    <main class="flex-1 flex flex-col min-w-0 overflow-hidden relative">
        <!-- Modern Frosted Topbar -->
        <header class="h-20 flex-shrink-0 flex items-center justify-between px-6 lg:px-10 bg-white/80 backdrop-blur-xl border-b border-slate-200/80 sticky top-0 z-30 shadow-sm">
            <div class="flex items-center gap-4">
                <!-- Mobile toggle button -->
                <button onclick="toggleSidebar()"
                    class="lg:hidden w-11 h-11 rounded-2xl bg-white border border-slate-200 text-slate-600 hover:text-brand-600 hover:border-brand-200 flex items-center justify-center shadow-sm transition-colors">
                    <i class="bi bi-list text-2xl"></i>
                </button>

                <!-- Breadcrumbs -->
                <div class="hidden sm:flex items-center gap-2.5 text-xs font-bold text-slate-400">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-1.5 hover:text-brand-600 transition-colors">
                        <i class="bi bi-house-door-fill text-slate-400"></i>
                        <span>MalCom</span>
                    </a>
                    <i class="bi bi-chevron-right text-[10px] opacity-40"></i>
                    <span class="text-slate-900 font-extrabold uppercase tracking-wider text-[11px] bg-slate-100 px-3 py-1 rounded-xl">
                        {{ str_replace('admin.', '', request()->route()?->getName() ?? 'Dashboard') }}
                    </span>
                </div>
            </div>

            <!-- Topbar Right Quick Controls -->
            <div class="flex items-center gap-3 lg:gap-4">
                <!-- Operational Badge -->
                <div class="hidden md:flex items-center gap-2 px-3.5 py-1.5 bg-emerald-50 border border-emerald-200/60 rounded-full text-emerald-700 text-xs font-bold shadow-sm">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span>Système Central Actif</span>
                </div>

                <!-- Fast Shop Selector / Link -->
                <a href="{{ route('admin.boutiques.index') }}"
                    class="hidden sm:flex items-center gap-2 px-4 py-2 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-all">
                    <i class="bi bi-shop text-brand-600"></i>
                    <span>Réseau</span>
                </a>

                <!-- Notification Bell -->
                <div class="relative">
                    <button class="w-11 h-11 rounded-2xl bg-white border border-slate-200 hover:border-brand-300 hover:bg-brand-50 flex items-center justify-center text-slate-600 hover:text-brand-600 transition-colors shadow-sm">
                        <i class="bi bi-bell-fill text-base"></i>
                        <span class="absolute top-2.5 right-2.5 w-2.5 h-2.5 bg-brand-600 border-2 border-white rounded-full"></span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Viewport Scrollable Area -->
        <section class="flex-1 overflow-y-auto p-6 lg:p-10 scroll-smooth">
            <!-- Flash Session Alerts -->
            @if (session('success'))
                <div class="mb-8 flex items-center gap-4 p-5 bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200/80 text-emerald-800 rounded-3xl shadow-sm animate-in fade-in slide-in-from-top-3 duration-300">
                    <div class="w-11 h-11 bg-emerald-500 text-white rounded-2xl flex items-center justify-center shadow-md shadow-emerald-500/20 flex-shrink-0">
                        <i class="bi bi-check2-circle text-2xl"></i>
                    </div>
                    <div>
                        <h4 class="font-extrabold text-sm uppercase tracking-wide text-emerald-900">Opération Réussie</h4>
                        <p class="font-semibold text-sm text-emerald-700 mt-0.5">{{ session('success') }}</p>
                    </div>
                </div>
            @endif

            @if (isset($errors) && $errors->any())
                <div class="mb-8 p-6 bg-gradient-to-r from-rose-50 to-red-50 border border-rose-200/80 text-rose-800 rounded-3xl shadow-sm animate-in fade-in slide-in-from-top-3 duration-300">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-9 h-9 bg-rose-500 text-white rounded-xl flex items-center justify-center shadow-md shadow-rose-500/20">
                            <i class="bi bi-exclamation-triangle-fill text-lg"></i>
                        </div>
                        <h4 class="font-black uppercase tracking-wider text-xs text-rose-900">Attention : Erreurs détectées</h4>
                    </div>
                    <ul class="space-y-1.5 list-disc list-inside font-semibold text-xs text-rose-700 pl-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Content Injected from Views -->
            @yield('content')
        </section>
    </main>

    <!-- Global Deletion Confirmation Modal -->
    <div id="global-delete-modal" class="fixed inset-0 z-[99999] hidden items-center justify-center p-4 bg-slate-950/75 backdrop-blur-md transition-all duration-200">
        <div class="relative w-full max-w-md bg-white rounded-3xl p-6 shadow-2xl border border-slate-200 text-center space-y-4 animate-in fade-in zoom-in duration-150">
            <div class="w-14 h-14 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center mx-auto text-2xl border border-rose-100">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <div>
                <h3 id="global-delete-title" class="text-base font-extrabold text-slate-900">Confirmer la suppression</h3>
                <p id="global-delete-message" class="text-xs text-slate-500 mt-1.5 leading-relaxed">
                    Cette action est irréversible. Voulez-vous vraiment continuer ?
                </p>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button type="button" onclick="closeGlobalDeleteModal()" class="flex-1 py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-colors">
                    Annuler
                </button>
                <button type="button" id="global-delete-confirm-btn" class="flex-1 py-2.5 px-4 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl shadow-lg shadow-rose-600/30 transition-all flex items-center justify-center gap-2">
                    <i class="bi bi-trash3-fill"></i>
                    <span>Supprimer Définitivement</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Global Scripts -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');
            sidebar.classList.toggle('-translate-x-full');
            backdrop.classList.toggle('hidden');
        }

        let formPendingSubmission = null;

        function triggerDeleteConfirm(event, title, message) {
            if (event) event.preventDefault();
            const form = event.target.closest('form');
            if (!form) return false;
            
            formPendingSubmission = form;
            document.getElementById('global-delete-title').textContent = title || 'Confirmer la suppression';
            document.getElementById('global-delete-message').textContent = message || 'Cette action est irréversible. Voulez-vous vraiment continuer ?';
            
            const modal = document.getElementById('global-delete-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            return false;
        }

        function closeGlobalDeleteModal() {
            const modal = document.getElementById('global-delete-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            formPendingSubmission = null;
        }

        document.getElementById('global-delete-confirm-btn')?.addEventListener('click', function() {
            if (formPendingSubmission) {
                formPendingSubmission.submit();
            }
            closeGlobalDeleteModal();
        });
    </script>
</body>

</html>
