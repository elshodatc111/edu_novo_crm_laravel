@extends('layouts.app')
@section('title', 'Profil')

@section('content')
    <x-page-header title="Profil" :subtitle="$user->role->label().($user->branch ? ' · '.$user->branch->name : '')" />

    <div class="grid max-w-5xl gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('profile.update') }}" class="card card-body space-y-5">
            @csrf @method('PUT')
            <h2 class="text-base font-semibold text-ink-900 dark:text-white">Shaxsiy ma'lumotlar</h2>
            <x-input name="name" label="F.I.O" :value="$user->name" required />
            <x-input name="phone" phone label="Telefon" :value="$user->phone" :required="! $user->isSuperAdmin()" />
            <x-input name="email" type="email" label="Email" :value="$user->email" />
            <div><button class="btn-primary">Saqlash</button></div>
        </form>

        <form method="POST" action="{{ route('profile.password') }}" class="card card-body space-y-5">
            @csrf @method('PUT')
            <h2 class="text-base font-semibold text-ink-900 dark:text-white">Parolni o'zgartirish</h2>
            <x-input name="current_password" type="password" label="Joriy parol" required autocomplete="current-password" />
            <x-input name="password" type="password" label="Yangi parol" required autocomplete="new-password" hint="Kamida 8 ta belgi." />
            <x-input name="password_confirmation" type="password" label="Yangi parolni takrorlang" required autocomplete="new-password" />
            <div><button class="btn-primary">Parolni yangilash</button></div>
        </form>
    </div>
@endsection
