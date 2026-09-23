{{-- Dars kalendari: oy ko'rinishi + tanlangan kun jadvali (vaqt, xona, guruh, o'qituvchi). Guruh nomi bosilsa, guruh ochiladi. --}}
@php
    $today = today()->toDateString();
    $inMonth = str_starts_with($today, $calendar['month']);
    $initial = $inMonth ? $today : $calendar['month'].'-01';
    $weekdays = ['Du', 'Se', 'Ch', 'Pa', 'Ju', 'Sh', 'Ya'];
@endphp
<div id="calendar" class="card mt-8" x-data="lessonCalendar(@js($calendar['days']), @js($initial), @js($today))">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-ink-100 px-5 py-4 dark:border-ink-800 sm:px-6">
        <div>
            <h2 class="text-lg font-semibold text-ink-900 dark:text-white">Dars kalendari</h2>
            <p class="text-xs text-ink-500 dark:text-ink-400">Qaysi kuni, qaysi vaqtda, qaysi xonada, qaysi guruhning darsi bor</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if (count($calendar['rooms']) > 1)
                <select x-model="room" class="input py-1.5 text-sm" aria-label="Xona bo'yicha saralash">
                    <option value="">Barcha xonalar</option>
                    @foreach ($calendar['rooms'] as $r)<option value="{{ $r['id'] }}">{{ $r['name'] }}</option>@endforeach
                </select>
            @endif
            <div class="flex items-center gap-1">
                <a href="{{ route('dashboard', ['cal' => $calendar['prev']]) }}#calendar" class="btn-secondary btn-sm" aria-label="Oldingi oy">‹</a>
                <span class="min-w-[8.5rem] text-center text-sm font-semibold text-ink-900 dark:text-white">{{ $calendar['label'] }}</span>
                <a href="{{ route('dashboard', ['cal' => $calendar['next']]) }}#calendar" class="btn-secondary btn-sm" aria-label="Keyingi oy">›</a>
                @unless ($inMonth)<a href="{{ route('dashboard') }}#calendar" class="btn-ghost btn-sm">Bugun</a>@endunless
            </div>
        </div>
    </div>

    <div class="grid lg:grid-cols-5">
        {{-- Oy jadvali --}}
        <div class="p-4 sm:p-5 lg:col-span-3">
            <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-semibold uppercase tracking-wide text-ink-400">
                @foreach ($weekdays as $w)<div class="py-1">{{ $w }}</div>@endforeach
            </div>
            <div class="grid grid-cols-7 gap-1">
                @foreach ($calendar['cells'] as $date)
                    @if ($date === null)
                        <div class="min-h-[3.25rem] sm:min-h-[5.5rem]"></div>
                    @else
                        <button type="button" @click="sel = '{{ $date }}'"
                                :class="[sel === '{{ $date }}' ? 'border-brand-500 bg-brand-50 ring-1 ring-brand-500 dark:bg-brand-950/50' : 'border-ink-100 hover:border-brand-300 dark:border-ink-800', '{{ $date }}' === today ? 'font-bold' : '']"
                                class="flex min-h-[3.25rem] flex-col rounded-lg border p-1 text-left align-top transition sm:min-h-[5.5rem] sm:p-1.5" aria-label="{{ $date }}">
                            <span class="flex items-center justify-between">
                                <span class="{{ $date === $today ? 'flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-600 px-1 text-[11px] text-white' : 'text-xs text-ink-700 dark:text-ink-200' }}">{{ (int) substr($date, 8, 2) }}</span>
                                <span x-show="list('{{ $date }}').length" x-text="list('{{ $date }}').length" class="rounded-full bg-brand-100 px-1.5 text-[10px] font-semibold text-brand-700 dark:bg-brand-900 dark:text-brand-200 sm:hidden"></span>
                            </span>
                            <span class="mt-1 hidden space-y-0.5 sm:block">
                                <template x-for="(l, i) in list('{{ $date }}').slice(0, 2)" :key="i">
                                    <span class="block truncate rounded bg-brand-50 px-1 text-[10px] leading-4 text-brand-800 dark:bg-brand-950 dark:text-brand-200" x-text="l.start + ' ' + l.group"></span>
                                </template>
                                <span x-show="list('{{ $date }}').length > 2" class="block px-1 text-[10px] text-ink-500" x-text="'+' + (list('{{ $date }}').length - 2) + ' ta yana'"></span>
                            </span>
                        </button>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Tanlangan kun --}}
        <div class="border-t border-ink-100 p-4 dark:border-ink-800 sm:p-5 lg:col-span-2 lg:border-l lg:border-t-0">
            <div class="mb-3 flex items-baseline justify-between">
                <h3 class="text-base font-semibold text-ink-900 dark:text-white" x-text="title()"></h3>
                <span class="text-xs text-ink-500" x-text="list(sel).length + ' ta dars'"></span>
            </div>
            <template x-if="!list(sel).length"><p class="rounded-xl bg-ink-50 p-4 text-sm text-ink-500 dark:bg-ink-800/40">Bu kunda dars yo'q.</p></template>
            <ul class="max-h-[26rem] space-y-2 overflow-y-auto pr-1">
                <template x-for="(l, i) in list(sel)" :key="i">
                    <li class="rounded-xl border border-ink-100 p-3 dark:border-ink-800">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <template x-if="l.url"><a :href="l.url" class="block truncate text-sm font-semibold text-ink-900 hover:text-brand-600 dark:text-white" x-text="l.group"></a></template>
                                <template x-if="!l.url"><span class="block truncate text-sm font-semibold text-ink-900 dark:text-white" title="Bu guruhni ochishga ruxsatingiz yo'q" x-text="l.group"></span></template>
                                <div class="mt-0.5 text-xs text-ink-500" x-text="[l.course, l.teacher].filter(Boolean).join(' · ')"></div>
                            </div>
                            <div class="shrink-0 text-right">
                                <div class="text-sm font-semibold tabular-nums text-brand-700 dark:text-brand-300" x-text="l.start + (l.end ? ' – ' + l.end : '')"></div>
                                <div class="text-xs text-ink-500" x-text="l.room"></div>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('lessonCalendar', (days, initial, today) => ({
                    days, sel: initial, today, room: '',
                    list(date) { return (this.days[date] || []).filter(l => !this.room || String(l.room_id) === this.room); },
                    title() {
                        const [y, m, d] = this.sel.split('-').map(Number);
                        const names = ['yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun', 'iyul', 'avgust', 'sentabr', 'oktabr', 'noyabr', 'dekabr'];
                        const week = ['Yakshanba', 'Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'];
                        return `${d}-${names[m - 1]}, ${week[new Date(y, m - 1, d).getDay()]}` + (this.sel === this.today ? ' (bugun)' : '');
                    },
                }));
            });
        </script>
    @endpush
@endonce
