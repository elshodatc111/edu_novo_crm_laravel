@extends('layouts.app')
@section('title', 'Filiallar xodimlari')

@section('content')
    <x-page-header title="Filiallar xodimlari" subtitle="Barcha filiallar bo'yicha ism, lavozim va aloqa — faqat ko'rish" />

    @forelse ($staff as $branchId => $users)
        @php $branch = $branchId !== 'sadmin' ? ($branches[$branchId] ?? null) : null; @endphp
        <div class="card mb-4 overflow-hidden">
            <div class="flex items-center justify-between border-b border-ink-100 bg-ink-50 px-4 py-2.5 text-sm font-semibold text-ink-700 dark:border-ink-800 dark:bg-ink-800/40 dark:text-ink-200">
                <span>{{ $branch->name ?? 'sAdmin (barcha filiallar)' }}</span>
                @if ($branch)
                    <a href="{{ route('staff.directory.branch', $branch) }}" class="btn-ghost btn-sm shrink-0">O'quvchi, guruh va lidlarni ko'rish &rarr;</a>
                @endif
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>F.I.O</th><th>Lavozim</th><th>Telefon</th></tr></thead>
                    <tbody>
                    @foreach ($users as $u)
                        <tr>
                            <td class="font-medium text-ink-900 dark:text-white">{{ $u->name }}</td>
                            <td><span class="{{ $u->role->badgeClass() }}">{{ $u->role->label() }}</span></td>
                            <td>{{ $u->phone ? \App\Support\Format::prettyPhone($u->phone) : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="card card-body text-center text-sm text-ink-500">Xodimlar topilmadi.</div>
    @endforelse
@endsection
