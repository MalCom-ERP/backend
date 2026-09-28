<!DOCTYPE html>
<html lang="fr" class="h-full bg-slate-950">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion SuperAdmin - MalCom</title>
    <link rel="icon" href="{{ asset('img/admin-pro-logo.svg') }}">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
    </style>
</head>

<body class="h-full flex items-center justify-center p-4 sm:p-6 lg:p-8 bg-slate-950 text-slate-900 relative overflow-hidden">
    <!-- Ambient mesh glow background -->
    <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-brand-600/20 blur-[150px] rounded-full pointer-events-none"></div>
    <div class="absolute bottom-10 left-10 w-96 h-96 bg-indigo-600/15 blur-[120px] rounded-full pointer-events-none"></div>

    <div class="relative w-full max-w-md">
        <!-- Floating Brand Card -->
        <div class="bg-white/95 backdrop-blur-2xl p-8 sm:p-10 rounded-[2.5rem] shadow-2xl border border-white/20">
            <!-- Brand Header -->
            <div class="text-center space-y-3 mb-8">
                <div class="inline-flex w-16 h-16 rounded-3xl bg-gradient-to-tr from-brand-600 via-indigo-600 to-indigo-500 items-center justify-center text-white text-3xl shadow-xl shadow-brand-500/30 mb-2">
                    <i class="bi bi-shield-shaded"></i>
                </div>
                <div>
                    <div class="flex items-center justify-center gap-2">
                        <h1 class="text-2xl font-black text-slate-900 tracking-tight">MalCom</h1>
                        <span class="px-2 py-0.5 rounded-full bg-brand-50 border border-brand-200 text-brand-700 text-[10px] font-black uppercase tracking-wider">PRO</span>
                    </div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Espace SuperAdmin Central</p>
                </div>
            </div>

            <!-- Flash alerts -->
            @if(session('success'))
                <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center gap-3">
                    <i class="bi bi-check-circle-fill text-lg text-emerald-600 flex-shrink-0"></i>
                    <p class="text-xs font-bold">{{ session('success') }}</p>
                </div>
            @endif

            @if(isset($errors) && $errors->any())
                <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800">
                    <div class="flex items-center gap-2 mb-1.5">
                        <i class="bi bi-exclamation-triangle-fill text-rose-600"></i>
                        <span class="text-xs font-black uppercase tracking-wider">Erreur d'authentification</span>
                    </div>
                    <ul class="text-xs font-semibold space-y-1 list-disc list-inside text-rose-700 pl-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Form -->
            <form class="space-y-5" action="{{ route('admin.login') }}" method="POST">
                @csrf
                <input type="hidden" name="remember" value="true">

                <div class="space-y-1.5">
                    <label for="email" class="text-[10px] font-black uppercase tracking-widest text-slate-400 pl-1">Adresse E-mail</label>
                    <div class="relative">
                        <i class="bi bi-envelope-fill absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}"
                            placeholder="admin@malcom.tech"
                            class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold text-slate-900 placeholder-slate-400 focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none transition-all">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="password" class="text-[10px] font-black uppercase tracking-widest text-slate-400 pl-1">Mot de Passe</label>
                    <div class="relative">
                        <i class="bi bi-lock-fill absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                            placeholder="••••••••"
                            class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold text-slate-900 placeholder-slate-400 focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none transition-all">
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input id="remember-me" name="remember-me" type="checkbox"
                            class="w-4 h-4 text-brand-600 rounded border-slate-300 focus:ring-brand-500">
                        <span class="text-xs font-bold text-slate-600">Se souvenir de moi</span>
                    </label>

                    <a href="{{ route('password.request') }}" class="font-bold text-brand-600 hover:text-brand-700 hover:underline">
                        Mot de passe oublié ?
                    </a>
                </div>

                <div class="pt-3">
                    <button type="submit"
                        class="w-full py-4 px-6 rounded-2xl bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-700 hover:to-indigo-700 text-white font-black text-xs uppercase tracking-wider shadow-xl shadow-brand-500/25 active:scale-95 transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <i class="bi bi-box-arrow-in-right text-base"></i>
                        <span>Accéder au Cockpit</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Footer note -->
        <p class="text-center text-[11px] font-bold text-slate-500 mt-6 tracking-wide">
            MalCom Suite Retail &bull; Système Multi-Boutiques UEMOA
        </p>
    </div>
</body>

</html>