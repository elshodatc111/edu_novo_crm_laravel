@props(['title', 'subtitle' => null, 'spec', 'table' => null, 'height' => 'h-72'])
@php
    $table ??= \App\Support\Viz::table($spec);
    $empty = \App\Support\Viz::isEmpty($spec);
    $hStyle = is_numeric($height) ? 'height: '.$height.'px' : null;
    $hClass = is_numeric($height) ? '' : $height;
@endphp
<div {{ $attributes->class(['card card-body flex flex-col']) }} x-data="{ view: 'chart' }">
    <div class="mb-4 flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h3 class="text-base font-semibold text-ink-900 dark:text-white">{{ $title }}</h3>
            @if ($subtitle)<p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">{{ $subtitle }}</p>@endif
        </div>
        <div class="flex shrink-0 rounded-lg bg-ink-100 p-0.5 text-xs font-semibold dark:bg-ink-800" role="tablist" aria-label="Ko'rinish">
            <button type="button" role="tab" :aria-selected="view === 'chart'" @click="view = 'chart'" :class="view === 'chart' ? 'bg-white text-brand-700 shadow-sm dark:bg-ink-900 dark:text-brand-300' : 'text-ink-500'" class="rounded-md px-2.5 py-1">Grafik</button>
            <button type="button" role="tab" :aria-selected="view === 'table'" @click="view = 'table'" :class="view === 'table' ? 'bg-white text-brand-700 shadow-sm dark:bg-ink-900 dark:text-brand-300' : 'text-ink-500'" class="rounded-md px-2.5 py-1">Jadval</button>
        </div>
    </div>

    <div x-show="view === 'chart'" class="flex-1">
        @if ($empty)
            <div @if($hStyle) style="{{ $hStyle }}" @endif class="flex {{ $hClass }} items-center justify-center rounded-xl bg-ink-50 text-sm text-ink-400 dark:bg-ink-800/40">Bu davrda ma'lumot yo'q</div>
        @else
            <div class="relative {{ $hClass }}" @if($hStyle) style="{{ $hStyle }}" @endif>
                <canvas data-spec="{{ json_encode($spec, JSON_UNESCAPED_UNICODE) }}" role="img" aria-label="{{ $title }}"></canvas>
            </div>
        @endif
    </div>

    <div x-show="view === 'table'" x-cloak class="flex-1">
        <div class="table-wrap max-h-[26rem] overflow-y-auto rounded-xl border border-ink-100 dark:border-ink-800">
            <table class="table">
                <thead><tr>@foreach ($table['columns'] as $i => $c)<th class="{{ $i > 0 ? 'text-right' : '' }}">{{ $c }}</th>@endforeach</tr></thead>
                <tbody>
                @forelse ($table['rows'] as $row)
                    <tr>@foreach ($row as $i => $cell)
                        <td class="{{ $i > 0 ? 'text-right tabular-nums' : 'font-medium text-ink-900 dark:text-white' }}">
                            @if (is_array($cell) && isset($cell['href']))<a href="{{ $cell['href'] }}" class="hover:text-brand-600">{{ $cell['text'] }}</a>@else{{ is_array($cell) ? $cell['text'] : $cell }}@endif
                        </td>
                    @endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($table['columns']) }}" class="py-8 text-center text-ink-400">Ma'lumot yo'q</td></tr>
                @endforelse
                </tbody>
                @if (! empty($table['foot']))
                    <tfoot><tr class="bg-ink-50 font-semibold dark:bg-ink-800/50">@foreach ($table['foot'] as $i => $cell)<td class="px-4 py-3 {{ $i > 0 ? 'text-right tabular-nums' : '' }}">{{ $cell }}</td>@endforeach</tr></tfoot>
                @endif
            </table>
        </div>
    </div>

    {{ $slot }}
</div>
