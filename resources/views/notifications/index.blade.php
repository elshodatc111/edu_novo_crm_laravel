@extends('layouts.app')
@section('title', 'Bildirishnomalar')

@section('content')
    <x-page-header title="Bildirishnomalar" subtitle="Mobil ilova foydalanuvchilariga push va ilova-ichi xabar yuborish" />

    <form method="POST" action="{{ route('notifications.store') }}" class="card card-body mb-6 grid gap-5 sm:grid-cols-2">
        @csrf
        <div>
            <label class="label" for="title">Sarlavha</label>
            <input id="title" name="title" class="input" maxlength="150" required value="{{ old('title') }}">
            @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <x-select name="branch_id" label="Kimga">
            <option value="">Barcha filial foydalanuvchilariga</option>
            @foreach ($branches as $b)
                <option value="{{ $b->id }}" @selected(old('branch_id') == $b->id)>{{ $b->name }} filiali</option>
            @endforeach
        </x-select>
        <div class="sm:col-span-2">
            <label class="label" for="body">Matn</label>
            <textarea id="body" name="body" rows="3" maxlength="1000" required class="input">{{ old('body') }}</textarea>
            @error('body')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div x-data="{ linkType: '{{ old('link_type', 'none') }}' }" class="sm:col-span-2 grid gap-5 sm:grid-cols-2">
            <div>
                <label class="label" for="link_type">Ilovada bosilganda qayerga o'tsin</label>
                <select id="link_type" name="link_type" class="input" x-model="linkType">
                    @foreach ($linkTypes as $key => $label)
                        <option value="{{ $key }}" @selected(old('link_type', 'none') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('link_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div x-show="linkType !== 'none'" x-cloak>
                <label class="label" for="link_id">ID (guruh/lid/o'quvchi raqami)</label>
                <input id="link_id" name="link_id" type="number" min="1" class="input" value="{{ old('link_id') }}">
                @error('link_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="sm:col-span-2 text-sm text-ink-500 dark:text-ink-400">
            Xabar tanlangan foydalanuvchilarning telefoniga push tarzida (agar Firebase sozlangan bo'lsa) va ilova ichidagi bildirishnomalar ro'yxatiga yuboriladi. sAdmin o'zi qabul qiluvchi bo'lmaydi.
        </div>
        <div class="sm:col-span-2"><button class="btn-primary">Yuborish</button></div>
    </form>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Sarlavha</th><th>Kimga</th><th>Havola</th><th>Qabul qiluvchi</th><th>O'qilgan</th><th>Yuborilgan</th></tr></thead>
                <tbody>
                @forelse ($history as $n)
                    <tr>
                        <td class="font-medium text-ink-900 dark:text-white">{{ $n->title }}</td>
                        <td>{{ $n->branch?->name ? $n->branch->name.' filiali' : 'Barchasi' }}</td>
                        <td class="text-ink-500 dark:text-ink-400">
                            @if (($n->data['link_type'] ?? 'none') !== 'none')
                                {{ $linkTypes[$n->data['link_type']] ?? $n->data['link_type'] }} #{{ $n->data['link_id'] }}
                            @else
                                &mdash;
                            @endif
                        </td>
                        <td>{{ $n->recipients_count }}</td>
                        <td>{{ $n->read_count }}</td>
                        <td class="whitespace-nowrap text-ink-500 dark:text-ink-400">{{ $n->created_at->format('d.m.Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-ink-400">Hali bildirishnoma yuborilmagan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $history->links() }}</div>
    </div>
@endsection
