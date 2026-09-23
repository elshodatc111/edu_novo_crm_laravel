@extends('layouts.app')
@section('title', $name)

@section('content')
    <x-page-header :title="$name">
        <x-slot:actions>
            <a href="{{ route('reports.index') }}" class="btn-secondary">Hisobotlar</a>
            @if ($canExport)<a href="{{ route('reports.export', ['report' => $key, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn-primary">Excelga yuklab olish</a>@endif
        </x-slot:actions>
    </x-page-header>

    @if ($needsPeriod)
        <form method="GET" class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end">
            <div><label class="label">Boshlanishi</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="input"></div>
            <div><label class="label">Tugashi</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="input"></div>
            <button class="btn-secondary">Ko'rsatish</button>
        </form>
    @endif

    @if ($data['summary'])
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($data['summary'] as $label => $value)
                <div class="card card-body"><div class="text-sm text-ink-500">{{ $label }}</div>
                    <div class="mt-1 text-xl font-bold text-ink-900 dark:text-white">{{ is_numeric($value) ? number_format($value, is_float($value) ? 1 : 0, '.', ' ') : $value }}</div></div>
            @endforeach
        </div>
    @endif

    <div class="card overflow-hidden">
        <div class="table-wrap"><table class="table">
            <thead><tr>@foreach ($data['columns'] as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
            <tbody>
            @forelse ($shown as $row)
                <tr>@foreach ($row as $cell)<td class="{{ is_int($cell) ? 'text-right' : '' }}">{{ is_int($cell) ? number_format($cell, 0, '', ' ') : $cell }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($data['columns']) }}" class="py-12 text-center text-ink-500">Ma'lumot yo'q.</td></tr>
            @endforelse
            </tbody></table></div>
        @if (count($data['rows']) > count($shown))
            <p class="border-t border-ink-100 px-5 py-3 text-sm text-ink-500 dark:border-ink-800">Sahifada dastlabki {{ count($shown) }} ta qator ko'rsatildi. Barcha {{ count($data['rows']) }} qator Excel faylida.</p>
        @endif
    </div>
@endsection
