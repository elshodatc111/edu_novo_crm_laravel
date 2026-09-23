{{--
    Voronka (SVG). $stages: [['label' => ..., 'value' => int], ...] — birinchisi eng katta.
    Har bir bosqichda: soni, umumiy ichidagi ulushi va oldingi bosqichdan o'tish foizi.
--}}
@props(['stages', 'height' => 62, 'width' => 640])
@php
    $W = (int) $width; $gap = 4; $n = count($stages);
    $max = max(1, (int) ($stages[0]['value'] ?? 0));
    $H = $n * $height + ($n - 1) * $gap;
    $minW = max(120, (int) round($W * 0.3));   // eng tor bosqichda ham yozuv sig'ishi uchun
    $widthOf = fn (int $v) => max($minW, (int) round($W * $v / $max));
    $fills = ['var(--viz-f1)', 'var(--viz-f2)', 'var(--viz-f3)', 'var(--viz-f4)'];
    $dark = ['#0b0b0b', '#ffffff', '#ffffff', '#ffffff'];
@endphp
<div class="w-full">
    <svg viewBox="0 0 {{ $W }} {{ $H }}" class="w-full" role="img" aria-label="Varonka: {{ collect($stages)->map(fn ($s) => $s['label'].' '.$s['value'])->implode(', ') }}">
        @foreach ($stages as $i => $s)
            @php
                $top = $widthOf((int) $s['value']);
                $bottom = isset($stages[$i + 1]) ? $widthOf((int) $stages[$i + 1]['value']) : max((int) round($minW * 0.8), (int) round($top * 0.86));
                $y = $i * ($height + $gap);
                $cx = $W / 2;
                $pts = sprintf('%.1f,%d %.1f,%d %.1f,%d %.1f,%d', $cx - $top / 2, $y, $cx + $top / 2, $y, $cx + $bottom / 2, $y + $height, $cx - $bottom / 2, $y + $height);
                $share = $max ? round($s['value'] * 100 / $max) : 0;
                $conv = $i > 0 && ($stages[$i - 1]['value'] ?? 0) > 0 ? round($s['value'] * 100 / $stages[$i - 1]['value']) : null;
                $ci = min($i, 3);
            @endphp
            <g>
                <title>{{ $s['label'] }}: {{ $s['value'] }} ({{ $share }}%){{ $conv !== null ? ", oldingi bosqichdan {$conv}%" : '' }}</title>
                <polygon points="{{ $pts }}" fill="{{ $fills[$ci] }}" />
                <text x="{{ $cx }}" y="{{ $y + $height / 2 - 6 }}" text-anchor="middle" font-size="13" font-weight="600" fill="{{ $dark[$ci] }}">{{ $s['label'] }}</text>
                <text x="{{ $cx }}" y="{{ $y + $height / 2 + 14 }}" text-anchor="middle" font-size="18" font-weight="700" fill="{{ $dark[$ci] }}">{{ number_format($s['value'], 0, '', ' ') }} <tspan font-size="12" font-weight="500" opacity=".85">· {{ $share }}%</tspan></text>
            </g>
        @endforeach
    </svg>
    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-ink-500 dark:text-ink-400">
        @foreach ($stages as $i => $s)
            @if ($i > 0 && ($stages[$i - 1]['value'] ?? 0) > 0)
                <span>{{ $stages[$i - 1]['label'] }} → {{ $s['label'] }}: <b class="text-ink-800 dark:text-ink-100">{{ round($s['value'] * 100 / $stages[$i - 1]['value']) }}%</b></span>
            @endif
        @endforeach
    </div>
</div>
