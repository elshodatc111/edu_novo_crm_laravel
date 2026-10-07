@extends('layouts.app')
@section('title', "sAdmin qo'shish")

@section('content')
    <x-page-header title="Yangi sAdmin" subtitle="Barcha filiallar va sozlamalarga to'liq kirish huquqi beriladi" />

    <form method="POST" action="{{ route('superadmins.store') }}" class="max-w-3xl space-y-6">
        @csrf
        <div class="card card-body grid gap-5 sm:grid-cols-2">
            <x-input name="name" label="F.I.O" required class="sm:col-span-2" />
            <x-input name="username" label="Login" required hint="Tizimga kirish uchun." />
            <x-input name="phone" phone label="Telefon" required />
            <x-input name="email" type="email" label="Email" />
            <div></div>
            <x-input name="password" type="password" label="Parol" required autocomplete="new-password" hint="Kamida 8 ta belgi." />
            <x-input name="password_confirmation" type="password" label="Parolni takrorlang" autocomplete="new-password" />
        </div>
        <div class="flex gap-2">
            <button class="btn-primary">Saqlash</button>
            <a href="{{ route('superadmins.index') }}" class="btn-secondary">Bekor qilish</a>
        </div>
    </form>
@endsection
