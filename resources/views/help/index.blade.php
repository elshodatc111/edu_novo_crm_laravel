@extends('layouts.app')
@section('title', 'Yordam')

@php
    $analyst = $mode === 'analyst';
    $sendRoute = $analyst ? 'ai.send' : 'help.send';
    $destroyRoute = $analyst ? 'ai.destroy' : 'help.destroy';
@endphp

@section('content')
    <x-page-header title="Yordam" :subtitle="$analyst ? 'Tahlil: filial statistikasi bo\'yicha savollar, kamchiliklar va tavsiyalar' : 'Qo\'llanma: tizimdan foydalanish bo\'yicha savollarga rolingizga mos javob'">
        <x-slot:actions><a href="{{ route('help.index', ['mode' => $mode]) }}" class="btn-secondary"><x-icon name="plus" class="h-4 w-4" /> Yangi suhbat</a></x-slot:actions>
    </x-page-header>

    {{-- Ikki rejim, bitta sahifa --}}
    @if ($canAnalyst)
        <div class="mb-6 inline-flex rounded-xl bg-ink-100 p-1 text-sm font-semibold dark:bg-ink-800" role="tablist" aria-label="Rejim">
            <a href="{{ route('help.index') }}" role="tab" aria-selected="{{ $analyst ? 'false' : 'true' }}" class="rounded-lg px-4 py-2 {{ ! $analyst ? 'bg-white text-brand-700 shadow-sm dark:bg-ink-900 dark:text-brand-300' : 'text-ink-500 hover:text-ink-800 dark:hover:text-ink-100' }}">Qo'llanma</a>
            <a href="{{ route('help.index', ['mode' => 'analyst']) }}" role="tab" aria-selected="{{ $analyst ? 'true' : 'false' }}" class="rounded-lg px-4 py-2 {{ $analyst ? 'bg-white text-brand-700 shadow-sm dark:bg-ink-900 dark:text-brand-300' : 'text-ink-500 hover:text-ink-800 dark:hover:text-ink-100' }}">Tahlil (AI)</a>
        </div>
    @endif

    @unless ($configured)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
            OpenAI kaliti sozlanmagan. <code>.env</code> faylida <code>OPENAI_API_KEY=...</code> ni kiriting va serverni qayta ishga tushiring.
        </div>
    @endunless

    <div class="grid gap-6 lg:grid-cols-4">
        <div class="card card-body h-fit space-y-1 lg:col-span-1">
            <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-ink-500">Suhbatlar</h3>
            @forelse ($chats as $c)
                <div class="group flex items-center gap-1">
                    <a href="{{ route('help.index', ['mode' => $mode, 'chat' => $c->id]) }}" class="flex-1 truncate rounded-lg px-2.5 py-2 text-sm {{ $current?->id === $c->id ? 'bg-brand-50 font-semibold text-brand-700 dark:bg-brand-950/60 dark:text-brand-300' : 'text-ink-600 hover:bg-ink-100 dark:text-ink-300 dark:hover:bg-ink-800' }}">{{ $c->title }}</a>
                    <form method="POST" action="{{ route($destroyRoute, $c) }}" onsubmit="return confirm('Suhbat o\'chirilsinmi?')">@csrf @method('DELETE')<button class="btn-ghost btn-sm opacity-0 group-hover:opacity-100" title="O'chirish"><x-icon name="trash" class="h-4 w-4" /></button></form>
                </div>
            @empty<p class="text-sm text-ink-500">Hali suhbat yo'q.</p>@endforelse
        </div>

        <div class="space-y-4 lg:col-span-3" x-data="{ busy: false }">
            <div class="card card-body min-h-[20rem] space-y-4">
                @forelse ($messages as $m)
                    <div class="flex {{ $m->role === 'user' ? 'justify-end' : '' }}">
                        <div class="max-w-[85%] whitespace-pre-line rounded-2xl px-4 py-3 text-sm leading-relaxed {{ $m->role === 'user' ? 'bg-brand-600 text-white' : 'bg-ink-100 text-ink-800 dark:bg-ink-800 dark:text-ink-100' }}">{{ $m->content }}</div>
                    </div>
                @empty
                    <div class="py-6 text-center text-sm text-ink-500">
                        @if ($analyst)
                            <p>Savol bering yoki tayyor tahlilni tanlang.</p>
                            <p class="mt-1 text-xs text-ink-400">AI faqat sizning ruxsatingiz doirasidagi raqamlardan foydalanadi. Ism va telefonlar OpenAI'ga yuborilmaydi.</p>
                            <p class="mt-3 text-xs">Tizim qoidalari yoki qadamlar haqida savol bo'lsa, <a href="{{ route('help.index') }}" class="font-semibold text-brand-600">Qo'llanma</a> rejimiga o'ting.</p>
                        @else
                            <p>Tizim haqida savol bering yoki namunalardan birini tanlang.</p>
                            <p class="mt-1 text-xs text-ink-400">Javoblar sizning rolingiz va ruxsatlaringizga mos beriladi. Bazadagi ma'lumotlar AI'ga yuborilmaydi.</p>
                            @if ($canAnalyst)<p class="mt-3 text-xs">Filial raqamlari (tushum, qarz, davomad) bo'yicha tahlil kerak bo'lsa, <a href="{{ route('help.index', ['mode' => 'analyst']) }}" class="font-semibold text-brand-600">Tahlil (AI)</a> rejimiga o'ting.</p>@endif
                        @endif
                    </div>
                @endforelse
            </div>

            @error('message')<p class="error-text">{{ $message }}</p>@enderror

            @if (! $current)
                <div class="flex flex-wrap gap-2">
                    @foreach ($examples as $p)
                        <form method="POST" action="{{ route($sendRoute) }}" @submit="busy = true">@csrf<input type="hidden" name="message" value="{{ $p }}"><button class="btn-secondary btn-sm text-left" :disabled="busy">{{ \Illuminate\Support\Str::limit($p, 60) }}</button></form>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route($sendRoute) }}" class="flex gap-2" @submit="busy = true">
                @csrf
                <input type="hidden" name="chat_id" value="{{ $current?->id }}">
                <input name="message" class="input" placeholder="{{ $analyst ? 'Savolingizni yozing...' : "Masalan: qarzi bor o'quvchini guruhga qo'sha olamanmi?" }}" maxlength="{{ $analyst ? 1500 : 1000 }}" required :disabled="busy" autofocus>
                <button class="btn-primary shrink-0" :disabled="busy"><span x-show="!busy">Yuborish</span><span x-show="busy" x-cloak>Tahlil qilinmoqda...</span></button>
            </form>
            @if ($analyst && $tools)<p class="text-xs text-ink-400">Mavjud ma'lumot manbalari: {{ implode(', ', $tools) }}.</p>@endif
        </div>
    </div>
@endsection
