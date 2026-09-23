@extends('layouts.app')
@section('title', $def['title'])

@section('content')
    <x-page-header :title="$def['title']" />
    @if ($catalog !== 'courses') @include('partials.settings-tabs') @endif

    @php($canManage = auth()->user()->can($def['permission']))
    <div class="grid gap-6 lg:grid-cols-3">
        @if ($canManage)
        <form method="POST" action="{{ route('catalog.store', $catalog) }}" class="card card-body h-fit space-y-4">
            @csrf
            <h2 class="text-base font-semibold text-ink-900 dark:text-white">Yangi: {{ mb_strtolower($def['singular']) }}</h2>
            @foreach ($def['fields'] as $name => $f)
                <x-input :name="$name" :label="$f['label']" :type="$f['type'] === 'money' ? 'text' : $f['type']" :money="$f['type'] === 'money'" required />
            @endforeach
            <button class="btn-primary w-full"><x-icon name="plus" class="h-4 w-4" /> Qo'shish</button>
        </form>
        @endif

        <div class="card overflow-hidden {{ $canManage ? 'lg:col-span-2' : 'lg:col-span-3' }}">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        @foreach ($def['fields'] as $f)<th>{{ $f['label'] }}</th>@endforeach
                        <th>Holat</th><th class="text-right">Amallar</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($items as $item)
                        <tr x-data="{ edit: false }" class="{{ $item->is_active ? '' : 'opacity-60' }}">
                            @foreach ($def['fields'] as $name => $f)
                                <td>
                                    <span x-show="!edit">
                                        @if ($f['type'] === 'money'){{ \App\Support\Format::money($item->$name) }}
                                        @elseif ($f['type'] === 'time'){{ substr($item->$name, 0, 5) }}
                                        @elseif ($f['type'] === 'date'){{ $item->$name->format('d.m.Y') }}
                                        @else<span class="font-medium text-ink-900 dark:text-white">{{ $item->$name }}</span>@endif
                                    </span>
                                    @if ($canManage)
                                    <input x-show="edit" x-cloak form="edit-{{ $item->id }}" name="{{ $name }}" type="{{ $f['type'] === 'money' ? 'text' : $f['type'] }}" @if ($f['type'] === 'money') inputmode="numeric" data-money @endif
                                           value="{{ $f['type'] === 'time' ? substr($item->$name, 0, 5) : ($f['type'] === 'date' ? $item->$name->format('Y-m-d') : ($f['type'] === 'money' ? number_format($item->$name, 0, '', ' ') : $item->$name)) }}" class="input py-1.5" required>
                                    @endif
                                </td>
                            @endforeach
                            <td><span class="{{ $item->is_active ? 'badge-green' : 'badge-gray' }}">{{ $item->is_active ? 'Faol' : 'Faol emas' }}</span></td>
                            <td>
                                <div class="flex justify-end gap-1">
                                    @if ($catalog === 'courses')<a href="{{ route('courses.show', $item->id) }}" class="btn-secondary btn-sm">Materiallar</a>@endif
                                    @if ($canManage)
                                    <button type="button" x-show="!edit" class="btn-ghost btn-sm" @click="edit = true"><x-icon name="edit" class="h-4 w-4" /></button>
                                    <button x-show="edit" x-cloak form="edit-{{ $item->id }}" class="btn-primary btn-sm">Saqlash</button>
                                    <button type="button" x-show="edit" x-cloak class="btn-ghost btn-sm" @click="edit = false">Bekor</button>
                                    <button form="toggle-{{ $item->id }}" class="btn-ghost btn-sm">{{ $item->is_active ? 'Faolsizlantirish' : 'Faollashtirish' }}</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($def['fields']) + 2 }}" class="py-10 text-center text-ink-500">Hali kiritilmagan.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($canManage)
            @foreach ($items as $item)
                <form id="edit-{{ $item->id }}" method="POST" action="{{ route('catalog.update', [$catalog, $item->id]) }}" class="hidden">@csrf @method('PUT')</form>
                <form id="toggle-{{ $item->id }}" method="POST" action="{{ route('catalog.toggle', [$catalog, $item->id]) }}" class="hidden">@csrf</form>
            @endforeach
            @endif
        </div>
    </div>
@endsection
