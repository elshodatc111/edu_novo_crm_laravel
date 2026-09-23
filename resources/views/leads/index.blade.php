@extends('layouts.app')
@section('title', 'Varonka')

@section('content')
    <x-page-header title="Varonka" subtitle="Murojaatlar: yangi → ko'rib chiqilmoqda → qabul qilindi">
        <x-slot:actions>
            @can('leads.manage')<a href="{{ route('leads.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Murojaat qo'shish</a>@endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Voronka --}}
    <div class="card card-body mb-6" x-data="{ view: 'funnel' }">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-ink-900 dark:text-white">Murojaatlar voronkasi</h3>
                <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">Murojaat → ko'rib chiqilgan → o'quvchi bo'lgan. Har bosqichda umumiy ulush va oldingi bosqichdan o'tish foizi.</p>
            </div>
            <div class="flex items-center gap-2">
                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <select name="days" class="input py-1.5 text-sm" onchange="this.form.submit()" aria-label="Davr">
                        <option value="0" @selected(! $days)>Hammasi</option>
                        @foreach ([30 => 'Oxirgi 30 kun', 90 => 'Oxirgi 90 kun', 365 => 'Oxirgi 1 yil'] as $d => $l)<option value="{{ $d }}" @selected($days === $d)>{{ $l }}</option>@endforeach
                    </select>
                </form>
                <div class="flex rounded-lg bg-ink-100 p-0.5 text-xs font-semibold dark:bg-ink-800" role="tablist">
                    <button type="button" @click="view = 'funnel'" :class="view === 'funnel' ? 'bg-white text-brand-700 shadow-sm dark:bg-ink-900 dark:text-brand-300' : 'text-ink-500'" class="rounded-md px-2.5 py-1">Voronka</button>
                    <button type="button" @click="view = 'table'" :class="view === 'table' ? 'bg-white text-brand-700 shadow-sm dark:bg-ink-900 dark:text-brand-300' : 'text-ink-500'" class="rounded-md px-2.5 py-1">Jadval</button>
                </div>
            </div>
        </div>
        <div x-show="view === 'funnel'" class="grid items-center gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                @if (($funnel[0]['value'] ?? 0) > 0)
                    <x-dash.funnel :stages="$funnel" />
                @else
                    <div class="flex h-48 items-center justify-center rounded-xl bg-ink-50 text-sm text-ink-400 dark:bg-ink-800/40">Bu davrda murojaat yo'q</div>
                @endif
            </div>
            <dl class="grid grid-cols-2 gap-3 text-sm lg:grid-cols-1">
                <div class="rounded-xl bg-ink-50 p-3 dark:bg-ink-800/40"><dt class="text-ink-500">Hali ko'rilmagan (yangi)</dt><dd class="text-xl font-bold text-ink-900 dark:text-white">{{ $funnelNew }}</dd></div>
                <div class="rounded-xl bg-ink-50 p-3 dark:bg-ink-800/40"><dt class="text-ink-500">Bekor qilingan</dt><dd class="text-xl font-bold text-ink-900 dark:text-white">{{ $funnelCancelled }}</dd></div>
            </dl>
        </div>
        <div x-show="view === 'table'" x-cloak>
            <div class="table-wrap rounded-xl border border-ink-100 dark:border-ink-800">
                <table class="table">
                    <thead><tr><th>Bosqich</th><th class="text-right">Soni</th><th class="text-right">Umumiy ulush</th><th class="text-right">Oldingi bosqichdan</th></tr></thead>
                    <tbody>
                    @foreach ($funnel as $i => $st)
                        <tr>
                            <td class="font-medium text-ink-900 dark:text-white">{{ $st['label'] }}</td>
                            <td class="text-right tabular-nums">{{ $st['value'] }}</td>
                            <td class="text-right tabular-nums">{{ $funnel[0]['value'] ? round($st['value'] * 100 / $funnel[0]['value']) . '%' : '—' }}</td>
                            <td class="text-right tabular-nums">{{ $i > 0 && $funnel[$i - 1]['value'] ? round($st['value'] * 100 / $funnel[$i - 1]['value']) . '%' : '—' }}</td>
                        </tr>
                    @endforeach
                    <tr class="text-ink-500"><td>Yangi (ko'rilmagan)</td><td class="text-right tabular-nums">{{ $funnelNew }}</td><td class="text-right tabular-nums">{{ $funnel[0]['value'] ? round($funnelNew * 100 / $funnel[0]['value']) . '%' : '—' }}</td><td></td></tr>
                    <tr class="text-ink-500"><td>Bekor qilingan</td><td class="text-right tabular-nums">{{ $funnelCancelled }}</td><td class="text-right tabular-nums">{{ $funnel[0]['value'] ? round($funnelCancelled * 100 / $funnel[0]['value']) . '%' : '—' }}</td><td></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach (\App\Models\Lead::statusLabels() as $k => $label)
            <a href="{{ route('leads.index', ['status' => $k]) }}" class="card card-body transition hover:border-brand-400 {{ $status === $k ? 'ring-2 ring-brand-500' : '' }}">
                <div class="text-sm text-ink-500">{{ $label }}</div>
                <div class="mt-1 text-2xl font-bold text-ink-900 dark:text-white">{{ $counts[$k] ?? 0 }}</div>
            </a>
        @endforeach
        <div class="card card-body"><div class="text-sm text-ink-500">Qabul foizi</div><div class="mt-1 text-2xl font-bold text-emerald-600">{{ $rate }}%</div><div class="text-xs text-ink-400">jami {{ $total }} murojaatdan</div></div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="card overflow-hidden lg:col-span-2">
            <form method="GET" class="grid gap-3 border-b border-ink-100 p-4 dark:border-ink-800 sm:grid-cols-3">
                <input type="hidden" name="status" value="{{ $status }}">
                <input name="q" value="{{ \App\Support\SafeInput::string(request('q')) }}" placeholder="Ism yoki telefon" class="input">
                <select name="source" class="input" onchange="this.form.submit()"><option value="">Barcha manbalar</option>
                    @foreach ($sources as $s)<option value="{{ $s->id }}" @selected(request('source') == $s->id)>{{ $s->name }}</option>@endforeach
                </select>
                <button class="btn-secondary">Qidirish</button>
            </form>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Ism</th><th>Telefon</th><th>Manba</th><th>AI</th><th>Sana</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($leads as $l)
                        <tr>
                            <td>
                                <a href="{{ route('leads.show', $l) }}" class="font-medium text-ink-900 hover:text-brand-600 dark:text-white">{{ $l->name }}</a>
                                @if ($l->is_repeat)<span class="badge-amber ml-1" title="Bu raqam o'quvchi sifatida ro'yxatda bor">Takror</span>@endif
                            </td>
                            <td><x-phone-copy :value="$l->phone" /></td>
                            <td>{{ $l->source?->name ?? '—' }}</td>
                            <td>@if ($l->ai_analysis)<span class="{{ ['yuqori' => 'badge-green', "o'rta" => 'badge-amber', 'past' => 'badge-gray'][$l->ai_analysis['priority']] ?? 'badge-gray' }}" title="Qabul ehtimoli">{{ $l->ai_analysis['priority'] }} · {{ $l->ai_analysis['probability'] }}%</span>@else<span class="text-ink-300">—</span>@endif</td>
                            <td class="whitespace-nowrap">{{ $l->created_at->format('d.m.Y H:i') }}</td>
                            <td class="text-right"><a href="{{ route('leads.show', $l) }}" class="btn-ghost btn-sm">Ochish</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-12 text-center text-ink-500">Murojaat yo'q.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $leads->links() }}
        </div>

        <div class="space-y-6">
            <div class="card card-body">
                <h3 class="mb-3 text-base font-semibold text-ink-900 dark:text-white">Manbalar bo'yicha</h3>
                <ul class="space-y-2 text-sm">
                    @forelse ($bySource as $r)
                        <li class="flex justify-between gap-2"><span>{{ $r->source }}</span><span class="font-semibold">{{ $r->total }} <span class="font-normal text-emerald-600">({{ (int) $r->converted }} qabul)</span></span></li>
                    @empty<li class="text-ink-500">Ma'lumot yo'q.</li>@endforelse
                </ul>
            </div>
            @if ($formBranch)
                @php $url = route('apply.show', $formBranch->code); $iframe = '<iframe src="'.route('apply.show', [$formBranch->code, 'embed' => 1]).'" width="100%" height="560" style="border:0" loading="lazy" title="Murojaat qoldirish"></iframe>'; @endphp
                <div class="card card-body space-y-3" x-data="{ copied: '' , copy(t, k) { navigator.clipboard.writeText(t); this.copied = k; setTimeout(() => this.copied = '', 1500) } }">
                    <h3 class="text-base font-semibold text-ink-900 dark:text-white">Murojaat formasi: {{ $formBranch->name }}</h3>
                    <p class="text-xs text-ink-500">Bu filial uchun forma. Murojaatlar avtomatik shu Varonkaga tushadi.</p>
                    <div><label class="label">Havola</label>
                        <div class="flex gap-2"><input readonly value="{{ $url }}" class="input text-xs" onclick="this.select()"><button type="button" class="btn-secondary btn-sm shrink-0" @click="copy(@js($url), 'url')" x-text="copied === 'url' ? 'Nusxalandi' : 'Nusxalash'"></button></div>
                        <a href="{{ $url }}" target="_blank" class="mt-1 inline-block text-xs font-semibold text-brand-600">Formani ochish</a></div>
                    <div><label class="label">Saytga qo'yish kodi (iframe)</label>
                        <textarea readonly rows="3" class="input text-xs" onclick="this.select()">{{ $iframe }}</textarea>
                        <button type="button" class="btn-secondary btn-sm mt-1" @click="copy(@js($iframe), 'code')" x-text="copied === 'code' ? 'Nusxalandi' : 'Kodni nusxalash'"></button></div>
                </div>
            @else
                <div class="card card-body text-sm text-ink-500">Murojaat formasi havolasi va saytga qo'yish kodi uchun yuqoridan <b>filialni tanlang</b>.</div>
            @endif
        </div>
    </div>
@endsection
