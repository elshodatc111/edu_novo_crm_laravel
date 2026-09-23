@props(['href', 'icon', 'active' => false])
<a href="{{ $href }}" {{ $attributes }} @class(['nav-link', 'nav-link-active' => $active]) @if ($active) aria-current="page" @endif>
    <x-icon :name="$icon" class="h-5 w-5 shrink-0" />
    <span>{{ $slot }}</span>
</a>
