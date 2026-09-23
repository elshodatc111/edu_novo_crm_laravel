@php
    $tabs = [
        ['Xonalar', route('catalog.index', 'rooms'), request()->is('settings/rooms*')],
        ['Dars vaqtlari', route('catalog.index', 'lesson-times'), request()->is('settings/lesson-times*')],
        ['Narx rejalari', route('catalog.index', 'price-plans'), request()->is('settings/price-plans*')],
        ['Xarajat turlari', route('catalog.index', 'expense-categories'), request()->is('settings/expense-categories*')],
        ['Chegirma qoidalari', route('discount-rules.edit'), request()->is('settings/discount-rules*')],
        ['Aksiyalar', route('catalog.index', 'campaigns'), request()->is('settings/campaigns*')],
        ['Kitoblar', route('catalog.index', 'books'), request()->is('settings/books*')],
        ["O'quvchi manbalari", route('catalog.index', 'lead-sources'), request()->is('settings/lead-sources*')],
        ['Dam olish kunlari', route('holidays.index'), request()->is('settings/holidays*')],
        ['Shartnoma', route('contract-settings.edit'), request()->is('settings/contract*')],
    ];
@endphp
<div class="mb-6 flex gap-1 overflow-x-auto rounded-xl bg-ink-100 p-1 dark:bg-ink-800">
    @foreach ($tabs as [$label, $url, $active])
        <a href="{{ $url }}" class="whitespace-nowrap rounded-lg px-3.5 py-2 text-sm font-medium transition {{ $active ? 'bg-white text-brand-700 shadow-sm dark:bg-ink-900 dark:text-brand-300' : 'text-ink-600 hover:text-ink-900 dark:text-ink-300 dark:hover:text-white' }}">{{ $label }}</a>
    @endforeach
</div>
