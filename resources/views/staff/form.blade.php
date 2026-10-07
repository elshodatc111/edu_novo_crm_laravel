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

            @if ($staff->exists && ! empty($changeRoles))
                <div class="sm:col-span-2">
                    <x-select name="role" label="Lavozim">
                        @foreach ($changeRoles as $role)
                            <option value="{{ $role->value }}" @selected(old('role', $staff->role->value) === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </x-select>
                    <p class="mt-1 text-xs text-ink-500">Lavozim o'zgarsa, ruxsatlar yangi lavozim shabloniga qaytariladi va mobil ilovadagi seanslari tugatiladi.</p>
                </div>
            @endif

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

        @if (auth()->user()->isSuperAdmin() && ($extraBranchOptions ?? collect())->isNotEmpty())
            <div class="card card-body" @if (! $staff->exists) x-data="{ role: '{{ old('role') }}' }" x-init="$nextTick(() => { const s = document.querySelector('select[name=role]'); if (s) { role = s.value; s.addEventListener('change', () => role = s.value) } })" x-show="role === 'operator'" x-cloak @endif>
                <input type="hidden" name="extra_branches_form" value="1">
                <h2 class="text-base font-semibold text-ink-900 dark:text-white">Operator ko'ra oladigan qo'shimcha filiallar</h2>
                <p class="mt-1 text-sm text-ink-500">Operator o'z filialidan tashqari bu filiallarga ham yuqoridagi filial tanlagich orqali o'ta oladi va u yerda o'z ruxsatlari doirasida ishlaydi. Belgini olib tashlasangiz, kirish huquqi darhol bekor bo'ladi.</p>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach ($extraBranchOptions as $b)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="extra_branches[]" value="{{ $b->id }}" @checked(in_array($b->id, (array) old('extra_branches', $extraBranchIds ?? []), false)) class="rounded border-ink-300">
                            {{ $b->name }}
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex gap-2">
            <button class="btn-primary">Saqlash</button>
            <a href="{{ route('staff.index') }}" class="btn-secondary">Bekor qilish</a>
        </div>
    </form>
@endsection
