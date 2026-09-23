<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shartnoma · Edunova</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A5; margin: 14mm; }
        body { font-size: 12.5px; }
        .contract { max-width: 560px; margin: 0 auto; white-space: pre-wrap; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; line-height: 1.5; }
    </style>
</head>
<body class="bg-white text-ink-900">
    <div class="flex justify-center gap-2 border-b border-ink-100 bg-ink-50 p-3 print:hidden">
        <button onclick="window.print()" class="btn-primary btn-sm">Chop etish</button>
    </div>

    <div class="contract py-6">{{ $text }}</div>

    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 200));</script>
</body>
</html>
