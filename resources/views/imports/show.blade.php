@extends('layouts.app')
@section('title', 'Import: '.$batch->filename)

@section('content')
    @php $s = $batch->summary; @endphp
    <x-page-header :title="$batch->filename" :subtitle="['pending' => 'Tekshiruv natijasi — tasdiqlashdan oldin ko\'rib chiqing', 'done' => 'Import bajarilgan', 'cancelled' => 'Bekor qilingan'][$batch->status]">
        <x-slot:actions><a href="{{ route('imports.index') }}" class="btn-secondary">Orqaga</a></x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([['Jami qator', $s['total'], ''], ["Qo'shiladi", $s['ok'], 'text-emerald-600'], ["O'tkazib yuboriladi (takror)", $s['duplicates'], 'text-amber-600'], ['Xato', $s['errors'], 'text-brand-600']] as [$l, $v, $c])
            <div class="card card-body"><div class="text-sm text-ink-500">{{ $l }}</div><div class="mt-1 text-2xl font-bold {{ $c ?: 'text-ink-900 dark:text-white' }}">{{ $v }}</div></div>
        @endforeach
    </div>

    @if (! empty($s['new_branches']))
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
            <b>Yangi filiallar ochiladi:</b>
            <ul class="mt-1 list-disc pl-5">@foreach ($s['new_branches'] as $name => $count)<li>Yangi filial: <b>{{ $name }}</b> — {{ $count }} ta o'quvchi</li>@endforeach</ul>
        </div>
    @endif

    @if (! empty($s['by_branch']))
        <div class="card card-body mt-4 text-sm">
            <b class="text-ink-900 dark:text-white">Filiallar bo'yicha:</b>
            <span class="text-ink-600 dark:text-ink-300">@foreach ($s['by_branch'] as $name => $count){{ $name }}: {{ $count }} ta @if (! $loop->last), @endif @endforeach</span>
            @if ($s['opening_balance'] !== 0)<div class="mt-1 text-ink-500">Boshlang'ich balanslar yig'indisi: {{ \App\Support\Format::money($s['opening_balance']) }}</div>@endif
        </div>
    @endif

    @if ($batch->status === 'pending')
        <div class="mt-4 flex gap-2">
            @if ($s['ok'] > 0)
                <form method="POST" action="{{ route('imports.confirm', $batch) }}" onsubmit="return confirm('{{ $s['ok'] }} ta o\'quvchi qo\'shilsinmi?{{ ! empty($s['new_branches']) ? ' Yangi filiallar ham ochiladi.' : '' }}')">@csrf<button class="btn-primary">Tasdiqlash va import qilish ({{ $s['ok'] }})</button></form>
            @endif
            <form method="POST" action="{{ route('imports.cancel', $batch) }}">@csrf<button class="btn-secondary">Bekor qilish</button></form>
        </div>
    @endif

    @if ($batch->status === 'done')
        <div class="mt-4">
            @if ($batch->result_path)
                <a href="{{ route('imports.result', $batch) }}" class="btn-primary">Login va parollar faylini yuklab olish</a>
                <p class="hint">Fayl faqat bir marta yuklab olinadi. Parollarni o'quvchilarga bering va faylni o'chiring.</p>
            @else
                <p class="text-sm text-ink-500">Login/parollar fayli yuklab olingan (yoki yaratilmagan).</p>
            @endif
        </div>
    @endif

    @if ($problems->isNotEmpty())
        <div class="card mt-6 overflow-hidden">
            <h2 class="px-5 pt-5 text-lg font-semibold text-ink-900 dark:text-white sm:px-6">Muammoli qatorlar</h2>
            <div class="table-wrap mt-3"><table class="table">
                <thead><tr><th>Qator</th><th>F.I.O</th><th>Telefon</th><th>Holat</th><th>Sabab</th></tr></thead>
                <tbody>@foreach ($problems as $r)
                    <tr><td>{{ $r['row'] }}</td><td>{{ $r['name'] }}</td><td>{{ $r['phone'] }}</td>
                        <td><span class="{{ $r['status'] === 'error' ? 'badge-red' : 'badge-amber' }}">{{ $r['status'] === 'error' ? 'Xato' : 'Takror' }}</span></td><td>{{ $r['message'] }}</td></tr>
                @endforeach</tbody></table></div>
        </div>
    @endif

    <div class="card mt-6 overflow-hidden">
        <h2 class="px-5 pt-5 text-lg font-semibold text-ink-900 dark:text-white sm:px-6">Ko'rib chiqish (dastlabki {{ count($preview) }} qator)</h2>
        <div class="table-wrap mt-3"><table class="table">
            <thead><tr><th>Qator</th><th>Filial</th><th>F.I.O</th><th>Telefon</th><th>Tug'ilgan sana</th><th>Manba</th><th class="text-right">Balans</th><th>Holat</th></tr></thead>
            <tbody>@foreach ($preview as $r)
                <tr><td>{{ $r['row'] }}</td><td>{{ $r['branch_name'] }}@if ($r['is_new_branch'])<span class="badge-amber ml-1">yangi</span>@endif</td><td>{{ $r['name'] }}</td><td>{{ $r['phone'] }}</td><td>{{ $r['birthday'] }}</td><td>{{ $r['source'] }}</td>
                    <td class="text-right">{{ number_format($r['balance'], 0, '', ' ') }}</td>
                    <td><span class="{{ ['ok' => 'badge-green', 'duplicate' => 'badge-amber', 'error' => 'badge-red'][$r['status']] }}">{{ ['ok' => 'OK', 'duplicate' => 'Takror', 'error' => 'Xato'][$r['status']] }}</span></td></tr>
            @endforeach</tbody></table></div>
    </div>
@endsection
