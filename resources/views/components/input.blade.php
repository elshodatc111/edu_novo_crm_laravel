@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null, 'money' => false, 'phone' => false])
@php
    // v8 A6: agar shu maydon nomi bilan avval massiv (masalan name[]=x) yuborilgan bo'lsa, old() massiv
    // qaytaradi - {{ }} uni to'g'ridan-to'g'ri chiqarsa 500 xato beradi. SafeInput bunday holatda $value'ni qaytaradi.
    $current = \App\Support\SafeInput::string(old($name), $value, 5000);
    if ($money) {
        $type = 'text';
        $current = filled($current) ? number_format((int) preg_replace('/\D/', '', (string) $current), 0, '', ' ') : $current;
    }
    if ($phone) {
        $type = 'tel';
    }
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="label">{{ $label }} @if ($required)<span class="text-brand-600">*</span>@endif</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'password') value="{{ $current }}" @endif
           @if ($money) inputmode="numeric" data-money autocomplete="off" @endif
           @if ($phone) inputmode="tel" data-phone maxlength="17" pattern="\+998 \d{2} \d{3} \d{4}" placeholder="+998 90 123 4567" title="+998 90 123 4567 ko'rinishida" @endif
           @required($required)
           {{ $attributes->except(['class', 'min', 'max', 'step'])->merge(['class' => 'input'.($errors->has($name) ? ' input-error' : '')]) }}
           @unless ($money) {{ $attributes->only(['min', 'max', 'step']) }} @endunless>
    @if ($hint)<p class="hint">{{ $hint }}</p>@endif
    @error($name)<p class="error-text">{{ $message }}</p>@enderror
</div>
