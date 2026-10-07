<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shartnoma · Edunova</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <style>
        /* Rasmiy hujjat ko'rinishi: A4, serif shrift, ikki chetga tekislangan matn, 20 mm maydon */
        @page {
            size: A4; margin: 18mm 15mm 18mm 25mm;
            @bottom-center { content: counter(page) " / " counter(pages); font: 10pt "Times New Roman", serif; color: #555; }
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #fff; color: #000; }
        body { font-family: "Times New Roman", "Liberation Serif", Times, serif; font-size: 12pt; line-height: 1.38; }
        .bar { display: flex; justify-content: center; gap: 8px; padding: 10px; background: #f4f4f5; border-bottom: 1px solid #e4e4e7; font-family: system-ui, sans-serif; }
        .bar button { padding: 7px 18px; border: 0; border-radius: 8px; background: #dc2626; color: #fff; font-size: 14px; cursor: pointer; }
        .page { max-width: 180mm; margin: 0 auto; padding: 14mm 0 20mm; }
        h1 { margin: 0 0 10pt; text-align: center; font-size: 13.5pt; font-weight: 700; text-transform: uppercase; line-height: 1.3; }
        h2 { margin: 13pt 0 5pt; text-align: center; font-size: 12pt; font-weight: 700; text-transform: uppercase; break-after: avoid; page-break-after: avoid; }
        p { margin: 0 0 4pt; text-align: justify; text-indent: 10mm; hyphens: auto; orphans: 3; widows: 3; }
        .meta { display: flex; justify-content: space-between; gap: 12px; margin: 0 0 10pt; font-weight: 600; }
        table.sign { width: 100%; margin-top: 8pt; border-collapse: collapse; break-inside: avoid; page-break-inside: avoid; }
        table.sign td { width: 50%; padding: 1pt 12pt 1pt 0; vertical-align: top; word-break: break-word; }
        table.sign td.b { font-weight: 700; }
        table.sign tr.gap td { height: 12pt; }
        @media print { .bar { display: none; } .page { padding: 0; max-width: none; } }
    </style>
</head>
<body>
    <div class="bar"><button onclick="window.print()">Chop etish</button></div>

    <main class="page">{!! $html !!}</main>

    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
</body>
</html>
