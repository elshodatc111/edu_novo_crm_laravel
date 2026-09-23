@extends('layouts.app')
@section('title', 'Ruxsatlar')

@section('content')
    <x-page-header :title="$staff->name.' — ruxsatlar'" :subtitle="$staff->role->label().' · '.($staff->branch?->name ?? '')">
        <x-slot:actions>
            <a href="{{ route('staff.index') }}" class="btn-secondary">Orqaga</a>
        </x-slot:actions>
    </x-page-header>

    @unless (auth()->user()->isSuperAdmin())
        <p class="mb-5 rounded-xl bg-ink-100 px-4 py-3 text-sm text-ink-600 dark:bg-ink-800 dark:text-ink-300">
            Siz faqat o'zingizda mavjud ruxsatlarni bera olasiz. Boshqa ruxsatlar o'chirilgan holda ko'rsatiladi.
        </p>
    @endunless

    <form method="POST" action="{{ route('staff.permissions.update', $staff) }}" x-data="{ all(v) { this.$root.querySelectorAll('input[name=\'permissions[]\']:not(:disabled)').forEach(c => c.checked = v) } }">
        @csrf @method('PUT')

        <div class="mb-4 flex gap-2">
            <button type="button" class="btn-secondary btn-sm" @click="all(true)">Barchasini belgilash</button>
            <button type="button" class="btn-secondary btn-sm" @click="all(false)">Belgini olib tashlash</button>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($groups as $group)
                <div class="card card-body">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400">{{ $group['label'] }}</h3>
                    <div class="space-y-2.5">
                        @foreach ($group['items'] as $key => $item)
                            @php $can = in_array($key, $grantable, true); @endphp
                            <label class="flex items-start gap-3 text-sm {{ $can ? 'cursor-pointer' : 'cursor-not-allowed opacity-50' }}">
                                <input type="checkbox" name="permissions[]" value="{{ $key }}" class="checkbox mt-0.5"
                                       @checked(in_array($key, $current, true)) @disabled(! $can)>
                                <span>{{ $item['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6"><button class="btn-primary">Saqlash</button></div>
    </form>
@endsection
