@php
    $active = $memberships->where('is_active', true);
    $me = auth()->user();
    $tabs = array_filter([
        'pay' => $me->can('payments.create') ? "To'lov qabul qilish" : null,
        'discount' => $me->can('payments.discount') ? 'Chegirma' : null,
        'refund' => $me->can('payments.refund') ? "To'lovni qaytarish" : null,
        'special' => $me->isSuperAdmin() ? 'Maxsus chegirma' : null,
    ]);
    $first = array_key_first($tabs);
@endphp
<div class="card mt-6 overflow-hidden" x-data="{ tab: '{{ $errors->has('special_amount') || $errors->has('special_description') ? 'special' : ($errors->has('discount') || $errors->has('group_id') && old('amount') ? 'discount' : ($errors->has('amount') && old('method') ? 'refund' : $first)) }}' }">
    <div class="flex gap-1 overflow-x-auto border-b border-ink-100 p-2 dark:border-ink-800">
        @foreach ($tabs as $k => $label)
            <button type="button" @click="tab = '{{ $k }}'" :class="tab === '{{ $k }}' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/60 dark:text-brand-300' : 'text-ink-600 dark:text-ink-300'" class="whitespace-nowrap rounded-lg px-3.5 py-2 text-sm font-semibold">{{ $label }}</button>
        @endforeach
    </div>

    @if (isset($tabs['pay']))
        <div x-show="tab === 'pay'" x-data="confirmForm('To\'lovni tasdiqlang')">
        <form method="POST" action="{{ route('payments.store', $student) }}" class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
            @csrf
            <x-once />
            <x-input name="cash" money label="Naqt (so'm)" />
            <x-input name="card" money label="Plastik (so'm)" />
            <x-select name="group_id" label="Qaysi guruh uchun (ixtiyoriy)">
                <option value="">Umumiy to'lov</option>
                @foreach ($active as $m)
                    @php $g = $m->group; $early = $g->early_discount > 0 && app(\App\Services\DiscountWindow::class)->allows($g); @endphp
                    <option value="{{ $g->id }}" @selected(\App\Support\SafeInput::string(old('group_id')) === (string) $g->id)>{{ $g->name }} — {{ \App\Support\Format::money($g->price, false) }}@if ($early) · {{ \App\Support\Format::money($g->price - $g->early_discount, false) }} to'lansa {{ \App\Support\Format::money($g->early_discount, false) }} chegirma @endif</option>
                @endforeach
            </x-select>
            @if ($campaigns->isNotEmpty())
                <div class="rounded-xl bg-amber-50 p-3 text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-200 sm:col-span-2">
                    <b>Amaldagi aksiyalar</b> (shartga mos kelsa, to'lov paytida avtomatik qo'shiladi, o'quvchiga bir martadan):
                    @foreach ($campaigns as $c)<div>• {{ $c->name }} — {{ \App\Support\Format::money($c->amount, false) }} so'm to'lansa +{{ \App\Support\Format::money($c->bonus, false) }}</div>@endforeach
                </div>
            @endif
            <x-input name="description" label="Izoh" class="sm:col-span-2" />
            <div class="sm:col-span-2"><button type="button" @click="review($event)" class="btn-primary">To'lovni qabul qilish</button></div>
        </form>
        <x-money-confirm />
        </div>
    @endif

    @if (isset($tabs['discount']))
        <div x-show="tab === 'discount'" x-cloak x-data="confirmForm('Chegirmani tasdiqlang')">
        <form method="POST" action="{{ route('payments.discount', $student) }}" class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
            @csrf
            <x-once />
            @error('discount')<p class="error-text sm:col-span-2">{{ $message }}</p>@enderror
            <x-select name="group_id" label="Guruh" required>
                <option value="">Tanlang</option>
                @foreach ($active->filter(fn ($m) => $m->group->max_discount > 0) as $m)<option value="{{ $m->group_id }}">{{ $m->group->name }} (eng ko'pi {{ \App\Support\Format::money($m->group->max_discount, false) }})</option>@endforeach
            </x-select>
            <x-input name="amount" money label="Chegirma summasi (so'm)" required />
            <x-input name="description" label="Sabab" required class="sm:col-span-2" />
            <div class="sm:col-span-2"><button type="button" @click="review($event)" class="btn-primary">Chegirma berish</button></div>
        </form>
        <x-money-confirm />
        </div>
    @endif

    @if (isset($tabs['refund']))
        <div x-show="tab === 'refund'" x-cloak x-data="confirmForm('Qaytarishni tasdiqlang')">
        <form method="POST" action="{{ route('payments.refund', $student) }}" class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
            @csrf
            <x-once />
            <x-select name="method" label="Qaysi kassadan qaytariladi" required><option value="cash">Naqt</option><option value="card">Plastik</option></x-select>
            <x-input name="amount" money label="Summa (so'm)" required :hint="'Balansda: '.\App\Support\Format::money(max(0, $student->balance))" />
            <x-input name="description" label="Sabab" required class="sm:col-span-2" />
            <div class="sm:col-span-2"><button type="button" @click="review($event)" class="btn-primary">Qaytarish</button></div>
        </form>
        <x-money-confirm />
        </div>
    @endif
    @if (isset($tabs['special']))
        <div x-show="tab === 'special'" x-cloak>
        <form method="POST" action="{{ route('payments.special-discount', $student) }}" class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
            @csrf
            <div class="rounded-xl bg-amber-50 p-3 text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-200 sm:col-span-2">
                Bu chegirma guruhga bog'lanmaydi va narx rejasidagi chegara bilan cheklanmaydi: summa to'g'ridan-to'g'ri o'quvchi balansiga qo'shiladi (kassadan pul chiqmaydi, SMS yuborilmaydi).
                Bitta chegirma <b>{{ \App\Support\Format::money(\App\Services\PaymentService::MAX_SPECIAL_DISCOUNT) }}</b> dan oshmasligi kerak. Tasdiqlash uchun parolingiz so'raladi.
            </div>
            <x-input name="special_amount" money label="Chegirma summasi (so'm)" required />
            <x-input name="special_description" label="Sabab (masalan, kam ta'minlangan oila)" required />
            <div class="sm:col-span-2"><button class="btn-primary">Davom etish (tekshirish)</button></div>
        </form>
        </div>
    @endif
</div>
