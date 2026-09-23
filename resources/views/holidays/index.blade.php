@extends('layouts.app')
@section('title', 'Dam olish kunlari')

@section('content')
    <x-page-header title="Dam olish kunlari" subtitle="Guruh dars kunlari hisoblanganda bu sanalar o'tkazib yuboriladi" />
    @include('partials.settings-tabs')

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <form method="POST" action="{{ route('holidays.store') }}" class="card card-body space-y-4">
                @csrf
                <h2 class="text-base font-semibold text-ink-900 dark:text-white">Kun qo'shish</h2>
                <x-input name="date" type="date" label="Sana" required />
                <x-input name="comment" label="Izoh" />
                <button class="btn-primary w-full"><x-icon name="plus" class="h-4 w-4" /> Qo'shish</button>
            </form>

            <form method="POST" action="{{ route('holidays.generate') }}" class="card card-body space-y-3">
                @csrf
                <h2 class="text-base font-semibold text-ink-900 dark:text-white">Avtomatik to'ldirish</h2>
                <p class="text-sm text-ink-500 dark:text-ink-400">Bugundan boshlab 1 yil uchun barcha yakshanba va rasmiy bayramlar qo'shiladi. Mavjud kunlar o'zgarmaydi.</p>
                <button class="btn-secondary w-full">1 yilga to'ldirish</button>
            </form>
        </div>

        <div class="card overflow-hidden lg:col-span-2">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Sana</th><th>Izoh</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($holidays as $h)
                        <tr>
                            <td class="whitespace-nowrap font-medium text-ink-900 dark:text-white">{{ $h->date->format('d.m.Y') }} <span class="text-xs font-normal text-ink-400">{{ $h->date->translatedFormat('l') }}</span></td>
                            <td>{{ $h->comment }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('holidays.destroy', $h) }}" onsubmit="return confirm('O\'chirilsinmi?')">@csrf @method('DELETE')
                                    <button class="btn-ghost btn-sm" title="O'chirish"><x-icon name="trash" class="h-4 w-4" /></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-10 text-center text-ink-500">Hali kiritilmagan. "1 yilga to'ldirish" tugmasini bosing.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $holidays->links() }}
        </div>
    </div>
@endsection
