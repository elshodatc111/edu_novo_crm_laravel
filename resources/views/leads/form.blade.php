@extends('layouts.app')
@section('title', "Murojaat qo'shish")

@section('content')
    <x-page-header title="Yangi murojaat" subtitle="Telefon yoki tashrif orqali kelgan murojaat" />
    <form method="POST" action="{{ route('leads.store') }}" class="max-w-2xl space-y-6">
        @csrf
        <div class="card card-body grid gap-5 sm:grid-cols-2">
            <x-input name="name" label="F.I.O" required class="sm:col-span-2" />
            <x-input name="phone" phone label="Telefon" required />
            <x-input name="phone2" phone label="Qo'shimcha telefon" />
            <x-input name="address" label="Manzil" />
            <x-select name="lead_source_id" label="Manba"><option value="">Ko'rsatilmagan</option>
                @foreach ($sources as $s)<option value="{{ $s->id }}" @selected(\App\Support\SafeInput::string(old('lead_source_id')) === (string) $s->id)>{{ $s->name }}</option>@endforeach
            </x-select>
        </div>
        <div class="flex gap-2"><button class="btn-primary">Saqlash</button><a href="{{ route('leads.index') }}" class="btn-secondary">Bekor qilish</a></div>
    </form>
@endsection
