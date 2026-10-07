@extends('layouts.app')
@section('title', 'Statistika')

@section('content')
    <x-page-header title="Statistika" subtitle="Aniq ta'riflar bilan hisoblangan asosiy ko'rsatkichlar" />

    <form method="GET" class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div><label class="label">Boshlanishi</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="input"></div>
        <div><label class="label">Tugashi</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="input"></div>
        <button class="btn-secondary">Ko'rsatish</button>
        <div class="flex gap-1 sm:ml-2">
            <a class="btn-ghost btn-sm" href="{{ route('statistics.index', ['from' => today()->startOfMonth()->toDateString(), 'to' => today()->toDateString()]) }}">Shu oy</a>
            <a class="btn-ghost btn-sm" href="{{ route('statistics.index', ['from' => today()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'to' => today()->subMonthNoOverflow()->endOfMonth()->toDateString()]) }}">O'tgan oy</a>
            <a class="btn-ghost btn-sm" href="{{ route('statistics.index', ['from' => today()->startOfYear()->toDateString(), 'to' => today()->toDateString()]) }}">Shu yil</a>
        </div>
    </form>

    @php
        $m = fn ($v) => \App\Support\Format::money($v);
        $cards = [];
        if ($showMoney) {
            $cards[] = ['Sof tushum', $m($o['net_income']), "Tushum {$m($o['income'])} − qaytarilgan {$m($o['refunds'])}", 'text-emerald-600'];
            $cards[] = ['Naqt / Plastik', $m($o['income_cash']).' / '.$m($o['income_card']), $o['payments_count'].' ta to\'lov', ''];
            $cards[] = ['Chegirma va bonus', $m($o['discounts']), 'Balansga qo\'shilgan chegirmalar', 'text-amber-600'];
        }
        if ($showFinance) {
            $cards[] = ['Xarajat', $m($o['expenses']), 'Kassa va moliya xarajatlari', 'text-brand-600'];
            $cards[] = ['Ish haqi', $m($o['salaries']), "O'qituvchi va hodimlarga to'langan", 'text-brand-600'];
            $cards[] = ['Foyda (taxminiy)', $m($o['profit']), 'Sof tushum − xarajat − ish haqi', $o['profit'] >= 0 ? 'text-emerald-600' : 'text-brand-600'];
        }
        $cards[] = ["Yangi o'quvchilar", $o['new_students'], 'Davrda ro\'yxatga olingan', ''];
        $cards[] = ["Faol o'quvchilar", $o['active_students'], 'Hozir faol guruhda', ''];
        $cards[] = ['Faol guruhlar', $o['active_groups'], 'Hozir davom etayotgan', ''];
        $cards[] = ['Qarzdorlar', $o['debtors'], 'Umumiy qarz: '.$m($o['debt_total']), 'text-brand-600'];
        $cards[] = ['Murojaatlar', $o['new_leads'], 'Qabul qilingan: '.$o['converted_leads'].($o['lead_conversion'] !== null ? " ({$o['lead_conversion']}%)" : ''), ''];
        $cards[] = ['Davomad', $o['attendance_rate'] === null ? '—' : $o['attendance_rate'].'%', $o['attendance_records'].' ta yozuv asosida', ''];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($cards as [$label, $value, $note, $color])
            <div class="card card-body">
                <div class="text-sm text-ink-500">{{ $label }}</div>
                <div class="mt-1 text-2xl font-bold {{ $color ?: 'text-ink-900 dark:text-white' }}">{{ $value }}</div>
                <div class="mt-1 text-xs text-ink-400">{{ $note }}</div>
            </div>
        @endforeach
    </div>

    {{-- Dashboardlar: har birida "Grafik / Jadval" almashtirgichi bor --}}
    @if ($showMoney)
        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            @foreach (['today' => 'Bugun', 'week' => 'Shu hafta', 'month' => 'Shu oy'] as $k => $lbl)
                @php $sn = $snapshot[$k]; @endphp
                <div class="card card-body">
                    <div class="text-sm text-ink-500">{{ $lbl }} — sof tushum</div>
                    <div class="mt-1 text-2xl font-bold text-ink-900 dark:text-white">{{ $m($sn['net']) }}</div>
                    <div class="mt-1 text-xs text-ink-400">
                        {{ $sn['count'] }} ta to'lov · avvalgi shu muddat: {{ $m($sn['previous']) }}
                        @if ($sn['change'] !== null)
                            <span class="{{ $sn['change'] >= 0 ? 'text-emerald-600' : 'text-brand-600' }} font-semibold">{{ $sn['change'] > 0 ? '+' : '' }}{{ $sn['change'] }}%</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            <div class="mb-3 flex items-center gap-1 text-xs font-semibold">
                @foreach (['day' => 'Kunlar (30 kun)', 'week' => 'Haftalar (12 hafta)', 'month' => 'Oylar (12 oy)'] as $k => $lbl)
                    <a href="{{ request()->fullUrlWithQuery(['dyn' => $k]) }}" class="rounded-lg px-3 py-1.5 {{ $dyn === $k ? 'bg-brand-600 text-white' : 'bg-ink-100 text-ink-600 dark:bg-ink-800 dark:text-ink-300' }}">{{ $lbl }}</a>
                @endforeach
            </div>
            <x-dash.card title="Tushum dinamikasi (aniq)" :subtitle="'O\'rtacha sof tushum: '.$m($charts['dynamics_average']).' · davr tanlovidan mustaqil'" :spec="$charts['dynamics']" :table="$charts['dynamics_table']" />
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <x-dash.card class="lg:col-span-2" title="Tushum dinamikasi" :subtitle="($charts['income_unit'] === 'day' ? 'Kunlar' : 'Oylar').' bo\'yicha: naqt va plastik ('.$from->format('d.m.Y').' – '.$to->format('d.m.Y').')'" :spec="$charts['income']" />
            <x-dash.card title="To'lov usuli" subtitle="Tanlangan davr tushumi: naqt va plastik ulushi" :spec="$charts['method']" />
        </div>
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <x-dash.card title="Guruhlar bo'yicha tushum" subtitle="Guruh ko'rsatilgan to'lovlar (qaytarishlar ayirilgan), eng ko'pi yuqorida" :spec="$charts['top_groups']" />
            <x-dash.card :title="$showFinance ? 'Tushum, xarajat va ish haqi (12 oy)' : 'Oylik sof tushum (12 oy)'" subtitle="Oylar bo'yicha solishtirish" :spec="$charts['flow']" />
        </div>
    @endif
    @if ($showFinance)
        <div class="mt-6">
            <x-dash.card title="Foyda dinamikasi (12 oy)" subtitle="Sof tushum − xarajat − ish haqi (taxminiy; manfiy bo'lsa zarar)" :spec="$charts['profit']" height="h-64" />
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-dash.card class="lg:col-span-2" title="O'quvchi va murojaatlar (12 oy)" subtitle="Yangi o'quvchilar, kelgan murojaatlar va qabul qilinganlar" :spec="$charts['people']" />
        <div class="card card-body" x-data="{ view: 'funnel' }">
            @php
                $f = $charts['funnel'];
                $stages = [['label' => 'Murojaat', 'value' => $f['total']], ['label' => "Ko'rib chiqilgan", 'value' => $f['in_progress'] + $f['converted']], ['label' => 'Qabul qilingan', 'value' => $f['converted']]];
            @endphp
            <div class="mb-4 flex items-start justify-between gap-3">
                <div><h3 class="text-base font-semibold text-ink-900 dark:text-white">Varonka</h3><p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">Tanlangan davrdagi murojaatlar</p></div>
                <div class="flex shrink-0 rounded-lg bg-ink-100 p-0.5 text-xs font-semibold dark:bg-ink-800">
                    <button type="button" @click="view = 'funnel'" :class="view === 'funnel' ? 'bg-white text-brand-700 shadow-sm dark:bg-ink-900 dark:text-brand-300' : 'text-ink-500'" class="rounded-md px-2.5 py-1">Grafik</button>
                    <button type="button" @click="view = 'table'" :class="view === 'table' ? 'bg-white text-brand-700 shadow-sm dark:bg-ink-900 dark:text-brand-300' : 'text-ink-500'" class="rounded-md px-2.5 py-1">Jadval</button>
                </div>
            </div>
            <div x-show="view === 'funnel'">
                @if ($f['total'] > 0)<x-dash.funnel :stages="$stages" :height="60" :width="300" />@else<div class="flex h-56 items-center justify-center rounded-xl bg-ink-50 text-sm text-ink-400 dark:bg-ink-800/40">Bu davrda ma'lumot yo'q</div>@endif
            </div>
            <div x-show="view === 'table'" x-cloak class="table-wrap rounded-xl border border-ink-100 dark:border-ink-800">
                <table class="table"><thead><tr><th>Holat</th><th class="text-right">Soni</th></tr></thead><tbody>
                    <tr><td>Jami murojaat</td><td class="text-right tabular-nums">{{ $f['total'] }}</td></tr>
                    <tr><td>Yangi (ko'rilmagan)</td><td class="text-right tabular-nums">{{ $f['new'] }}</td></tr>
                    <tr><td>Ko'rib chiqilmoqda</td><td class="text-right tabular-nums">{{ $f['in_progress'] }}</td></tr>
                    <tr><td>Qabul qilingan</td><td class="text-right tabular-nums">{{ $f['converted'] }}</td></tr>
                    <tr><td>Bekor qilingan</td><td class="text-right tabular-nums">{{ $f['cancelled'] }}</td></tr>
                </tbody></table>
            </div>
            <a href="{{ route('leads.index') }}" class="mt-3 text-xs font-semibold text-brand-600 hover:underline">Varonka sahifasiga o'tish →</a>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-dash.card title="Kurslar bo'yicha faol o'quvchilar" subtitle="Hozir guruhlarda o'qiyotganlar (davrga bog'liq emas)" :spec="$charts['courses']" />
        <x-dash.card title="Qarzdorlar" subtitle="Qarz miqdori bo'yicha taqsimot (hozirgi holat)" :spec="$charts['debt']" :table="$charts['debt_table']" />
    </div>

    <div class="mt-6">
        <x-dash.card title="Davomad (6 oy)" subtitle="Oylar bo'yicha kelganlar ulushi" :spec="$charts['attendance']" height="h-60">
            <a href="{{ route('attendance.stats') }}" class="mt-3 text-xs font-semibold text-brand-600 hover:underline">Batafsil davomad statistikasi →</a>
        </x-dash.card>
    </div>

    @if ($comparison)
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            @isset($charts['branches'])<x-dash.card title="Filiallar: sof tushum" subtitle="Tanlangan davr" :spec="$charts['branches']" />@endisset
            @isset($charts['branch_students'])<x-dash.card title="Filiallar: o'quvchilar" subtitle="Faol va davrda yangi qo'shilgan" :spec="$charts['branch_students']" />@endisset
        </div>
        <div class="card mt-6 overflow-hidden">
            <h2 class="px-5 pt-5 text-lg font-semibold text-ink-900 dark:text-white sm:px-6">Filiallar solishtiruvi</h2>
            <div class="table-wrap mt-3"><table class="table">
                <thead><tr><th>Filial</th><th class="text-right">Sof tushum</th><th class="text-right">Yangi o'quvchi</th><th class="text-right">Faol o'quvchi</th><th class="text-right">Qarz</th></tr></thead>
                <tbody>
                @foreach ($comparison as $c)
                    <tr><td class="font-medium">{{ $c['branch'] }} @if ($c['archived'])<span class="badge-gray ml-1">arxiv</span>@endif</td>
                        <td class="text-right">{{ $m($c['net_income']) }}</td><td class="text-right">{{ $c['new_students'] }}</td><td class="text-right">{{ $c['students'] }}</td><td class="text-right text-brand-600">{{ $m($c['debt']) }}</td></tr>
                @endforeach
                </tbody></table></div>
        </div>
    @endif

    <div class="mt-6 space-y-1 rounded-xl bg-ink-100 p-4 text-xs leading-relaxed text-ink-600 dark:bg-ink-800 dark:text-ink-300">
        <b>Ta'riflar.</b>
        @if ($showMoney)<p>Tushum — «to'lov» turidagi yozuvlar (naqt + plastik). Sof tushum = tushum − qaytarilgan.</p>@endif
        @if ($showFinance)<p>Xarajat — tasdiqlangan kassa xarajatlari + moliya balansidan xarajatlar. Foyda pul harakati asosida hisoblanadi (o'quvchi qarzlari va hisoblangan, lekin to'lanmagan ish haqi kirmaydi).</p>@endif
        <p>Qarzdorlar va faol o'quvchilar — hozirgi holat, davrga bog'liq emas. Davomad — tanlangan davrdagi barcha yozuvlar bo'yicha kelganlar ulushi.</p>
    </div>
@push('scripts')@vite('resources/js/charts.js')@endpush
@endsection
