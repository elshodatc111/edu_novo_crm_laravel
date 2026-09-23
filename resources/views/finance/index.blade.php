@extends('layouts.app')
@section('title', 'Moliya')

@section('content')
    <x-page-header title="Moliya" subtitle="Filial balansi: kassadan tasdiqlangan chiqimlar shu yerga o'tadi" />

    @php $m = fn ($v) => \App\Support\Format::money($v); @endphp
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="card card-body"><div class="text-sm text-ink-500">Naqt balans</div><div class="mt-1 text-2xl font-bold text-ink-900 dark:text-white">{{ $m($balances['treasury_cash']) }}</div></div>
        <div class="card card-body"><div class="text-sm text-ink-500">Plastik balans</div><div class="mt-1 text-2xl font-bold text-ink-900 dark:text-white">{{ $m($balances['treasury_card']) }}</div></div>
        <div class="card card-body">
            <div class="text-sm text-ink-500">Ehson balansi (jami)</div>
            <div class="mt-1 text-2xl font-bold text-amber-600">{{ $m($charityTotal) }}</div>
            <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                <div class="rounded-lg bg-ink-50 px-3 py-2 dark:bg-ink-800/60"><div class="text-ink-500">Naqt ehson</div><div class="mt-0.5 text-sm font-semibold text-ink-900 dark:text-white">{{ $m($balances['treasury_charity_cash']) }}</div></div>
                <div class="rounded-lg bg-ink-50 px-3 py-2 dark:bg-ink-800/60"><div class="text-ink-500">Plastik ehson</div><div class="mt-0.5 text-sm font-semibold text-ink-900 dark:text-white">{{ $m($balances['treasury_charity_card']) }}</div></div>
            </div>
            @if ($balances['treasury_charity'] > 0)<div class="mt-2 text-xs text-brand-600">Taqsimlanmagan (eski) ehson: {{ $m($balances['treasury_charity']) }}</div>@endif
        </div>
    </div>

    @can('finance.manage')
        <p class="mt-6 rounded-xl bg-ink-100 px-4 py-3 text-sm text-ink-600 dark:bg-ink-800 dark:text-ink-300">Pul chiqadigan har bir amal (chiqim, xarajat, ehson chiqimi) avval <b>«Tekshiring»</b> sahifasida ko'rsatiladi va faqat siz tasdiqlagandan keyin bajariladi — xato bo'lsa, tuzatib qaytadan kiritishingiz mumkin.</p>

        {{-- 1-qator: chiqim, xarajat va shaxsiy kirim — teng kenglikda --}}
        <div class="mt-4 grid gap-6 lg:grid-cols-3">
            <form method="POST" action="{{ route('finance.withdraw') }}" class="card card-body space-y-4">
                @csrf
                <h2 class="text-base font-semibold text-ink-900 dark:text-white">Balansdan chiqim</h2>
                <x-select name="source" label="Qaysi balansdan" required>
                    <option value="cash" @selected(old('source') === 'cash')>Naqt</option><option value="card" @selected(old('source') === 'card')>Plastik</option>
                </x-select>
                <x-input name="amount" money label="Summa (so'm)" required />
                <x-input name="description" label="Izoh" required />
                <button class="btn-primary w-full">Tekshirishga o'tish</button>
            </form>

            <form method="POST" action="{{ route('finance.expense') }}" class="card card-body space-y-4">
                @csrf
                <h2 class="text-base font-semibold text-ink-900 dark:text-white">Xarajat</h2>
                <x-select name="method" label="Qaysi balansdan" required>
                    <option value="cash">Naqt</option><option value="card">Plastik</option>
                </x-select>
                @if ($expenseCategories->isNotEmpty())
                    <x-select name="category_id" label="Xarajat turi (ixtiyoriy)">
                        <option value="">Tanlanmagan</option>
                        @foreach ($expenseCategories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </x-select>
                @endif
                <x-input name="amount" money label="Summa (so'm)" required />
                <x-input name="description" label="Izoh" required />
                <button class="btn-primary w-full">Tekshirishga o'tish</button>
            </form>

            @can('finance.deposit')
                <form method="POST" action="{{ route('finance.deposit') }}" class="card card-body space-y-4">
                    @csrf
                    <x-once />
                    <h2 class="text-base font-semibold text-ink-900 dark:text-white">Shaxsiy mablag' kiritish</h2>
                    <p class="text-sm text-ink-500">Egasining shaxsiy mablag'i moliyaga kirim qilinadi — darhol yoziladi, ehson ajratilmaydi.</p>
                    <x-select name="method" label="Turi" required>
                        <option value="cash">Naqt</option><option value="card">Plastik</option>
                    </x-select>
                    <x-input name="amount" money label="Summa (so'm)" required />
                    <x-input name="description" label="Izoh (manba)" required />
                    <button class="btn-primary w-full">Kiritish</button>
                </form>
            @endcan
        </div>

        {{-- 2-qator: ehson chiqimi va ehson foizi — teng kenglikda --}}
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <form method="POST" action="{{ route('finance.charity-withdraw') }}" class="card card-body space-y-4">
                @csrf
                <h2 class="text-base font-semibold text-ink-900 dark:text-white">Ehson chiqimi</h2>
                <x-select name="method" label="Qaysi ehsondan" required>
                    <option value="cash">Naqt ehson ({{ $m($balances['treasury_charity_cash']) }})</option>
                    <option value="card">Plastik ehson ({{ $m($balances['treasury_charity_card']) }})</option>
                </x-select>
                <x-input name="amount" money label="Summa (so'm)" required />
                <x-input name="description" label="Izoh (kimga / nimaga)" required />
                <button class="btn-primary w-full">Tekshirishga o'tish</button>
            </form>

            <form method="POST" action="{{ route('finance.charity') }}" class="card card-body h-fit space-y-4">
                @csrf
                <h2 class="text-base font-semibold text-ink-900 dark:text-white">Ehson foizi</h2>
                <p class="text-sm text-ink-500">Kassadan moliyaga o'tkaziladigan har bir chiqimdan shu foiz ehson balansiga ajratiladi (naqt chiqimdan — naqt ehsonga, plastikdan — plastik ehsonga).</p>
                <x-input name="percent" type="number" step="0.01" min="0" max="100" label="Foiz (%)" :value="$percent" required />
                <button class="btn-secondary w-full">Saqlash</button>
            </form>
        </div>
    @endcan

    @if (! auth()->user()->can('finance.manage') && auth()->user()->can('finance.deposit'))
        <div class="mt-6 grid gap-6 sm:max-w-md">
            <form method="POST" action="{{ route('finance.deposit') }}" class="card card-body space-y-4">
                @csrf
                <x-once />
                <h2 class="text-base font-semibold text-ink-900 dark:text-white">Shaxsiy mablag' kiritish</h2>
                <p class="text-sm text-ink-500">Egasining shaxsiy mablag'i moliyaga kirim qilinadi — darhol yoziladi, ehson ajratilmaydi.</p>
                <x-select name="method" label="Turi" required>
                    <option value="cash">Naqt</option><option value="card">Plastik</option>
                </x-select>
                <x-input name="amount" money label="Summa (so'm)" required />
                <x-input name="description" label="Izoh (manba)" required />
                <button class="btn-primary w-full">Kiritish</button>
            </form>
        </div>
    @endif

    <div class="card mt-6 overflow-hidden">
        <form method="GET" class="flex flex-col gap-3 border-b border-ink-100 p-4 dark:border-ink-800 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-lg font-semibold text-ink-900 dark:text-white">Harakatlar (90 kun)</h2>
            <select name="wallet" class="input sm:w-56" onchange="this.form.submit()">
                <option value="">Barcha balanslar</option>
                @foreach (['treasury_cash' => 'Naqt', 'treasury_card' => 'Plastik', 'treasury_charity' => 'Ehson (hammasi)', 'treasury_charity_cash' => 'Ehson (naqt)', 'treasury_charity_card' => 'Ehson (plastik)'] as $k => $v)<option value="{{ $k }}" @selected(request('wallet') === $k)>{{ $v }}</option>@endforeach
            </select>
        </form>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Sana</th><th>Balans</th><th>Turi</th><th class="text-right">Summa</th><th class="text-right">Qoldiq</th><th>Izoh</th><th>Kim</th></tr></thead>
                <tbody>
                @forelse ($history as $t)
                    <tr>
                        <td class="whitespace-nowrap">{{ $t->created_at->format('d.m.Y H:i') }}</td>
                        <td>{{ $t->walletLabel() }}</td><td>{{ $t->typeLabel() }}</td>
                        <td class="text-right font-medium {{ $t->amount < 0 ? 'text-brand-600' : 'text-emerald-600' }}">{{ $t->amount > 0 ? '+' : '' }}{{ \App\Support\Format::money($t->amount, false) }}</td>
                        <td class="text-right">{{ \App\Support\Format::money($t->balance_after, false) }}</td>
                        <td class="max-w-xs truncate" title="{{ $t->description }}">{{ $t->description }}@if ($t->category) <span class="text-xs text-ink-400">· {{ $t->category->name }}</span>@endif</td>
                        <td>{{ $t->creator?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-ink-500">Harakat yo'q.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $history->links() }}
    </div>
@endsection
