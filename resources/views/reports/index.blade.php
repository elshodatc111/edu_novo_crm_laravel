@extends('layouts.app')
@section('title', 'Hisobotlar')

@section('content')
    <x-page-header title="Hisobotlar" subtitle="Jadval ko'rinishida ko'rish va Excelga yuklab olish" />
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($reports as $key => $def)
            <a href="{{ route('reports.show', $key) }}" class="card card-body transition hover:border-brand-400">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-300"><x-icon name="log" /></span>
                    <div><div class="font-semibold text-ink-900 dark:text-white">{{ $def[0] }}</div><div class="text-xs text-ink-400">{{ $def[2] ? 'Davr bo\'yicha' : 'Hozirgi holat' }}</div></div>
                </div>
            </a>
        @endforeach
    </div>
@endsection
