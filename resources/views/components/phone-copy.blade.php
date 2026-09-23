{{-- v9: telefon raqamini probelsiz (+998901234567) bir bosishda nusxalash. --}}
@props(['value'])
@php $digits = \App\Support\Format::normalizePhone($value); @endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5']) }}>
    <span>{{ \App\Support\Format::prettyPhone($value) }}</span>
    @if ($digits)
        <button type="button" x-data="{ copied: false }" title="Raqamni nusxalash"
            @click.stop.prevent="navigator.clipboard.writeText('+{{ $digits }}'); copied = true; setTimeout(() => copied = false, 1500)"
            class="text-ink-400 transition hover:text-brand-600">
            <x-icon x-show="!copied" name="copy" class="h-3.5 w-3.5" />
            <x-icon x-show="copied" name="check" class="h-3.5 w-3.5 text-emerald-600" x-cloak />
        </button>
    @endif
</span>
