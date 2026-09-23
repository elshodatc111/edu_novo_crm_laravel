@extends('layouts.app')
@section('title', 'Filiallar')

@section('content')
<div x-data="{ closing: null, deleting: null }">
    <x-page-header title="Filiallar" subtitle="Filiallarni ochish, tahrirlash va yopish">
        <x-slot:actions>
            <a href="{{ route('branches.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Yangi filial</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($branches as $branch)
            <div class="card card-body flex flex-col {{ $branch->isActive() ? '' : 'opacity-75' }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-ink-900 dark:text-white">{{ $branch->name }}</h3>
                        <p class="text-xs text-ink-400">{{ $branch->code }}</p>
                    </div>
                    <span class="{{ $branch->isActive() ? 'badge-green' : 'badge-gray' }}">
                        @unless ($branch->isActive())<x-icon name="archive" class="h-3.5 w-3.5" />@endunless
                        {{ $branch->status->label() }}
                    </span>
                </div>

                <dl class="mt-4 space-y-1.5 text-sm text-ink-600 dark:text-ink-300">
                    @if ($branch->phone)<div class="flex items-center gap-2"><x-icon name="phone" class="h-4 w-4 text-ink-400" />{{ $branch->phone }}</div>@endif
                    @if ($branch->address)<div class="flex items-center gap-2"><x-icon name="pin" class="h-4 w-4 text-ink-400" />{{ $branch->address }}</div>@endif
                    <div class="flex items-center gap-2"><x-icon name="users" class="h-4 w-4 text-ink-400" />{{ $branch->staff_count }} hodim · {{ $branch->students_count }} o'quvchi</div>
                    <div class="text-xs text-ink-400">SMS: {{ $branch->hasOwnSmsAccount() ? "filialning o'z akkaunti" : 'umumiy akkaunt' }}</div>
                    @unless ($branch->isActive())
                        <div class="text-xs text-ink-400">Yopilgan: {{ $branch->closed_at?->format('d.m.Y') }}@if ($branch->closed_reason) — {{ $branch->closed_reason }}@endif</div>
                    @endunless
                </dl>

                <div class="mt-5 flex flex-wrap gap-2 border-t border-ink-100 pt-4 dark:border-ink-800">
                    <a href="{{ route('branches.edit', $branch) }}" class="btn-secondary btn-sm"><x-icon name="edit" class="h-4 w-4" /> Tahrirlash</a>
                    @if ($branch->isActive())
                        <button type="button" class="btn-ghost btn-sm" @click="closing = { id: {{ $branch->id }}, name: @js($branch->name) }">Yopish</button>
                    @else
                        <form method="POST" action="{{ route('branches.reopen', $branch) }}">@csrf
                            <button class="btn-ghost btn-sm">Qayta ochish</button>
                        </form>
                    @endif
                    <button type="button" class="btn-danger btn-sm ml-auto" @click="deleting = { id: {{ $branch->id }}, name: @js($branch->name) }">
                        <x-icon name="trash" class="h-4 w-4" /> Butunlay o'chirish
                    </button>
                </div>
            </div>
        @empty
            <div class="card card-body col-span-full py-12 text-center text-ink-500">Hali filial yo'q. Birinchi filialni oching.</div>
        @endforelse
    </div>

    {{-- Yopish oynasi --}}
    <div x-show="closing" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/60 p-4" @keydown.escape.window="closing = null">
        <div class="card card-body w-full max-w-md shadow-pop" @click.outside="closing = null">
            <h3 class="text-lg font-semibold text-ink-900 dark:text-white">Filialni yopish</h3>
            <p class="mt-2 text-sm text-ink-600 dark:text-ink-300">
                <b x-text="closing?.name"></b> yopiladi. Ma'lumotlar o'chmaydi, arxivda saqlanadi. Filial xodimlari tizimga kira olmaydi. Keyin qayta ochishingiz mumkin.
            </p>
            <form method="POST" :action="closing ? '{{ url('branches') }}/' + closing.id + '/close' : '#'" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="label" for="closed_reason">Sabab (ixtiyoriy)</label>
                    <input id="closed_reason" name="closed_reason" maxlength="255" class="input">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" @click="closing = null">Bekor qilish</button>
                    <button class="btn-primary">Yopish</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Butunlay o'chirish oynasi (v10, 7-band) --}}
    <div x-data="{ typed: '' }" x-show="deleting" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/60 p-4" @keydown.escape.window="deleting = null" x-effect="if (!deleting) typed = ''">
        <div class="card card-body w-full max-w-md shadow-pop" @click.outside="deleting = null">
            <h3 class="text-lg font-semibold text-red-700 dark:text-red-400">Filialni butunlay o'chirish</h3>
            <p class="mt-2 text-sm text-ink-600 dark:text-ink-300">
                <b x-text="deleting?.name"></b> va unga tegishli <b>BARCHA</b> ma'lumot (hodimlar, o'quvchilar, guruhlar,
                to'lovlar, kassa, lidlar, SMS, AI suhbatlar) <b>QAYTARIB BO'LMAYDIGAN</b> tarzda o'chiriladi. Bu amalni
                orqaga qaytarib bo'lmaydi.
            </p>
            <form method="POST" :action="deleting ? '{{ url('branches') }}/' + deleting.id : '#'" class="mt-4 space-y-4">
                @csrf
                @method('DELETE')
                <div>
                    <label class="label" for="confirm_name">Tasdiqlash uchun filial nomini aniq yozing: <b x-text="deleting?.name"></b></label>
                    <input id="confirm_name" name="confirm_name" x-model="typed" autocomplete="off" class="input" required>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" @click="deleting = null">Bekor qilish</button>
                    <button class="btn-danger" :disabled="typed !== deleting?.name">Butunlay o'chirish</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
