@if (session('success') || session('error'))
    @php $ok = (bool) session('success'); @endphp
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" x-transition
         class="mb-5 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm {{ $ok ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200' : 'border-brand-200 bg-brand-50 text-brand-800 dark:border-brand-900 dark:bg-brand-950/50 dark:text-brand-200' }}">
        <x-icon :name="$ok ? 'check' : 'alert'" class="mt-0.5 h-5 w-5 shrink-0" />
        <div class="flex-1">{{ session('success') ?? session('error') }}</div>
        <button type="button" @click="show = false" class="opacity-60 hover:opacity-100"><x-icon name="x" class="h-4 w-4" /></button>
    </div>
@endif
