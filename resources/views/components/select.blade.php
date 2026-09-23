@props(['name', 'label', 'required' => false])
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="label">{{ $label }} @if ($required)<span class="text-brand-600">*</span>@endif</label>
    <select id="{{ $name }}" name="{{ $name }}" @required($required)
            {{ $attributes->except('class')->merge(['class' => 'input'.($errors->has($name) ? ' input-error' : '')]) }}>
        {{ $slot }}
    </select>
    @error($name)<p class="error-text">{{ $message }}</p>@enderror
</div>
