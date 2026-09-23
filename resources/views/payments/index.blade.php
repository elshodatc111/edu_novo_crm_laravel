@extends('layouts.app')
@section('title', "To'lovlar")

@section('content')
    <x-page-header title="To'lovlar" subtitle="To'lov qabul qilish o'quvchi sahifasidan amalga oshiriladi" />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([["Naqt", $totals->cash, 'text-emerald-600'], ["Plastik", $totals->card, 'text-emerald-600'], ["Chegirma va bonus", $totals->discounts, 'text-amber-600'], ["Qaytarilgan", $totals->refunds, 'text-brand-600']] as [$l, $v, $c])
            <div class="card card-body"><div class="text-sm text-ink-500">{{ $l }}</div><div class="mt-1 text-xl font-bold {{ $c }}">{{ \App\Support\Format::money($v) }}</div></div>
        @endforeach
    </div>

    <div class="card mt-6 overflow-hidden">
        <form method="GET" class="grid gap-3 border-b border-ink-100 p-4 dark:border-ink-800 md:grid-cols-6">
            <input type="date" name="from" value="{{ $from }}" class="input">
            <input type="date" name="to" value="{{ $to }}" class="input">
            <select name="type" class="input"><option value="">Barcha turlar</option>
                @foreach (['payment' => "To'lov", 'discount' => 'Chegirma', 'campaign_bonus' => 'Aksiya bonusi', 'refund' => 'Qaytarildi', 'reversal' => 'Storno'] as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach
            </select>
            <select name="method" class="input"><option value="">Naqt va plastik</option>
                <option value="cash" @selected(request('method') === 'cash')>Naqt</option><option value="card" @selected(request('method') === 'card')>Plastik</option>
            </select>
            <select name="created_by" class="input"><option value="">Barcha kassirlar</option>
                @foreach ($cashiers as $c)<option value="{{ $c->id }}" @selected(request('created_by') == $c->id)>{{ $c->name }}</option>@endforeach
            </select>
            <div class="flex gap-2"><input name="q" value="{{ \App\Support\SafeInput::string(request('q')) }}" placeholder="O'quvchi" class="input"><button class="btn-secondary">Saralash</button></div>
        </form>

        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Sana</th><th>O'quvchi</th><th>Tur</th><th>Guruh</th><th class="text-right">Summa</th><th>Kassir</th><th>Izoh</th><th></th></tr></thead>
                <tbody>
                @forelse ($payments as $p)
                    <tr>
                        <td class="whitespace-nowrap">{{ $p->created_at->format('d.m.Y H:i') }}</td>
                        <td><a href="{{ route('students.show', $p->student) }}" class="font-medium text-ink-900 hover:text-brand-600 dark:text-white">{{ $p->student->name }}</a></td>
                        <td>
                            <span class="{{ ['payment' => 'badge-green', 'discount' => 'badge-amber', 'campaign_bonus' => 'badge-amber', 'refund' => 'badge-red', 'reversal' => 'badge-gray'][$p->type] ?? 'badge-gray' }}">{{ $p->typeLabel() }}</span>
                            @if ($p->method)<span class="text-xs text-ink-400">{{ $p->method->label() }}</span>@endif
                            @if ($p->reversed_at)<span class="badge-gray text-xs">Stornolangan</span>@endif
                            @if ($p->refund_rejected_at)<span class="badge-gray text-xs">Rad etilgan</span>@endif
                        </td>
                        <td>{{ $p->group?->name ?? '—' }}</td>
                        <td class="text-right font-semibold">{{ $p->type === 'refund' ? '−' : '' }}{{ \App\Support\Format::money($p->amount, false) }}</td>
                        <td>{{ $p->creator?->name }}</td>
                        <td class="max-w-xs truncate text-ink-500" title="{{ $p->description }}">{{ $p->description }}</td>
                        <td class="text-right"><a href="{{ route('payments.receipt', $p) }}" target="_blank" class="btn-ghost btn-sm">Chek</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-12 text-center text-ink-500">Bu davrda to'lov yo'q.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $payments->links() }}
    </div>
@endsection
