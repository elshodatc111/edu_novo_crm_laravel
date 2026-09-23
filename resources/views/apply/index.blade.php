@extends('layouts.public')
@section('title', 'Filialni tanlang')

@section('content')
    <div class="card card-body">
        <h1 class="text-xl font-bold text-ink-900">Filialni tanlang</h1>
        <p class="mt-1 text-sm text-ink-500">Murojaat qoldirmoqchi bo'lgan filialni tanlang.</p>
        <div class="mt-5 space-y-2">
            @forelse ($branches as $b)
                <a href="{{ route('apply.show', $b->code) }}" class="block rounded-xl border border-ink-200 px-4 py-3 hover:border-brand-500 hover:bg-brand-50">
                    <div class="font-semibold text-ink-900">{{ $b->name }}</div>
                    @if ($b->address)<div class="text-sm text-ink-500">{{ $b->address }}</div>@endif
                </a>
            @empty
                <p class="text-sm text-ink-500">Hozircha faol filial yo'q.</p>
            @endforelse
        </div>
    </div>
@endsection
