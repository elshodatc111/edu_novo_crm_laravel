@extends('layouts.app')
@section('title', $student->exists ? "O'quvchini tahrirlash" : "O'quvchi qo'shish")

@section('content')
    <x-page-header :title="$student->exists ? $student->name : 'Yangi o\'quvchi'" />

    <form method="POST" action="{{ $student->exists ? route('students.update', $student) : route('students.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($student->exists) @method('PUT') @endif

        <div class="card card-body grid gap-5 sm:grid-cols-2">
            <x-input name="name" label="F.I.O" :value="$student->name" required class="sm:col-span-2" />
            <x-input name="phone" phone label="Asosiy telefon" :value="$student->phone" required hint="Mobil ilova logini shu raqamdan hosil bo'ladi. Bir filialda bitta o'quvchiga tegishli." />
            <x-input name="phone2" phone label="Qo'shimcha telefon (ota-ona)" :value="$student->phone2" hint="Takrorlanishi mumkin (aka-uka uchun umumiy raqam)." />
            <x-input name="birthday" type="date" label="Tug'ilgan sana" :value="$student->birthday?->format('Y-m-d')" />
            <x-select name="lead_source_id" label="Qayerdan eshitdi?">
                <option value="">Ko'rsatilmagan</option>
                @foreach ($sources as $src)
                    <option value="{{ $src->id }}" @selected(\App\Support\SafeInput::string(old('lead_source_id', $student->lead_source_id)) === (string) $src->id)>{{ $src->name }}</option>
                @endforeach
            </x-select>
            <x-input name="address" label="Manzil" :value="$student->address" class="sm:col-span-2" />
            <div class="sm:col-span-2">
                <label for="about" class="label">Eslatma</label>
                <textarea id="about" name="about" rows="3" class="input">{{ \App\Support\SafeInput::string(old('about'), $student->about, 2000) }}</textarea>
                @error('about')<p class="error-text">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="flex gap-2">
            <button class="btn-primary">Saqlash</button>
            <a href="{{ $student->exists ? route('students.show', $student) : route('students.index') }}" class="btn-secondary">Bekor qilish</a>
        </div>
    </form>
@endsection
