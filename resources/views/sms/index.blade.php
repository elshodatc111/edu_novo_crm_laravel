@extends('layouts.app')
@section('title', 'SMS')

@section('content')
<div x-data="{ tab: '{{ $errors->has('message') ? 'send' : (collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'templates.')) ? 'settings' : 'history') }}' }">
    <x-page-header title="SMS" subtitle="Eskiz orqali yuboriladigan xabarlar">
        @if ($branch)
            <x-slot:actions><span class="{{ $branch->sms_enabled ? 'badge-green' : 'badge-gray' }}">SMS {{ $branch->sms_enabled ? 'yoqilgan' : "o'chiq" }}</span></x-slot:actions>
        @endif
    </x-page-header>

    @if ($branch && ! $branch->sms_enabled)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
            Bu filialda SMS yuborish <b>o'chirilgan</b>. Yoqish uchun «Sozlamalar» bo'limida belgilang (Eskiz akkaunti sozlangan bo'lishi kerak).
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ([['Yuborildi (30 kun)', $stats['sent'] ?? 0, 'text-emerald-600'], ['Navbatda', $stats['queued'] ?? 0, 'text-amber-600'], ['Xato', $stats['failed'] ?? 0, 'text-brand-600']] as [$l, $v, $c])
            <div class="card card-body"><div class="text-sm text-ink-500">{{ $l }}</div><div class="mt-1 text-2xl font-bold {{ $c }}">{{ $v }}</div></div>
        @endforeach
    </div>

    <div class="mt-6 flex gap-1 overflow-x-auto rounded-xl bg-ink-100 p-1 dark:bg-ink-800">
        @foreach (array_filter(['history' => 'Tarix', 'send' => $canSend ? 'Ommaviy yuborish' : null, 'settings' => $canManage ? 'Sozlamalar' : null]) as $k => $label)
            <button type="button" @click="tab = '{{ $k }}'" :class="tab === '{{ $k }}' ? 'bg-white text-brand-700 shadow-sm dark:bg-ink-900 dark:text-brand-300' : 'text-ink-600 dark:text-ink-300'" class="whitespace-nowrap rounded-lg px-3.5 py-2 text-sm font-medium">{{ $label }}</button>
        @endforeach
    </div>

    <div x-show="tab === 'history'" class="card mt-4 overflow-hidden">
        <form method="GET" class="grid gap-3 border-b border-ink-100 p-4 dark:border-ink-800 sm:grid-cols-3">
            <input name="q" value="{{ \App\Support\SafeInput::string(request('q')) }}" placeholder="Telefon raqami" class="input">
            <select name="status" class="input" onchange="this.form.submit()"><option value="">Barcha holatlar</option>
                @foreach (['sent' => 'Yuborilgan', 'queued' => 'Navbatda', 'failed' => 'Xato'] as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
            </select>
            <button class="btn-secondary">Qidirish</button>
        </form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Sana</th><th>Qabul qiluvchi</th><th>Xabar</th><th>Holat</th></tr></thead>
            <tbody>
            @forelse ($messages as $m)
                <tr>
                    <td class="whitespace-nowrap">{{ $m->created_at->format('d.m.Y H:i') }}</td>
                    <td>{{ $m->recipient?->name ?? '—' }}<div class="text-xs text-ink-400">{{ \App\Support\Format::prettyPhone($m->phone) }}</div></td>
                    <td class="max-w-md text-sm">{{ $m->message }}</td>
                    <td><span class="{{ ['sent' => 'badge-green', 'queued' => 'badge-amber', 'failed' => 'badge-red'][$m->status] }}">{{ ['sent' => 'Yuborilgan', 'queued' => 'Navbatda', 'failed' => 'Xato'][$m->status] }}</span>
                        @if ($m->status === 'failed' && $m->provider_response)<div class="max-w-[14rem] truncate text-xs text-ink-400" title="{{ $m->provider_response }}">{{ $m->provider_response }}</div>@endif</td>
                </tr>
            @empty<tr><td colspan="4" class="py-10 text-center text-ink-500">Xabar yo'q.</td></tr>@endforelse
            </tbody></table></div>
        {{ $messages->links() }}
    </div>

    @if ($canSend)
        <form x-show="tab === 'send'" x-cloak method="POST" action="{{ route('sms.bulk') }}" class="card card-body mt-4 max-w-2xl space-y-4" x-data="{ audience: 'debtors' }">
            @csrf
            <h2 class="text-base font-semibold text-ink-900 dark:text-white">Ommaviy SMS</h2>
            <x-select name="audience" label="Kimga" required x-model="audience">
                <option value="debtors">Qarzdor o'quvchilarga</option><option value="group">Guruh o'quvchilariga</option><option value="all">Barcha faol o'quvchilarga</option>
            </x-select>
            <div x-show="audience === 'group'" x-cloak>
                <x-select name="group_id" label="Guruh"><option value="">Tanlang</option>@foreach ($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach</x-select>
            </div>
            <div>
                <label class="label" for="message">Xabar matni</label>
                <textarea id="message" name="message" rows="4" maxlength="500" class="input" required>{{ \App\Support\SafeInput::string(old('message'), $templates['debt_reminder']['body'] ?? '', 500) }}</textarea>
                @error('message')<p class="error-text">{{ $message }}</p>@enderror
                <p class="hint">O'zgaruvchilar: {{ implode('  ', array_keys(\App\Support\SmsTemplates::PLACEHOLDERS)) }} — har bir qabul qiluvchi uchun almashtiriladi. Bir xil raqamga bir marta yuboriladi.</p>
            </div>
            <button class="btn-primary">Tekshirishga o'tish</button>
        </form>
    @endif

    @if ($canManage)
        <form x-show="tab === 'settings'" x-cloak method="POST" action="{{ route('sms.settings') }}" class="mt-4 space-y-4">
            @csrf @method('PUT')
            <div class="card card-body">
                <label class="flex items-center gap-3 text-sm font-medium"><input type="checkbox" name="sms_enabled" value="1" class="checkbox" @checked($branch->sms_enabled)> Bu filialda SMS yuborishni yoqish</label>
                <p class="hint">Eskiz akkaunti: {{ $branch->hasOwnSmsAccount() ? "filialning o'z akkaunti" : 'umumiy akkaunt (.env)' }}. Filial akkauntini sAdmin «Filiallar» bo'limida kiritadi.</p>
            </div>
            @foreach ($templates as $key => $t)
                <div class="card card-body space-y-2">
                    <label class="flex items-center gap-3 text-sm font-semibold"><input type="checkbox" name="templates[{{ $key }}][enabled]" value="1" class="checkbox" @checked($t['enabled'])> {{ $t['label'] }}</label>
                    <textarea name="templates[{{ $key }}][body]" rows="2" maxlength="500" class="input" required>{{ $t['body'] }}</textarea>
                    @error("templates.$key.body")<p class="error-text">{{ $message }}</p>@enderror
                    @if ($key === 'debt_reminder')
                        <label class="flex items-center gap-3 border-t border-ink-100 pt-2 text-sm text-ink-600 dark:border-ink-800 dark:text-ink-300"><input type="checkbox" name="sms_auto_debt" value="1" class="checkbox" @checked($branch->sms_auto_debt)> Har kuni avtomatik yuborish (soat 09:15, kuniga bir marta)</label>
                        <p class="hint">Ishlashi uchun shablon ham yoqilgan bo'lishi kerak (yuqoridagi katakcha).</p>
                    @elseif ($key === 'absence_notice')
                        <label class="flex items-center gap-3 border-t border-ink-100 pt-2 text-sm text-ink-600 dark:border-ink-800 dark:text-ink-300"><input type="checkbox" name="sms_auto_absent" value="1" class="checkbox" @checked($branch->sms_auto_absent)> Davomad olinganda avtomatik yuborish (o'quvchiga emas, «Qo'shimcha telefon» — ota-ona raqamiga)</label>
                        <p class="hint">Ishlashi uchun shablon ham yoqilgan bo'lishi kerak; o'quvchida qo'shimcha telefon ko'rsatilmagan bo'lsa, xabar yuborilmaydi.</p>
                    @endif
                </div>
            @endforeach
            <p class="hint">O'zgaruvchilar: {{ implode('  ', array_keys(\App\Support\SmsTemplates::PLACEHOLDERS)) }}. Eskiz shablonlarni oldindan tasdiqlaydi — matnni Eskiz'da ruxsat etilgan ko'rinishda yozing.</p>
            <button class="btn-primary">Saqlash</button>
        </form>
    @endif
</div>
@endsection
