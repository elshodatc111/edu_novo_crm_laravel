@php $today = today()->toDateString(); $canEditPast = auth()->user()->can('editPastAttendance', $group); @endphp
<div class="table-wrap">
    <table class="table">
        <thead>
        <tr>
            <th class="sticky left-0 z-10 bg-ink-50 dark:bg-ink-900">O'quvchi</th>
            @foreach ($matrix['days'] as $d)
                @php
                    $cls = match ($d['state']) {
                        'held' => '', 'missed' => 'text-amber-600', 'today' => 'text-brand-600', default => 'text-ink-300',
                    };
                @endphp
                <th class="text-center {{ $cls }}" title="{{ ['held' => 'Davomad olingan', 'missed' => 'Davomad olinmagan', 'today' => 'Bugun', 'upcoming' => 'Kelgusi dars'][$d['state']] }}">
                    @if ($canEditPast && in_array($d['state'], ['held', 'missed'], true))
                        <a href="{{ route('attendance.past.show', ['group' => $group, 'date' => $d['date']]) }}" class="hover:underline">{{ \Carbon\Carbon::parse($d['date'])->format('d.m') }}</a>
                    @else
                        {{ \Carbon\Carbon::parse($d['date'])->format('d.m') }}
                    @endif
                </th>
            @endforeach
            <th class="text-center">%</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($matrix['rows'] as $row)
            <tr>
                <td class="sticky left-0 whitespace-nowrap bg-white font-medium text-ink-900 dark:bg-ink-900 dark:text-white">
                    {{ $row['student']->name }} @unless ($row['active'])<span class="badge-gray ml-1">chiqqan</span>@endunless
                </td>
                @foreach ($matrix['days'] as $d)
                    <td class="text-center">
                        @if (array_key_exists($d['date'], $row['cells']))
                            @if ($row['cells'][$d['date']])<span class="text-emerald-600" title="Keldi">●</span>@else<span class="text-brand-600" title="Kelmadi">✕</span>@endif
                        @else<span class="text-ink-300">·</span>@endif
                    </td>
                @endforeach
                <td class="text-center font-semibold">{{ $row['rate'] === null ? '—' : $row['rate'].'%' }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ count($matrix['days']) + 2 }}" class="py-10 text-center text-ink-500">Davomad ma'lumoti yo'q.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<p class="px-5 pb-4 pt-2 text-xs text-ink-400"><span class="text-emerald-600">●</span> keldi &nbsp; <span class="text-brand-600">✕</span> kelmadi &nbsp; · yozuv yo'q &nbsp; <span class="text-amber-600">sariq sana</span> — davomad olinmagan</p>
