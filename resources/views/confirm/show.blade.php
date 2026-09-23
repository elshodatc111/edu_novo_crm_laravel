@extends('layouts.app')
@section('title', 'Tekshiring')

@section('content')
    <div class="mx-auto max-w-xl" x-data="{ busy: false }">
        <x-page-header title="Tekshiring" subtitle="Pul chiqimi tasdiqlangandan keyin o'zgartirib bo'lmaydi" />

        <div class="card card-body space-y-5">
            <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                <x-icon name="alert" class="mt-0.5 h-5 w-5 shrink-0" />
                <div>Ma'lumotlar to'g'riligini diqqat bilan tekshiring. <b>Tasdiqlagandan keyin</b> bu amalni bekor qilib yoki tuzatib bo'lmaydi.</div>
            </div>

            @if ($item['warning'] ?? null)
                <div class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
                    <x-icon name="alert" class="mt-0.5 h-5 w-5 shrink-0" />
                    <div>{{ $item['warning'] }}</div>
                </div>
            @endif

            <div>
                <h2 class="text-lg font-semibold text-ink-900 dark:text-white">{{ $item['title'] }}</h2>
                <dl class="mt-3 divide-y divide-ink-100 text-sm dark:divide-ink-800">
                    @foreach ($item['rows'] as [$label, $value])
                        <div class="flex justify-between gap-4 py-2.5"><dt class="text-ink-500">{{ $label }}</dt><dd class="text-right font-medium text-ink-900 dark:text-white">{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </div>

            @if ($item['balance'])
                <div class="rounded-xl bg-ink-50 p-4 text-sm dark:bg-ink-800/60">
                    <div class="text-ink-500">{{ $item['balance']['label'] }}</div>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-lg font-bold">
                        <span class="text-ink-900 dark:text-white">{{ \App\Support\Format::money($item['balance']['before']) }}</span>
                        <span class="text-ink-400">→</span>
                        <span class="text-brand-600">{{ \App\Support\Format::money($item['balance']['after']) }}</span>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('confirm.store', $token) }}" class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end" @submit="busy = true">
                @csrf
                <a href="{{ $item['back'] }}" class="btn-secondary">Tuzatish (orqaga)</a>
                <button class="btn-primary" :disabled="busy"><x-icon name="check" class="h-4 w-4" /> <span x-text="busy ? 'Bajarilmoqda...' : 'Ha, to\'g\'ri — tasdiqlash'"></span></button>
            </form>
        </div>
    </div>
@endsection
