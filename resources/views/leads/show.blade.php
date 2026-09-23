@extends('layouts.app')
@section('title', $lead->name)

@section('content')
    <x-page-header :title="$lead->name" :subtitle="\App\Support\Format::prettyPhone($lead->phone)">
        <x-slot:actions>
            <span class="{{ ['new' => 'badge-blue', 'in_progress' => 'badge-amber', 'converted' => 'badge-green', 'cancelled' => 'badge-gray'][$lead->status] }}">{{ $lead->status_label }}</span>
            <a href="{{ route('leads.index') }}" class="btn-secondary">Orqaga</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            @php $ai = $lead->ai_analysis; @endphp
            <div class="card card-body space-y-3">
                <div class="flex items-center justify-between"><h3 class="text-base font-semibold text-ink-900 dark:text-white">AI fikri</h3>
                    @can('leads.manage')<form method="POST" action="{{ route('leads.analyze', $lead) }}">@csrf<button class="btn-ghost btn-sm">{{ $ai ? 'Qayta tahlil' : 'Tahlil qilish' }}</button></form>@endcan</div>
                @error('ai')<p class="error-text">{{ $message }}</p>@enderror
                @if ($ai)
                    <div class="flex items-center gap-2"><span class="{{ ['yuqori' => 'badge-green', "o'rta" => 'badge-amber', 'past' => 'badge-gray'][$ai['priority']] ?? 'badge-gray' }}">Ustuvorlik: {{ $ai['priority'] }}</span><span class="text-sm font-semibold">{{ $ai['probability'] }}% qabul ehtimoli</span></div>
                    <div class="h-2 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full bg-brand-500" style="width: {{ $ai['probability'] }}%"></div></div>
                    <p class="text-sm">{{ $ai['summary'] }}</p>
                    @if ($ai['next_step'])<p class="text-sm"><b>Keyingi qadam:</b> {{ $ai['next_step'] }}</p>@endif
                    @if ($ai['talking_points'])<ul class="list-disc space-y-1 pl-5 text-sm">@foreach ($ai['talking_points'] as $t)<li>{{ $t }}</li>@endforeach</ul>@endif
                    @if ($ai['risks'])<p class="text-xs text-brand-600">Xavflar: {{ implode('; ', $ai['risks']) }}</p>@endif
                    <p class="text-xs text-ink-400">{{ $lead->ai_analyzed_at?->format('d.m.Y H:i') }} · AI xulosasi tavsiya xarakterida</p>
                @else
                    <p class="text-sm text-ink-500">Hali tahlil qilinmagan. Murojaat tushganda va izoh yozilganda AI avtomatik tahlil qiladi (navbat ishlab turgan bo'lsa), yoki «Tahlil qilish» tugmasini bosing.</p>
                @endif
            </div>
            <div class="card card-body">
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-ink-500">Telefon</dt><dd class="font-medium"><x-phone-copy :value="$lead->phone" /></dd></div>
                    <div><dt class="text-ink-500">Manba</dt><dd class="font-medium">{{ $lead->source?->name ?? '—' }}</dd></div>
                    <div><dt class="text-ink-500">Qo'shimcha telefon</dt><dd class="font-medium">{{ $lead->phone2 ?? '—' }}</dd></div>
                    <div><dt class="text-ink-500">Manzil</dt><dd class="font-medium">{{ $lead->address ?? '—' }}</dd></div>
                    <div><dt class="text-ink-500">Kelgan vaqti</dt><dd class="font-medium">{{ $lead->created_at->format('d.m.Y H:i') }}</dd></div>
                    @if ($lead->student)<div><dt class="text-ink-500">O'quvchi</dt><dd><a class="font-semibold text-brand-600" href="{{ route('students.show', $lead->student) }}">{{ $lead->student->name }}</a></dd></div>@endif
                </dl>
            </div>

            @if ($lead->isOpen())
                @can('leads.manage')
                    @can('students.create')
                        <form method="POST" action="{{ route('leads.convert', $lead) }}" class="card card-body space-y-4">
                            @csrf
                            <h3 class="text-base font-semibold text-ink-900 dark:text-white">O'quvchi sifatida ro'yxatga olish</h3>
                            @if ($existing)
                                <div class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                                    Bu raqam bilan o'quvchi bor: <a class="font-semibold underline" href="{{ route('students.show', $existing) }}">{{ $existing->name }}</a>.
                                    Bir filialda bir telefon raqami bitta o'quvchiga tegishli, shuning uchun murojaat shu o'quvchiga bog'lanadi.
                                </div>
                            @endif
                            <div><label class="label" for="about">Eslatma</label><textarea id="about" name="about" rows="2" class="input"></textarea></div>
                            @can('groups.members')
                                @if ($groups->isNotEmpty())
                                    <div><label class="label" for="group_id">Guruhga qo'shish (ixtiyoriy)</label>
                                        <select id="group_id" name="group_id" class="input">
                                            <option value="">Qo'shmaslik</option>
                                            @foreach ($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
                                        </select>
                                    </div>
                                @endif
                            @endcan
                            <button class="btn-primary w-full">{{ $existing ? "Mavjud o'quvchiga bog'lash" : "Ro'yxatga olish" }}</button>
                        </form>
                    @endcan
                    <form method="POST" action="{{ route('leads.cancel', $lead) }}" class="card card-body space-y-3" onsubmit="return confirm('Murojaat bekor qilinsinmi?')">
                        @csrf
                        <input name="reason" placeholder="Bekor qilish sababi" class="input">
                        <button class="btn-secondary w-full">Bekor qilish</button>
                    </form>
                @endcan
            @elseif ($lead->status === 'cancelled')
                @can('leads.manage')<form method="POST" action="{{ route('leads.reopen', $lead) }}">@csrf<button class="btn-secondary w-full">Qayta ochish</button></form>@endcan
            @endif
        </div>

        <div class="card card-body lg:col-span-2">
            <h2 class="mb-4 text-lg font-semibold text-ink-900 dark:text-white">Izohlar tarixi</h2>
            @can('leads.manage')
                <form method="POST" action="{{ route('leads.note', $lead) }}" class="mb-5 flex flex-col gap-2 sm:flex-row">
                    @csrf
                    <input name="body" placeholder="Suhbat natijasi, keyingi qadam..." class="input" required>
                    <button class="btn-primary">Qo'shish</button>
                </form>
                @error('body')<p class="error-text -mt-3 mb-3">{{ $message }}</p>@enderror
            @endcan
            <ol class="space-y-3 border-l border-ink-200 pl-4 dark:border-ink-700">
                @foreach ($lead->notes as $n)
                    <li class="relative text-sm {{ $n->type === 'ai' ? 'rounded-xl bg-brand-50 p-3 dark:bg-brand-950/30' : '' }}">
                        <span class="absolute -left-[21px] top-1.5 h-2.5 w-2.5 rounded-full {{ $n->type === 'ai' ? 'bg-amber-500' : 'bg-brand-500' }}"></span>
                        <div>{{ $n->body }}</div>
                        <div class="text-xs text-ink-400">{{ $n->created_at->format('d.m.Y H:i') }} · {{ $n->type === 'ai' ? 'AI' : ($n->user?->name ?? 'Sayt') }}</div>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
@endsection
