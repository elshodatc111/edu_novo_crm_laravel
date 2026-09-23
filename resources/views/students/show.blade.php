@extends('layouts.app')
@section('title', $student->name)

@section('content')
<div x-data="{ removing: null }">
    <x-page-header :title="$student->name" :subtitle="'Login: '.$student->username">
        <x-slot:actions>
            @can('students.update')
                <a href="{{ route('students.edit', $student) }}" class="btn-secondary"><x-icon name="edit" class="h-4 w-4" /> Tahrirlash</a>
                <form method="POST" action="{{ route('students.reset-password', $student) }}" onsubmit="return confirm('Yangi parol yaratilsinmi? Eski parol ishlamay qoladi.')">@csrf
                    <button class="btn-secondary"><x-icon name="key" class="h-4 w-4" /> Parolni yangilash</button>
                </form>
            @endcan
            @can('students.archive')
                @if ($student->archived_at)
                    <form method="POST" action="{{ route('students.restore', $student) }}">@csrf<button class="btn-secondary">Arxivdan qaytarish</button></form>
                @else
                    <form method="POST" action="{{ route('students.archive', $student) }}" onsubmit="return confirm('Arxivga o\'tkazilsinmi?')">@csrf<button class="btn-ghost">Arxivga</button></form>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    @error('payment')
        <div class="mb-6 rounded-xl border border-brand-200 bg-brand-50 p-4 text-sm text-brand-800 dark:border-brand-900 dark:bg-brand-950/40 dark:text-brand-200">{{ $message }}</div>
    @enderror

    @if (session('credentials'))
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
            <b>Mobil ilova uchun kirish ma'lumotlari</b> (parol faqat hozir ko'rsatiladi, uni o'quvchiga bering):
            <div class="mt-2 font-mono text-base">Login: <b>{{ session('credentials')['login'] }}</b> &nbsp; Parol: <b>{{ session('credentials')['password'] }}</b></div>
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        <div class="card card-body">
            <div class="text-sm text-ink-500">Balans</div>
            <div class="mt-1 text-2xl font-bold {{ $student->balance < 0 ? 'text-brand-600' : 'text-emerald-600' }}">{{ \App\Support\Format::money($student->balance) }}</div>
            <div class="mt-1 text-xs text-ink-400">{{ $student->balance < 0 ? 'Qarzdorlik' : "Mavjud mablag'" }}</div>
        </div>
        <div class="card card-body md:col-span-2">
            <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                <div><dt class="text-ink-500">Telefon</dt><dd class="font-medium">{{ $student->phone }}</dd></div>
                <div><dt class="text-ink-500">Qo'shimcha telefon</dt><dd class="font-medium">{{ $student->phone2 ?? '—' }}</dd></div>
                <div><dt class="text-ink-500">Tug'ilgan sana</dt><dd class="font-medium">{{ $student->birthday?->format('d.m.Y') ?? '—' }}</dd></div>
                <div><dt class="text-ink-500">Manba</dt><dd class="font-medium">{{ $student->leadSource?->name ?? '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-ink-500">Manzil</dt><dd class="font-medium">{{ $student->address ?? '—' }}</dd></div>
                @if ($student->about)<div class="sm:col-span-2"><dt class="text-ink-500">Eslatma</dt><dd>{{ $student->about }}</dd></div>@endif
            </dl>
        </div>
    </div>

    @if (! $student->archived_at && auth()->user()->canany(['payments.create', 'payments.discount', 'payments.refund']))
        @include('students._money')
    @endif

    <div class="card mt-6 overflow-hidden">
        <div class="flex flex-col gap-3 px-5 pt-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <h2 class="text-lg font-semibold text-ink-900 dark:text-white">Guruhlar</h2>
            @if ($availableGroups->isNotEmpty() && ! $student->archived_at && $student->balance < 0 && ! auth()->user()->can('groups.enroll_debtor'))
                <p class="rounded-xl bg-brand-50 px-3.5 py-2 text-sm text-brand-700 dark:bg-brand-950/40 dark:text-brand-300">Qarzi bor o'quvchini guruhga qo'shib bo'lmaydi. Avval to'lov qabul qiling.</p>
            @elseif ($availableGroups->isNotEmpty() && ! $student->archived_at)
                <form method="POST" action="#" class="flex flex-col gap-2 sm:flex-row sm:flex-wrap" x-data="{ g: '', allowDebt: false }" :action="g ? '{{ url('groups') }}/' + g + '/students' : '#'">
                    @csrf
                    <input type="hidden" name="student_id" value="{{ $student->id }}">
                    <select x-model="g" required class="input sm:w-64">
                        <option value="">Guruhga qo'shish...</option>
                        @foreach ($availableGroups as $g)
                            <option value="{{ $g->id }}">{{ $g->name }} — {{ \App\Support\Format::money($g->price) }} ({{ $g->starts_on->format('d.m') }}–{{ $g->ends_on->format('d.m.Y') }})</option>
                        @endforeach
                    </select>
                    <input name="note" placeholder="Izoh" class="input sm:w-48">
                    @if ($student->balance < 0)
                        <label class="flex w-full items-start gap-2 rounded-xl border border-brand-200 bg-brand-50 p-3 text-sm text-brand-800 dark:border-brand-900 dark:bg-brand-950/40 dark:text-brand-200">
                            <input type="checkbox" name="allow_debt" value="1" x-model="allowDebt" class="checkbox mt-0.5">
                            <span><b>Qarzga qo'shish (istisno).</b> O'quvchining qarzi {{ \App\Support\Format::money(-$student->balance) }}. Guruh narxi yechilgach qarz oshadi. Tasdiqlayman.</span>
                        </label>
                    @endif
                    <button class="btn-primary" @if ($student->balance < 0) :disabled="!allowDebt" @endif>Qo'shish</button>
                </form>
            @endif
        </div>
        <div class="table-wrap mt-3">
            <table class="table">
                <thead><tr><th>Guruh</th><th>O'qituvchi</th><th>Qo'shildi</th><th>Holat</th><th></th></tr></thead>
                <tbody>
                @forelse ($memberships as $m)
                    <tr>
                        <td><a href="{{ route('groups.show', $m->group) }}" class="font-medium text-ink-900 hover:text-brand-600 dark:text-white">{{ $m->group->name }}</a></td>
                        <td>{{ $m->group->teacher->name }}</td>
                        <td>{{ $m->created_at->format('d.m.Y') }}@if ($m->addedBy)<div class="text-xs text-ink-400">{{ $m->addedBy->name }}</div>@endif</td>
                        <td>
                            @if ($m->is_active)<span class="badge-green">Faol</span>
                            @else<span class="badge-gray">Chiqarilgan {{ $m->left_at?->format('d.m.Y') }}</span>@if ($m->fine > 0)<div class="text-xs text-brand-600">Jarima: {{ \App\Support\Format::money($m->fine) }}</div>@endif @endif
                        </td>
                        <td class="text-right">
                            @if ($m->is_active) @can('groups.members')
                                <button class="btn-ghost btn-sm" @click="removing = { id: {{ $m->group_id }}, name: @js($m->group->name), price: {{ $m->group->price }} }">Chiqarish</button>
                            @endcan @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-ink-500">Hali guruhga qo'shilmagan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Balans harakatlari va tarix — bitta jadvalda --}}
    <div class="card mt-6 overflow-hidden" x-data="{ f: 'all' }">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5 sm:px-6">
            <div>
                <h2 class="text-lg font-semibold text-ink-900 dark:text-white">Balans harakatlari va tarix</h2>
                <p class="text-xs text-ink-500">To'lovlar naqt yoki plastik ekani bilan; guruhga qo'shilish, chiqish va boshqa hodisalar ham shu yerda.</p>
            </div>
            <div class="flex rounded-lg bg-ink-100 p-0.5 text-xs font-semibold dark:bg-ink-800" role="tablist">
                @foreach (['all' => 'Hammasi', 'money' => 'Pul harakati', 'event' => 'Hodisalar'] as $k => $l)
                    <button type="button" @click="f = '{{ $k }}'" :class="f === '{{ $k }}' ? 'bg-white text-brand-700 shadow-sm dark:bg-ink-900 dark:text-brand-300' : 'text-ink-500'" class="rounded-md px-2.5 py-1">{{ $l }}</button>
                @endforeach
            </div>
        </div>
        <div class="table-wrap mt-3">
            <table class="table">
                <thead><tr><th>Sana</th><th>Turi</th><th>To'lov usuli</th><th class="text-right">Summa</th><th class="text-right">Qoldiq</th><th>Kim</th>@can('payments.reverse')<th>Storno</th>@endcan</tr></thead>
                <tbody>
                @forelse ($timeline as $r)
                    <tr x-show="f === 'all' || f === '{{ $r['kind'] }}'">
                        <td class="whitespace-nowrap">{{ $r['at']->format('d.m.Y H:i') }}</td>
                        <td>
                            @if ($r['kind'] === 'money')
                                <span class="badge-{{ ['green' => 'green', 'amber' => 'amber', 'red' => 'red'][$r['tone']] ?? 'gray' }}">{{ $r['label'] }}</span>
                                @if ($r['detail'])<div class="mt-0.5 text-xs text-ink-400">{{ $r['detail'] }}</div>@endif
                            @else
                                <span class="text-ink-700 dark:text-ink-200">{{ $r['label'] }}</span>
                            @endif
                        </td>
                        <td>
                            @if ($r['method'])<span class="{{ $r['method'] === 'Naqt' ? 'badge-green' : 'badge-blue' }}">{{ $r['method'] }}</span>@else<span class="text-ink-300">—</span>@endif
                        </td>
                        <td class="text-right font-medium tabular-nums {{ ($r['amount'] ?? 0) < 0 ? 'text-brand-600' : 'text-emerald-600' }}">
                            @if ($r['amount'] !== null){{ $r['amount'] > 0 ? '+' : '' }}{{ \App\Support\Format::money($r['amount'], false) }}@else<span class="text-ink-300">—</span>@endif
                        </td>
                        <td class="text-right tabular-nums">@if ($r['balance'] !== null){{ \App\Support\Format::money($r['balance'], false) }}@else<span class="text-ink-300">—</span>@endif</td>
                        <td class="text-xs text-ink-500">
                            {{ $r['by'] ?? '—' }}
                            @can('payments.view')
                                @if ($r['kind'] === 'money' && ($r['payment_id'] ?? null))
                                    <a href="{{ route('payments.receipt', $r['payment_id']) }}" target="_blank" class="ml-1 text-brand-600 hover:underline">Chek</a>
                                @endif
                            @endcan
                        </td>
                        @can('payments.reverse')
                            <td>
                                @if ($r['kind'] === 'money' && ($r['reversed'] ?? false))
                                    <span class="badge-gray text-xs">Stornolangan</span>
                                @elseif ($r['kind'] === 'money' && ($r['reversible'] ?? false))
                                    <details>
                                        <summary class="btn-ghost btn-sm inline-block cursor-pointer list-none">Storno</summary>
                                        <form method="POST" action="{{ route('payments.reverse', $r['payment_id']) }}" class="mt-2 w-64 space-y-2 rounded-xl border border-ink-200 bg-ink-50 p-3 dark:border-ink-700 dark:bg-ink-800/60">
                                            @csrf
                                            <label class="label text-xs" for="reverse-reason-{{ $r['payment_id'] }}">Sabab</label>
                                            <input id="reverse-reason-{{ $r['payment_id'] }}" name="reason" type="text" required maxlength="255" placeholder="Nega storno qilinmoqda?" class="input">
                                            <button class="btn-primary btn-sm w-full">Tekshiruvga o'tish</button>
                                        </form>
                                    </details>
                                @endif
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr><td colspan="{{ auth()->user()->can('payments.reverse') ? 7 : 6 }}" class="py-8 text-center text-ink-500">Harakat yo'q.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @can('students.notes')
    <div class="card card-body mt-6">
        <h2 class="mb-1 text-lg font-semibold text-ink-900 dark:text-white">Ichki eslatmalar</h2>
        <p class="mb-4 text-xs text-ink-500">Faqat xodimlarga ko'rinadi, o'quvchi ko'rmaydi. Yuqoridagi "Eslatma" maydonidan farqli — bir nechta yozuv, muallif va sana bilan saqlanadi.</p>
        <form method="POST" action="{{ route('students.notes.store', $student) }}" class="mb-5 flex flex-col gap-2 sm:flex-row">
            @csrf
            <textarea name="body" rows="2" maxlength="2000" placeholder="Eslatma matni..." class="input" required></textarea>
            <button class="btn-primary sm:self-start">Qo'shish</button>
        </form>
        @error('body')<p class="error-text -mt-3 mb-3">{{ $message }}</p>@enderror
        @if ($notes->isNotEmpty())
            <ol class="space-y-3 border-l border-ink-200 pl-4 dark:border-ink-700">
                @foreach ($notes as $n)
                    <li class="relative text-sm">
                        <span class="absolute -left-[21px] top-1.5 h-2.5 w-2.5 rounded-full bg-brand-500"></span>
                        <div class="flex items-start justify-between gap-3">
                            <div class="whitespace-pre-wrap text-ink-700 dark:text-ink-200">{{ $n->body }}</div>
                            <form method="POST" action="{{ route('students.notes.destroy', [$student, $n]) }}" onsubmit="return confirm('Eslatma o\'chirilsinmi?')">
                                @csrf @method('DELETE')
                                <button class="shrink-0 text-xs text-ink-400 hover:text-brand-600">O'chirish</button>
                            </form>
                        </div>
                        <div class="text-xs text-ink-400">{{ $n->created_at->format('d.m.Y H:i') }} · {{ $n->user?->name ?? '—' }}</div>
                    </li>
                @endforeach
            </ol>
        @else
            <p class="text-sm text-ink-500">Hali eslatma yo'q.</p>
        @endif
    </div>
    @endcan

    {{-- Guruhdan chiqarish oynasi --}}
    <div x-show="removing" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/60 p-4" @keydown.escape.window="removing = null">
        <div class="card card-body w-full max-w-md shadow-pop" @click.outside="removing = null">
            <h3 class="text-lg font-semibold text-ink-900 dark:text-white">Guruhdan chiqarish</h3>
            <p class="mt-2 text-sm text-ink-600 dark:text-ink-300"><b x-text="removing?.name"></b> guruhi narxi balansga qaytariladi, jarima kiritilsa balansdan yechiladi.</p>
            <form method="POST" :action="removing ? '{{ url('groups') }}/' + removing.id + '/students/{{ $student->id }}' : '#'" class="mt-4 space-y-4">
                @csrf @method('DELETE')
                <div>
                    <label class="label" for="fine">Jarima (so'm)</label>
                    <input id="fine" name="fine" type="text" inputmode="numeric" data-money value="0" class="input">
                </div>
                <div>
                    <label class="label" for="rnote">Sabab</label>
                    <input id="rnote" name="note" maxlength="500" class="input">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" @click="removing = null">Bekor qilish</button>
                    <button class="btn-primary">Chiqarish</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
