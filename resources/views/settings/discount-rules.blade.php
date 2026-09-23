@extends('layouts.app')
@section('title', 'Chegirma qoidalari')

@section('content')
    <x-page-header title="Chegirma qoidalari" subtitle="Oldindan to'lov chegirmasi qachon berilishi" />
    @include('partials.settings-tabs')

    <form method="POST" action="{{ route('discount-rules.update') }}" class="card card-body max-w-2xl space-y-5">
        @csrf @method('PUT')
        <div class="grid gap-5 sm:grid-cols-2">
            <x-input name="discount_days_before" type="number" min="0" max="365" label="Guruh boshlanishidan necha kun OLDIN" :value="$branch->discount_days_before" required />
            <x-input name="discount_days_after" type="number" min="0" max="365" label="Guruh boshlanishidan necha kun KEYIN" :value="$branch->discount_days_after" required />
        </div>
        <div class="rounded-xl bg-ink-100 p-4 text-sm leading-relaxed text-ink-600 dark:bg-ink-800 dark:text-ink-300">
            <b>Qanday ishlaydi.</b> Chegirma narx rejasidagi «Oldindan to'lov chegirmasi» summasida, shu muddat oralig'ida beriladi:
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>O'quvchi oldindan to'lagan bo'lsa (balansi <i>narx − chegirma</i> ga yetsa), guruhga qo'shilganda chegirma avtomatik beriladi.</li>
                <li>Guruhga qo'shilgan o'quvchi shu guruh uchun <i>narx − chegirma</i> ga yetguncha to'lasa, chegirma to'lov paytida beriladi.</li>
                <li>Chegirma bir o'quvchiga bir guruh uchun faqat <b>bir marta</b> beriladi (guruhga qo'shilganda olgan bo'lsa, keyingi to'lovda qayta berilmaydi).</li>
            </ul>
        </div>
        <button class="btn-primary">Saqlash</button>
    </form>
@endsection
