@extends('layouts.app')
@section('title', 'Kassa')

@section('content')
    <x-page-header title="Kassa" subtitle="Kunlik pul: talaba to'lovlari shu yerga tushadi" />

    @error('payment')
        <div class="mb-6 rounded-xl border border-brand-200 bg-brand-50 p-4 text-sm text-brand-800 dark:border-brand-900 dark:bg-brand-950/40 dark:text-brand-200">{{ $message }}</div>
    @enderror

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card card-body"><div class="text-sm text-ink-500">Kassada naqt</div><div class="mt-1 text-2xl font-bold text-ink-900 dark:text-white">{{ \App\Support\Format::money($cash) }}</div></div>
        <div class="card card-body"><div class="text-sm text-ink-500">Kassada plastik</div><div class="mt-1 text-2xl font-bold text-ink-900 dark:text-white">{{ \App\Support\Format::money($card) }}</div></div>
        <div class="card card-body"><div class="text-sm text-ink-500">Bugun tushgan (naqt)</div><div class="mt-1 text-xl font-bold text-emerald-600">{{ \App\Support\Format::money($today->cash) }}</div></div>
        <div class="card card-body"><div class="text-sm text-ink-500">Bugun tushgan (plastik)</div><div class="mt-1 text-xl font-bold text-emerald-600">{{ \App\Support\Format::money($today->card) }}</div></div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        @can('cashbox.request')
            <div x-data="confirmForm('So\'rovni tasdiqlang')" class="h-fit">
            <form method="POST" action="{{ route('cashbox.store') }}" x-data="{ kind: '{{ old('kind', 'expense') }}' }" class="card card-body space-y-4">
                @csrf
                <x-once />
                <h2 class="text-base font-semibold text-ink-900 dark:text-white">Chiqim yoki xarajat so'rovi</h2>
                <p class="text-sm text-ink-500">Pul kassadan darhol yechiladi. Admin tasdiqlaydi yoki bekor qiladi (pul qaytadi).</p>
                <x-select name="kind" label="Turi" required x-model="kind">
                    <option value="expense">Xarajat (sarflandi)</option>
                    <option value="withdrawal">Chiqim (moliya balansiga o'tkazish)</option>
                </x-select>
                <x-select name="method" label="Qaysi kassadan" required>
                    <option value="cash">Naqt</option><option value="card">Plastik</option>
                </x-select>
                <x-input name="amount" money label="Summa (so'm)" required />
                <x-input name="description" label="Izoh" required />
                @if ($expenseCategories->isNotEmpty())
                    <div x-show="kind === 'expense'" x-cloak>
                        <x-select name="category_id" label="Xarajat turi (ixtiyoriy)">
                            <option value="">— tanlanmagan —</option>
                            @foreach ($expenseCategories as $c)
                                <option value="{{ $c->id }}" @selected(old('category_id') == $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </x-select>
                    </div>
                @endif
                <button type="button" @click="review($event)" class="btn-primary w-full">So'rov yaratish</button>
            </form>
            <x-money-confirm />
            </div>
        @endcan

        <div class="space-y-6 {{ auth()->user()->can('cashbox.request') ? 'lg:col-span-2' : 'lg:col-span-3' }}">
            <div class="card overflow-hidden">
                <h2 class="px-5 pt-5 text-lg font-semibold text-ink-900 dark:text-white sm:px-6">Tasdiq kutayotgan so'rovlar</h2>
                <div class="table-wrap mt-3">
                    <table class="table">
                        <thead><tr><th>Sana</th><th>Turi</th><th class="text-right">Summa</th><th>Izoh</th><th>So'radi</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($pending as $r)
                            <tr>
                                <td class="whitespace-nowrap">{{ $r->created_at->format('d.m.Y H:i') }}</td>
                                <td>{{ $r->kindLabel() }} <span class="text-xs text-ink-400">{{ $r->method->label() }}{{ $r->category ? ' · '.$r->category->name : '' }}</span></td>
                                <td class="text-right font-semibold">{{ \App\Support\Format::money($r->amount, false) }}</td>
                                <td class="max-w-xs truncate" title="{{ $r->description }}">{{ $r->description }}</td>
                                <td>{{ $r->requester?->name }}</td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        @can('cashbox.approve')
                                            <form method="POST" action="{{ route('cashbox.approve', $r) }}">@csrf<button class="btn-primary btn-sm">Tasdiqlash</button></form>
                                        @endcan
                                        @if (auth()->user()->can('cashbox.approve') || (auth()->user()->can('cashbox.request') && $r->requested_by === auth()->id()))
                                            <form method="POST" action="{{ route('cashbox.cancel', $r) }}" onsubmit="return confirm('Bekor qilinsinmi? Pul kassaga qaytadi.')">@csrf<button class="btn-ghost btn-sm">Bekor</button></form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-ink-500">Kutayotgan so'rov yo'q.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($refunds->isNotEmpty())
                <div class="card overflow-hidden">
                    <h2 class="px-5 pt-5 text-lg font-semibold text-ink-900 dark:text-white sm:px-6">Tasdiqlanmagan qaytarishlar</h2>
                    <div class="table-wrap mt-3">
                        <table class="table">
                            <thead><tr><th>Sana</th><th>O'quvchi</th><th class="text-right">Summa</th><th>Sabab</th><th>Kassir</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($refunds as $p)
                                <tr>
                                    <td class="whitespace-nowrap">{{ $p->created_at->format('d.m.Y H:i') }}</td>
                                    <td>{{ $p->student->name }}</td>
                                    <td class="text-right font-semibold">{{ \App\Support\Format::money($p->amount, false) }} <span class="text-xs text-ink-400">{{ $p->method->label() }}</span></td>
                                    <td class="max-w-xs truncate" title="{{ $p->description }}">{{ $p->description }}</td><td>{{ $p->creator?->name }}</td>
                                    <td class="text-right">
                                        @can('cashbox.approve')
                                            <div class="flex justify-end gap-1">
                                                <form method="POST" action="{{ route('cashbox.refund-confirm', $p) }}">@csrf<button class="btn-secondary btn-sm">Tasdiqlash</button></form>
                                                <details class="text-left">
                                                    <summary class="btn-ghost btn-sm inline-block cursor-pointer list-none">Rad etish</summary>
                                                    <form method="POST" action="{{ route('cashbox.refund-reject', $p) }}" class="mt-2 w-64 space-y-2 rounded-xl border border-ink-200 bg-ink-50 p-3 dark:border-ink-700 dark:bg-ink-800/60">
                                                        @csrf
                                                        <label class="label text-xs" for="reject-reason-{{ $p->id }}">Rad etish sababi</label>
                                                        <input id="reject-reason-{{ $p->id }}" name="reason" type="text" required maxlength="255" placeholder="Nega rad etilmoqda?" class="input">
                                                        <button class="btn-primary btn-sm w-full">Rad etish va pulni qaytarish</button>
                                                    </form>
                                                </details>
                                            </div>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if ($history !== null)
            <div class="card overflow-hidden">
                <h2 class="px-5 pt-5 text-lg font-semibold text-ink-900 dark:text-white sm:px-6">So'nggi 30 kun</h2>
                <div class="table-wrap mt-3">
                    <table class="table">
                        <thead><tr><th>Ko'rib chiqildi</th><th>Turi</th><th class="text-right">Summa</th><th>Izoh</th><th>Holat</th><th>Kim</th></tr></thead>
                        <tbody>
                        @forelse ($history as $r)
                            <tr>
                                <td class="whitespace-nowrap">{{ $r->decided_at?->format('d.m.Y H:i') }}</td>
                                <td>{{ $r->kindLabel() }} <span class="text-xs text-ink-400">{{ $r->method->label() }}{{ $r->category ? ' · '.$r->category->name : '' }}</span></td>
                                <td class="text-right">{{ \App\Support\Format::money($r->amount, false) }}</td>
                                <td class="max-w-xs truncate" title="{{ $r->description }}">{{ $r->description }}</td>
                                <td><span class="{{ $r->status === 'approved' ? 'badge-green' : 'badge-gray' }}">{{ $r->status === 'approved' ? 'Tasdiqlangan' : 'Bekor qilingan' }}</span></td>
                                <td class="text-xs text-ink-500">{{ $r->requester?->name }} → {{ $r->decider?->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-ink-500">Yozuv yo'q.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            @if ($closings !== null)
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5 sm:px-6">
                    <h2 class="text-lg font-semibold text-ink-900 dark:text-white">Kassa smenasi</h2>
                    <details class="text-sm">
                        <summary class="btn-secondary btn-sm inline-block cursor-pointer list-none">Smenani yopish</summary>
                        <form method="POST" action="{{ route('cashbox.close') }}" onsubmit="return confirm('Smena yopilsinmi? Farq faqat yoziladi, kassa balansi o\'zgarmaydi.')"
                              class="mt-2 w-72 space-y-2 rounded-xl border border-ink-200 bg-ink-50 p-3 text-left dark:border-ink-700 dark:bg-ink-800/60">
                            @csrf
                            <x-once />
                            <p class="text-xs text-ink-500">Kutilgan (jurnal bo'yicha) naqt: <b>{{ \App\Support\Format::money($cash) }}</b></p>
                            <x-input name="actual_cash" money label="Sanalgan haqiqiy naqt" required />
                            <x-input name="note" label="Izoh (ixtiyoriy)" />
                            <button class="btn-primary btn-sm w-full">Yopish</button>
                        </form>
                    </details>
                </div>
                <p class="px-5 pb-2 text-xs text-ink-400 sm:px-6">Farq faqat shu ro'yxatga yoziladi - kassa balansi qo'lda o'zgartirilmaydi.</p>
                <div class="table-wrap mt-3">
                    <table class="table">
                        <thead><tr><th>Sana</th><th class="text-right">Kutilgan</th><th class="text-right">Haqiqiy</th><th class="text-right">Farq</th><th>Izoh</th><th>Kim</th></tr></thead>
                        <tbody>
                        @forelse ($closings as $c)
                            <tr>
                                <td class="whitespace-nowrap">{{ $c->created_at->format('d.m.Y H:i') }}</td>
                                <td class="text-right">{{ \App\Support\Format::money($c->expected_cash, false) }}</td>
                                <td class="text-right">{{ \App\Support\Format::money($c->actual_cash, false) }}</td>
                                <td class="text-right font-semibold {{ $c->difference === 0 ? '' : ($c->difference > 0 ? 'text-emerald-600' : 'text-brand-600') }}">
                                    {{ $c->difference > 0 ? '+' : '' }}{{ \App\Support\Format::money($c->difference, false) }}
                                </td>
                                <td class="max-w-xs truncate" title="{{ $c->note }}">{{ $c->note }}</td>
                                <td class="text-xs text-ink-500">{{ $c->closer?->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-ink-500">Hali smena yopilmagan.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>
    </div>
@endsection
