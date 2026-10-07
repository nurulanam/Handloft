{{-- Print-ready report: plain HTML + print CSS (A4), no app shell. Table headers repeat on every printed
     page and rows never split across pages. Opens the print dialog itself when ?autoprint=1. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }} · {{ $period->label() }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <style>
        @page { size: A4; margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 11px/1.45 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #18181b; background: #f4f4f5; }
        .sheet { max-width: 210mm; margin: 24px auto; background: #fff; padding: 16mm 14mm; box-shadow: 0 10px 30px rgb(0 0 0 / .08); border-radius: 6px; }
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
        .empty { color: #a1a1aa; font-style: italic; padding: 10px 0; }
        footer { margin-top: 18px; padding-top: 8px; border-top: 1px solid #e4e4e7; color: #a1a1aa; font-size: 9.5px; display: flex; justify-content: space-between; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; padding: 0; max-width: none; box-shadow: none; border-radius: 0; }
            th, tbody tr:nth-child(even) td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
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
            <h2>{{ $table['title'] }}</h2>
            @if (count($table['rows']) === 0)
                <p class="empty">Nothing in this period.</p>
            @else
                @php
                    // A column right-aligns when every cell in it is a number (or a "—" placeholder).
                    $numeric = collect($table['headers'])->keys()->map(fn ($i) => collect($table['rows'])->every(fn ($row) => is_int($row[$i] ?? null) || is_float($row[$i] ?? null) || ($row[$i] ?? null) === '—')
                        && collect($table['rows'])->contains(fn ($row) => is_int($row[$i] ?? null) || is_float($row[$i] ?? null)))->all();
                @endphp
                <table>
                    <thead>
                        <tr>
                            @foreach ($table['headers'] as $i => $header)
                                <th class="{{ $numeric[$i] ? 'num' : '' }}">{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($table['rows'] as $row)
                            <tr>
                                @foreach ($row as $i => $cell)
                                    <td class="{{ ($numeric[$i] ?? false) ? 'num' : '' }}">{{ is_float($cell) ? number_format($cell, 2) : $cell }}</td>
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
