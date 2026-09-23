@extends('layouts.app')
@section('title', $staff->exists ? 'Hodimni tahrirlash' : "Hodim qo'shish")

@section('content')
    <x-page-header :title="$staff->exists ? 'Hodimni tahrirlash' : 'Yangi hodim'" :subtitle="$staff->exists ? $staff->role->label() : null" />

    <form method="POST" action="{{ $staff->exists ? route('staff.update', $staff) : route('staff.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($staff->exists) @method('PUT') @endif

        <div class="card card-body grid gap-5 sm:grid-cols-2">
            @unless ($staff->exists)
                <x-select name="role" label="Lavozim" required>
                    <option value="">Tanlang</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </x-select>
                @if (auth()->user()->isSuperAdmin())
                    <x-select name="branch_id" label="Filial" required>
                        <option value="">Tanlang</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected(\App\Support\SafeInput::string(old('branch_id')) === (string) $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </x-select>
                @endif
            @endunless

            <x-input name="name" label="F.I.O" :value="$staff->name" required class="sm:col-span-2" />
            <x-input name="username" label="Login" :value="$staff->username" required hint="Tizimga kirish uchun (masalan, telefon raqami)." />
            <x-input name="phone" phone label="Telefon" :value="$staff->phone" required hint="Asosiy telefon. Bir filialda bir lavozimda takrorlanmaydi." />
            <x-input name="email" type="email" label="Email" :value="$staff->email" />
            <x-input name="birthday" type="date" label="Tug'ilgan sana" :value="$staff->birthday?->format('Y-m-d')" />
            <x-input name="address" label="Manzil" :value="$staff->address" class="sm:col-span-2" />

            <x-input name="password" type="password" label="Parol" :required="! $staff->exists" autocomplete="new-password"
                     :hint="$staff->exists ? 'O\'zgartirmaslik uchun bo\'sh qoldiring.' : 'Kamida 8 ta belgi.'" />
            <x-input name="password_confirmation" type="password" label="Parolni takrorlang" autocomplete="new-password" />

            <x-select name="status" label="Holat" required>
                @foreach (\App\Enums\UserStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(old('status', $staff->status?->value) === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </x-select>
        </div>

        <div class="flex gap-2">
            <button class="btn-primary">Saqlash</button>
            <a href="{{ route('staff.index') }}" class="btn-secondary">Bekor qilish</a>
        </div>
    </form>
@endsection
