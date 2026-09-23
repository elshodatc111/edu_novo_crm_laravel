/*
 * Edunova CRM grafiklari (Chart.js). Ma'lumot serverdan tayyor "spec" ko'rinishida keladi (App\Support\Viz),
 * jadval ko'rinishi ham xuddi shu ma'lumotdan quriladi.
 *
 * Qoidalar: ingichka ustunlar (<= 24px, uchi 4px yumaloq), 2px chiziqlar, ingichka jimjima setka,
 * 2 ta seriya va undan ko'p bo'lsa legenda, rang har bir "ob'ekt"ga bog'liq (tartibga emas),
 * yorug'/qorong'i rejimda CSS o'zgaruvchilaridan olinadi (--viz-*).
 */
import {
    Chart, BarController, BarElement, LineController, LineElement, PointElement,
    DoughnutController, ArcElement, CategoryScale, LinearScale, Tooltip, Legend, Filler,
} from 'chart.js';

Chart.register(BarController, BarElement, LineController, LineElement, PointElement, DoughnutController, ArcElement, CategoryScale, LinearScale, Tooltip, Legend, Filler);

const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

const colorOf = (d, i) => {
    if (d.color) return css(`--viz-${d.color}`) || d.color;
    return css(`--viz-s${d.slot || i + 1}`);
};

const nf = new Intl.NumberFormat('ru-RU');
export const fmt = (unit, v) => {
    if (v === null || v === undefined) return '—';
    if (unit === 'money') return nf.format(Math.round(v)) + " so'm";
    if (unit === 'percent') return nf.format(Math.round(v * 10) / 10) + '%';
    return nf.format(v);
};
const short = (unit, v) => {
    if (unit === 'percent') return v + '%';
    if (unit === 'money') {
        const a = Math.abs(v);
        if (a >= 1e9) return +(v / 1e9).toFixed(1) + ' mlrd';
        if (a >= 1e6) return +(v / 1e6).toFixed(1) + ' mln';
        if (a >= 1e3) return +(v / 1e3).toFixed(0) + ' ming';
    }
    return nf.format(v);
};

const centerText = {
    id: 'centerText',
    afterDraw(chart, _args, opts) {
        if (!opts || !opts.value) return;
        const { ctx, chartArea: a } = chart;
        const x = (a.left + a.right) / 2, y = (a.top + a.bottom) / 2;
        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = css('--viz-ink');
        ctx.font = '700 22px system-ui, -apple-system, "Segoe UI", sans-serif';
        ctx.fillText(opts.value, x, y - 8);
        ctx.fillStyle = css('--viz-muted');
        ctx.font = '500 12px system-ui, -apple-system, "Segoe UI", sans-serif';
        ctx.fillText(opts.label || '', x, y + 14);
        ctx.restore();
    },
};

const instances = new WeakMap();

export function mount(canvas) {
    const spec = JSON.parse(canvas.dataset.spec);
    instances.get(canvas)?.destroy();

    const surface = css('--viz-surface');
    const text = css('--viz-text');
    const muted = css('--viz-muted');
    const grid = css('--viz-grid');
    const unit = spec.unit || 'int';
    const font = { family: 'system-ui, -apple-system, "Segoe UI", sans-serif', size: 12 };
    const multi = spec.datasets.length > 1;
    const horizontal = spec.type === 'hbar';
    const isDonut = spec.type === 'donut';
    const isLine = spec.type === 'line' || spec.type === 'area';

    let datasets;
    if (isDonut) {
        const d = spec.datasets[0];
        datasets = [{
            data: d.data, label: d.label,
            backgroundColor: spec.labels.map((_, i) => colorOf(spec.colors?.[i] ? { color: spec.colors[i] } : { slot: i + 1 }, i)),
            borderColor: surface, borderWidth: 2, hoverOffset: 4,
        }];
    } else {
        datasets = spec.datasets.map((d, i) => {
            const c = colorOf(d, i);
            if (isLine) {
                return {
                    label: d.label, data: d.data, borderColor: c, backgroundColor: c + '1a', borderWidth: 2, tension: 0.25,
                    fill: spec.type === 'area' && !multi, pointRadius: 0, pointHoverRadius: 5, pointHoverBorderWidth: 2,
                    pointHoverBackgroundColor: c, pointHoverBorderColor: surface, borderJoinStyle: 'round', borderCapStyle: 'round',
                };
            }
            return {
                label: d.label, data: d.data, backgroundColor: c, maxBarThickness: 24,
                borderRadius: spec.stacked ? 0 : 4, borderSkipped: 'start',
                borderWidth: spec.stacked ? 2 : 0, borderColor: surface,
            };
        });
    }

    const scales = isDonut ? {} : {
        [horizontal ? 'y' : 'x']: {
            stacked: !!spec.stacked, grid: { display: false }, border: { color: grid },
            ticks: { color: muted, font, maxRotation: 0, autoSkip: horizontal ? false : spec.labels.length > 8, autoSkipPadding: 12 },
        },
        [horizontal ? 'x' : 'y']: {
            stacked: !!spec.stacked, beginAtZero: true, suggestedMax: spec.max || undefined, max: unit === 'percent' ? 100 : undefined,
            grid: { color: grid, lineWidth: 1 }, border: { display: false },
            ticks: { color: muted, font, maxTicksLimit: 6, callback: (v) => short(unit, v) },
        },
    };

    const chart = new Chart(canvas, {
        type: isDonut ? 'doughnut' : (isLine ? 'line' : 'bar'),
        data: { labels: spec.labels, datasets },
        options: {
            responsive: true, maintainAspectRatio: false, animation: { duration: 250 },
            indexAxis: horizontal ? 'y' : 'x',
            cutout: isDonut ? '68%' : undefined,
            interaction: isDonut ? { mode: 'nearest', intersect: true } : { mode: 'index', intersect: false },
            layout: { padding: { top: 4, right: 4 } },
            scales,
            plugins: {
                centerText: isDonut ? spec.center : null,
                legend: {
                    display: multi || isDonut, position: isDonut ? 'right' : 'top', align: isDonut ? 'center' : 'start',
                    labels: { color: text, font, usePointStyle: true, pointStyle: 'rectRounded', boxWidth: 10, boxHeight: 10, padding: 14 },
                },
                tooltip: {
                    backgroundColor: surface, titleColor: css('--viz-ink'), bodyColor: text, borderColor: grid, borderWidth: 1,
                    padding: 10, cornerRadius: 10, boxPadding: 4, usePointStyle: true, titleFont: { ...font, weight: '600' }, bodyFont: font,
                    callbacks: {
                        label: (ctx) => {
                            const v = isDonut ? ctx.parsed : (horizontal ? ctx.parsed.x : ctx.parsed.y);
                            let line = `${isDonut ? ctx.label : ctx.dataset.label}: ${fmt(unit, v)}`;
                            if (isDonut) {
                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                if (total) line += ` (${Math.round(v * 100 / total)}%)`;
                            }
                            return line;
                        },
                    },
                },
            },
        },
        plugins: [centerText],
    });

    instances.set(canvas, chart);
}

export function mountAll(root = document) {
    root.querySelectorAll('canvas[data-spec]').forEach((c) => mount(c));
}

mountAll();
window.addEventListener('themechange', () => mountAll());
window.EdunovaCharts = { mount, mountAll };
