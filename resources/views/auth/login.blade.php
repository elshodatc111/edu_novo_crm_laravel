<!DOCTYPE html>
<html lang="uz" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kirish · Edunova CRM</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script>try{if(localStorage.getItem('theme')==='dark'||(!localStorage.getItem('theme')&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark')}}catch(e){}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
<div class="grid min-h-full lg:grid-cols-2">
    <div class="relative hidden overflow-hidden bg-brand-600 lg:flex lg:flex-col lg:justify-between lg:p-12">
        <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full bg-brand-500/60"></div>
        <div class="absolute -bottom-32 -left-16 h-[28rem] w-[28rem] rounded-full bg-brand-700/60"></div>
        <div class="relative flex items-center gap-3 text-white">
            <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-white text-xl font-black text-brand-600">E</span>
            <span class="text-2xl font-extrabold tracking-tight">Edunova</span>
        </div>
        <div class="relative max-w-md text-white">
            <h2 class="text-4xl font-extrabold leading-tight tracking-tight">O'quv markazingizni bitta tizimdan boshqaring.</h2>
            <p class="mt-4 text-lg text-brand-100">Filiallar, o'quvchilar, davomad, to'lovlar va hisobotlar — barchasi bir joyda.</p>
        </div>
        <p class="relative text-sm text-brand-200">© {{ date('Y') }} Edunova CRM</p>
    </div>

    <div class="flex items-center justify-center bg-white p-6 dark:bg-ink-950 sm:p-12">
        <div class="w-full max-w-sm">
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <x-logo />
                <span class="text-xl font-extrabold tracking-tight text-ink-900 dark:text-white">Edunova</span>
            </div>

            <h1 class="text-2xl font-bold tracking-tight text-ink-900 dark:text-white">Tizimga kirish</h1>
            <p class="mt-1.5 text-sm text-ink-500 dark:text-ink-400">Login va parolingizni kiriting.</p>

            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
                @csrf
                <x-input name="login" label="Login yoki email" autocomplete="username" required autofocus />
                <x-input name="password" type="password" label="Parol" autocomplete="current-password" required />

                <label class="flex items-center gap-2 text-sm text-ink-600 dark:text-ink-300">
                    <input type="checkbox" name="remember" value="1" class="checkbox"> Meni eslab qol
                </label>

                <button type="submit" class="btn-primary w-full py-3">Kirish</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
