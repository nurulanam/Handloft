{{-- Print-ready report: plain HTML + print CSS (A4), no app shell. Table headers repeat on every printed
     page and rows never split across pages. Reports with a wide table (9+ columns, e.g. the person × day
     grid) print on landscape A4 with a fixed, tighter table layout, so nothing is cut off at 100% scale.
     Opens the print dialog itself when ?autoprint=1. --}}
@php
    $landscape = collect($tables)->contains(fn ($table) => count($table['headers']) >= 9);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }} · {{ $period->label() }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <style>
        @page { size: A4 {{ $landscape ? 'landscape' : 'portrait' }}; margin: {{ $landscape ? '10mm' : '14mm 12mm' }}; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 11px/1.45 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #18181b; background: #f4f4f5; }
        .sheet { max-width: {{ $landscape ? '297mm' : '210mm' }}; margin: 24px auto; background: #fff; padding: 16mm 14mm; box-shadow: 0 10px 30px rgb(0 0 0 / .08); border-radius: 6px; }
        .toolbar { position: sticky; top: 0; z-index: 1; display: flex; justify-content: center; gap: 8px; padding: 12px; background: rgb(244 244 245 / .9); backdrop-filter: blur(8px); }
        .btn { font: 600 13px/1 inherit; padding: 9px 16px; border-radius: 8px; border: 1px solid #d4d4d8; background: #fff; color: #3f3f46; cursor: pointer; text-decoration: none; }
        .btn-primary { background: #10512a; border-color: #10512a; color: #fff; }
        header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding-bottom: 12px; border-bottom: 2px solid #10512a; }
        .brand { display: flex; align-items: center; gap: 10px; }
        .brand img { width: 34px; height: 34px; }
        .brand strong { display: block; font-size: 16px; }
        .brand span, .meta { color: #71717a; font-size: 10.5px; }
        .meta { text-align: right; }
        .meta b { color: #18181b; font-size: 12px; }
        h2 { margin: 18px 0 6px; font-size: 12.5px; color: #10512a; text-transform: uppercase; letter-spacing: .04em; break-after: avoid; }
        .kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 14px; }
        .kpi { border: 1px solid #e4e4e7; border-radius: 6px; padding: 8px 10px; }
        .kpi small { display: block; color: #71717a; font-size: 9.5px; text-transform: uppercase; letter-spacing: .03em; }
        .kpi b { display: block; font-size: 18px; margin-top: 2px; }
        .kpi em { font-style: normal; font-size: 9.5px; color: #71717a; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        th { text-align: left; background: #f4f4f5; color: #3f3f46; font-weight: 600; padding: 5px 6px; border-bottom: 1px solid #d4d4d8; white-space: nowrap; }
        td { padding: 4.5px 6px; border-bottom: 1px solid #f0f0f1; vertical-align: top; }
        tbody tr:nth-child(even) td { background: #fafafa; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        /* Wide tables: fixed layout so columns share the page width instead of overflowing it. */
        table.wide { table-layout: fixed; font-size: 8.5px; }
        table.wide th { white-space: normal; vertical-align: bottom; }
        table.wide th, table.wide td { padding: 4px 4px; overflow-wrap: anywhere; }
        /* The person × day grid: many narrow, centred number columns; a wider name column. */
        table.grid { font-size: 8px; }
        table.grid th, table.grid td { padding: 3px 1px; text-align: center; overflow-wrap: normal; }
        table.grid th:first-child, table.grid td:first-child { width: 30mm; text-align: left; padding-left: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        table.grid th:last-child, table.grid td:last-child { width: 13mm; text-align: right; padding-right: 4px; font-weight: 600; }
        table.grid tbody tr:last-child td { font-weight: 600; border-top: 1px solid #d4d4d8; }
        .empty { color: #a1a1aa; font-style: italic; padding: 10px 0; }
        footer { margin-top: 18px; padding-top: 8px; border-top: 1px solid #e4e4e7; color: #a1a1aa; font-size: 9.5px; display: flex; justify-content: space-between; }
        /* Full report: denser rows, so a month's daily trend and the projects table share one page. */
        body.dense table:not(.grid) { font-size: 9px; }
        body.dense table:not(.grid) th { padding: 2px 6px; line-height: 1.25; }
        body.dense table:not(.grid) td { padding: 1px 6px; line-height: 1.25; }
        body.dense h2 { margin: 10px 0 4px; }
        /* A new printed page; on screen, a dashed divider marks where it falls. */
        .page-break { break-before: page; margin: 22px -14mm 0; border-top: 1px dashed #d4d4d8; }
        @media print {
            .page-break { margin: 0; border: 0; }
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; padding: 0; max-width: none; box-shadow: none; border-radius: 0; }
            th, tbody tr:nth-child(even) td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body class="{{ $section === 'all' ? 'dense' : '' }}">
    <div class="toolbar">
        <button type="button" class="btn btn-primary" onclick="window.print()">Print / Save as PDF</button>
        <button type="button" class="btn" onclick="history.length > 1 ? history.back() : window.close()">Close</button>
    </div>

    <main class="sheet">
        <header>
            <div class="brand">
                <img src="/logo.svg" alt="">
                <div>
                    <strong>{{ $heading }}</strong>
                    <span>{{ config('app.name') }} · {{ ucfirst($period->type === 'custom' ? 'custom range' : $period->type) }} report</span>
                </div>
            </div>
            <div class="meta">
                <b>{{ $period->label() }}</b><br>
                {{ $period->start->format('d M Y') }} – {{ $period->end->format('d M Y') }}<br>
                Generated {{ now()->format('d M Y, H:i') }} by {{ auth()->user()->name }}
            </div>
        </header>

        @if ($overview)
            @php $k = $overview['kpis']; $p = $overview['previous']; @endphp
            <div class="kpis">
                @foreach ([
                    ['Tasks created', $k['created'], $p['created']],
                    ['Tasks completed', $k['completed'], $p['completed']],
                    ['Hours logged', \App\Support\Duration::forHumans($k['hours']), \App\Support\Duration::forHumans($p['hours'])],
                    ['On-time completion', $k['onTimeRate'] !== null ? $k['onTimeRate'].'%' : '—', $p['onTimeRate'] !== null ? $p['onTimeRate'].'%' : '—'],
                ] as [$label, $value, $before])
                    <div class="kpi"><small>{{ $label }}</small><b>{{ $value }}</b><em>Previous period: {{ $before }}</em></div>
                @endforeach
            </div>
        @endif

        @if ($person)
            <div class="kpis">
                @foreach ([
                    ['Hours logged', \App\Support\Duration::forHumans($person['hours']), $person['activeDays'].' active '.\Illuminate\Support\Str::plural('day', $person['activeDays'])],
                    ['Average / active day', \App\Support\Duration::forHumans($person['avgPerActiveDay']), $person['role']],
                    ['Tasks worked on', $person['tasksWorked'], 'with time logged'],
                    ['Tasks completed', $person['completedCount'], $person['onTimeRate'] !== null ? $person['onTimeRate'].'% on time' : 'no deadlines'],
                ] as [$label, $value, $note])
                    <div class="kpi"><small>{{ $label }}</small><b>{{ $value }}</b><em>{{ $note }}</em></div>
                @endforeach
            </div>
        @endif

        @foreach ($tables as $table)
            @if ($table['page_break'] ?? false)
                <div class="page-break" aria-hidden="true"></div>
            @endif
            <h2>{{ $table['title'] }}</h2>
            @if (count($table['rows']) === 0)
                <p class="empty">Nothing in this period.</p>
            @else
                @php
                    $headers = $table['print_headers'] ?? $table['headers'];
                    $isGrid = $table['blank_zero'] ?? false;
                    $isWide = count($headers) >= 9;
                    // Compact numbers for the grid ("6.8", blank for zero); two decimals elsewhere.
                    $show = fn ($cell) => match (true) {
                        $isGrid && (is_int($cell) || is_float($cell)) => (float) $cell == 0.0 ? '' : rtrim(rtrim(number_format((float) $cell, 1), '0'), '.'),
                        is_float($cell) => number_format($cell, 2),
                        default => $cell,
                    };
                    // A column right-aligns when every cell in it is a number (or a "—" placeholder).
                    $numeric = collect($table['headers'])->keys()->map(fn ($i) => collect($table['rows'])->every(fn ($row) => is_int($row[$i] ?? null) || is_float($row[$i] ?? null) || ($row[$i] ?? null) === '—')
                        && collect($table['rows'])->contains(fn ($row) => is_int($row[$i] ?? null) || is_float($row[$i] ?? null)))->all();
                @endphp
                <table class="{{ $isWide ? 'wide' : '' }} {{ $isGrid ? 'grid' : '' }}">
                    <thead>
                        <tr>
                            @foreach ($headers as $i => $header)
                                <th class="{{ $numeric[$i] ? 'num' : '' }}">{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($table['rows'] as $row)
                            <tr>
                                @foreach ($row as $i => $cell)
                                    <td class="{{ ($numeric[$i] ?? false) ? 'num' : '' }}">{{ $show($cell) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach

        <footer>
            <span>{{ config('app.name') }} · {{ $heading }}</span>
            <span>{{ $period->label() }}</span>
        </footer>
    </main>

    @if ($autoprint)
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
    @endif
</body>
</html>
