@props(['count' => 0])
{{--
    v13: yuqori paneldagi eslatmalar qo'ng'iroqchasi. Faol (yopilmagan) eslatmalar soni belgida ko'rinadi,
    har 15 soniyada yangilanadi; bosilganda qaysi o'quvchiga qaysi eslatma qoldirilgani ro'yxatda chiqadi.
    «Faolsizlantirish» eslatmani o'chirmaydi - «Yopilgan» tabiga o'tkazadi, qayta faollashtirish mumkin.
--}}
@php
    $urls = [
        'feed' => route('notes.feed'),
        'close' => route('notes.close', '__ID__'),
        'reopen' => route('notes.reopen', '__ID__'),
    ];
@endphp
<div x-data="notesBell(@js((int) $count), @js($urls))" @keydown.escape.window="open = false" @click.outside="open = false" class="relative">
    <button type="button" @click="toggle()" class="btn-ghost relative p-2" aria-label="Eslatmalar" title="Eslatmalar">
        <x-icon name="bell" />
        <span x-show="count > 0" x-cloak x-text="count > 99 ? '99+' : count"
              class="absolute -right-0.5 -top-0.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-brand-600 px-1 text-[10px] font-bold leading-none text-white"></span>
    </button>

    <div x-show="open" x-cloak x-transition.opacity
         class="absolute right-0 z-40 mt-2 w-[24rem] max-w-[92vw] overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-pop dark:border-ink-700 dark:bg-ink-900">
        <div class="flex items-center justify-between border-b border-ink-100 px-4 py-3 dark:border-ink-800">
            <div class="text-sm font-semibold text-ink-900 dark:text-white">Eslatmalar</div>
            <div class="flex gap-1 rounded-lg bg-ink-100 p-0.5 text-xs font-medium dark:bg-ink-800">
                <button type="button" @click="setTab('active')" :class="tab === 'active' ? 'bg-white text-brand-700 shadow-sm dark:bg-ink-900' : 'text-ink-600 dark:text-ink-300'" class="rounded-md px-3 py-1">Faol</button>
                <button type="button" @click="setTab('closed')" :class="tab === 'closed' ? 'bg-white text-brand-700 shadow-sm dark:bg-ink-900' : 'text-ink-600 dark:text-ink-300'" class="rounded-md px-3 py-1">Yopilgan</button>
            </div>
        </div>

        <div class="max-h-[26rem] overflow-y-auto">
            <div x-show="loading && items.length === 0" class="px-4 py-8 text-center text-sm text-ink-500">Yuklanmoqda...</div>
            <div x-show="error" class="px-4 py-3 text-center text-xs text-brand-600">Ma'lumotni yangilab bo'lmadi, qayta urinilmoqda.</div>
            <div x-show="!loading && items.length === 0" class="px-4 py-8 text-center text-sm text-ink-500"
                 x-text="tab === 'active' ? 'Faol eslatma yo\'q.' : 'Yopilgan eslatma yo\'q.'"></div>

            <template x-for="item in items" :key="item.id">
                <div class="border-b border-ink-100 px-4 py-3 last:border-0 dark:border-ink-800">
                    <div class="flex items-start justify-between gap-3">
                        <a :href="item.student_url" class="text-sm font-semibold text-ink-900 hover:text-brand-600 dark:text-white" x-text="item.student"></a>
                        <button type="button" @click="act(item)" class="shrink-0 text-xs font-medium text-ink-500 hover:text-brand-600"
                                x-text="tab === 'active' ? 'Faolsizlantirish' : 'Qayta faollashtirish'"></button>
                    </div>
                    <div class="mt-1 whitespace-pre-wrap break-words text-sm text-ink-600 dark:text-ink-300" x-text="item.body"></div>
                    <div class="mt-1 text-xs text-ink-400">
                        <span x-text="item.created_at"></span> · <span x-text="item.author || '—'"></span>
                        <template x-if="showBranch && item.branch"><span> · <span x-text="item.branch"></span></span></template>
                        <template x-if="item.closed_at"><span> · yopilgan <span x-text="item.closed_at"></span><template x-if="item.closed_by"><span>, <span x-text="item.closed_by"></span></span></template></span></template>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
