<!DOCTYPE html>
<html lang="uz" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Murojaat') · {{ isset($branch) ? $branch->name : 'Edunova' }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if (isset($branch))
        <style>:root { --brand-public: {{ $branch->effectiveBrandColor() }}; }</style>
    @endif
</head>
<body class="min-h-full bg-ink-50">
<div class="mx-auto flex min-h-full max-w-lg flex-col justify-center px-4 py-10">
    @if (isset($branch))
        {{-- v9 (3-band): Edunova o'rniga shu filialning nomi, rangi va ma'lumoti --}}
        <div class="mb-6 flex flex-col items-center gap-3 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-2xl text-xl font-extrabold text-white" style="background-color: {{ $branch->effectiveBrandColor() }}">{{ mb_strtoupper(mb_substr($branch->name, 0, 1)) }}</span>
            <span class="text-xl font-extrabold tracking-tight text-ink-900">{{ $branch->name }}</span>
            @if ($branch->public_about)
                <p class="max-w-sm text-sm text-ink-500">{{ $branch->public_about }}</p>
            @endif
        </div>
    @else
        <div class="mb-6 flex items-center justify-center gap-3">
            <x-logo />
            <span class="text-xl font-extrabold tracking-tight text-ink-900">Edunova</span>
        </div>
    @endif
    @yield('content')
    <p class="mt-8 text-center text-xs text-ink-400">
        @if (isset($branch))
            © {{ date('Y') }} {{ $branch->name }}
        @else
            © {{ date('Y') }} Edunova CRM
        @endif
    </p>
</div>
</body>
</html>
