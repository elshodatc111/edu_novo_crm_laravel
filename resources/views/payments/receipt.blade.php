<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chek №{{ $payment->id }} · Edunova</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css'])
    @if ($format === '80mm')
        <style>
            @page { size: 80mm auto; margin: 0; }
            body { width: 76mm; margin: 0 auto; font-size: 11px; }
            .receipt { padding: 3mm; }
        </style>
    @else
        <style>
            @page { size: A5; margin: 12mm; }
            body { font-size: 13px; }
            .receipt { max-width: 480px; margin: 0 auto; }
        </style>
    @endif
</head>
<body class="bg-white text-ink-900">
    <div class="flex justify-center gap-2 border-b border-ink-100 bg-ink-50 p-3 print:hidden">
        <a href="?format=a5" class="btn-sm {{ $format === 'a5' ? 'btn-primary' : 'btn-secondary' }}">A5</a>
        <a href="?format=80mm" class="btn-sm {{ $format === '80mm' ? 'btn-primary' : 'btn-secondary' }}">80 mm (termoprinter)</a>
        <button onclick="window.print()" class="btn-primary btn-sm">Chop etish</button>
    </div>

    <div class="receipt py-6 leading-snug">
        <div class="text-center">
            <div class="text-base font-extrabold">{{ $payment->branch?->name ?? '—' }}</div>
            @if ($payment->branch?->address)<div class="text-xs text-ink-500">{{ $payment->branch->address }}</div>@endif
            @if ($payment->branch?->phone)<div class="text-xs text-ink-500">{{ \App\Support\Format::prettyPhone($payment->branch->phone) }}</div>@endif
        </div>

        <div class="my-3 border-t border-dashed border-ink-300"></div>

        <div class="text-center text-sm font-bold uppercase">{{ $payment->typeLabel() }} CHEKI</div>
        <div class="text-center text-xs text-ink-500">№{{ $payment->id }} · {{ $payment->created_at->format('d.m.Y H:i') }}</div>

        <div class="my-3 border-t border-dashed border-ink-300"></div>

        <table class="w-full text-xs">
            <tr><td class="py-0.5 text-ink-500">O'quvchi</td><td class="py-0.5 text-right font-medium">{{ $payment->student?->name ?? '—' }}</td></tr>
            @if ($payment->student?->phone)<tr><td class="py-0.5 text-ink-500">Telefon</td><td class="py-0.5 text-right">{{ \App\Support\Format::prettyPhone($payment->student->phone) }}</td></tr>@endif
            @if ($payment->group)<tr><td class="py-0.5 text-ink-500">Guruh</td><td class="py-0.5 text-right">{{ $payment->group->name }}</td></tr>@endif
            @if ($payment->method)<tr><td class="py-0.5 text-ink-500">Usul</td><td class="py-0.5 text-right">{{ $payment->method->label() }}</td></tr>@endif
            @if ($payment->description)<tr><td class="py-0.5 text-ink-500">Izoh</td><td class="py-0.5 text-right">{{ $payment->description }}</td></tr>@endif
        </table>

        <div class="my-3 border-t border-dashed border-ink-300"></div>

        <div class="flex items-baseline justify-between">
            <span class="font-semibold">Summa</span>
            <span class="text-lg font-extrabold">{{ $payment->type === 'refund' ? '−' : '' }}{{ \App\Support\Format::money($payment->amount) }}</span>
        </div>
        @if ($payment->student)
            <div class="mt-1 flex items-baseline justify-between text-xs text-ink-500">
                <span>Joriy balans</span>
                <span class="font-medium {{ $payment->student->balance < 0 ? 'text-brand-600' : '' }}">{{ \App\Support\Format::money($payment->student->balance) }}</span>
            </div>
        @endif

        <div class="my-3 border-t border-dashed border-ink-300"></div>

        <div class="text-center text-xs text-ink-500">
            Kassir: {{ $payment->creator?->name ?? '—' }}
            @if ($payment->reversed_at)<div class="mt-1 font-semibold text-brand-600">STORNOLANGAN</div>@endif
        </div>
        <div class="mt-4 text-center text-xs text-ink-400">Xaridingiz uchun rahmat!</div>
    </div>

    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 200));</script>
</body>
</html>
