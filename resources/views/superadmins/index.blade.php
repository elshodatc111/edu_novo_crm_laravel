@extends('layouts.app')
@section('title', 'Super adminlar')

@section('content')
    <x-page-header title="Super adminlar" subtitle="Barcha filiallarni boshqara oladigan foydalanuvchilar">
        <x-slot:actions><a href="{{ route('superadmins.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> sAdmin qo'shish</a></x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>F.I.O</th><th>Login</th><th>Telefon</th><th>Holat</th><th>Oxirgi kirish</th><th class="text-right">Amallar</th></tr></thead>
                <tbody>
                @foreach ($admins as $a)
                    @continue($a->archived_at)
                    <tr>
                        <td class="font-medium text-ink-900 dark:text-white">{{ $a->name }} @if ($a->is(auth()->user()))<span class="badge-gray ml-1">siz</span>@endif</td>
                        <td>{{ $a->username }}</td>
                        <td>{{ $a->phone ?? '—' }}</td>
                        <td><span class="{{ $a->isActive() ? 'badge-green' : 'badge-gray' }}">{{ $a->status->label() }}</span></td>
                        <td class="text-xs text-ink-500">{{ $a->last_login_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td>
                            @unless ($a->is(auth()->user()))
                                <div class="flex justify-end gap-1">
                                    <form method="POST" action="{{ route('superadmins.toggle', $a->id) }}">@csrf
                                        <button class="btn-ghost btn-sm">{{ $a->isActive() ? 'Bloklash' : 'Faollashtirish' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('superadmins.destroy', $a->id) }}" onsubmit="return confirm('Bu sAdmin olib tashlansinmi? U tizimga kira olmaydi.')">@csrf @method('DELETE')
                                        <button class="btn-ghost btn-sm text-brand-600">Olib tashlash</button>
                                    </form>
                                </div>
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <p class="mt-4 text-xs text-ink-500">sAdmin barcha filiallarga, ruxsatlarga va sozlamalarga to'liq kiradi. Oxirgi faol sAdmin'ni bloklab yoki olib tashlab bo'lmaydi. Olib tashlangan sAdmin'ning tarixi (jurnal, to'lovlar) saqlanib qoladi.</p>
@endsection
