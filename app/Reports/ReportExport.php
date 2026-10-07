<?php

namespace App\Reports;

use App\Models\User;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Turns a report section into named tables, and those tables into CSV or
 * Excel downloads. The print view renders the very same tables.
 */
final class ReportExport
{
    public const SECTIONS = ['overview', 'tasks', 'projects', 'people', 'person', 'all'];

    public function __construct(private readonly ReportBuilder $builder) {}

    /**
     * @param  array<string, mixed>  $filters  task filters (project, status, assignee, search) and, for "person", the user id
     * @return list<array{name: string, title: string, headers: list<string>, rows: list<list<mixed>>}>
     */
    public function tables(string $section, array $filters = []): array
    {
        $tables = [];

        if ($section === 'person') {
            return $this->builder->personTables($this->person($filters));
        }

        if (in_array($section, ['overview', 'all'], true)) {
            $overview = $this->builder->overview();
            $summary = $this->builder->summaryRows($overview);

            $tables[] = ['name' => 'Summary', 'title' => 'Summary', 'headers' => array_shift($summary), 'rows' => $summary];
            $tables[] = [
                'name' => 'Trend',
                'title' => 'Trend by '.$this->builder->period->granularity(),
                'headers' => ['Period', 'Hours logged', 'Tasks created', 'Tasks completed'],
                'rows' => array_map(fn ($b) => [$b['long'], round($b['hours'], 2), $b['created'], $b['completed']], $overview['series']),
            ];
            $tables[] = [
                'name' => 'Status of new tasks',
                'title' => 'Current status of tasks created in the period',
                'headers' => ['Status', 'Tasks'],
                'rows' => $overview['statusBreakdown']->map(fn ($row) => [$row['status']->label(), $row['count']])->all(),
            ];
            $tables[] = [
                'name' => 'Hours by project',
                'title' => 'Hours by project',
                'headers' => ['Project', 'Hours'],
                'rows' => $this->builder->hoursByProject()->map(fn ($row) => [$row['name'], round($row['hours'], 2)])->all(),
            ];
        }

        if (in_array($section, ['tasks', 'all'], true)) {
            $tables[] = ['name' => 'Tasks', 'title' => 'Tasks', 'headers' => ReportBuilder::TASK_COLUMNS, 'rows' => $this->builder->taskRows($section === 'all' ? [] : $filters)];
        }

        if (in_array($section, ['projects', 'all'], true)) {
            $tables[] = ['name' => 'Projects', 'title' => 'Projects', 'headers' => ReportBuilder::PROJECT_COLUMNS, 'rows' => $this->builder->projectRows()];
        }

        if (in_array($section, ['people', 'all'], true)) {
            $tables[] = ['name' => 'People', 'title' => 'People', 'headers' => ReportBuilder::PEOPLE_COLUMNS, 'rows' => $this->builder->peopleRows()];

            $grid = $this->builder->dailyHoursTable();
            $tables[] = ['name' => 'Daily hours', 'title' => 'Hours per person by '.$this->builder->period->granularity(), 'headers' => $grid['headers'], 'rows' => $grid['rows']];
        }

        return $tables;
    }

    public function filename(string $section, string $extension, array $filters = []): string
    {
        $subject = $section === 'person' ? 'person-'.Str::slug($this->person($filters)->name) : $section;

        return 'handloft-'.$subject.'-report-'.$this->builder->period->slug().'.'.$extension;
    }

    public function heading(string $section, array $filters = []): string
    {
        return config('app.name').' — '.match ($section) {
            'all' => 'Full report',
            'person' => $this->person($filters)->name."'s report",
            default => ucfirst($section).' report',
        };
    }

    /**
     * The person a "person" report is about; 404 when the id is missing or unknown.
     */
    public function person(array $filters): User
    {
        return User::findOrFail((int) ($filters['user'] ?? 0));
    }

    public function csv(string $section, array $filters = []): StreamedResponse
    {
        $tables = $this->tables($section, $filters);
        $period = $this->builder->period;

        return response()->streamDownload(function () use ($tables, $section, $period, $filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens accented names correctly

            fputcsv($out, [$this->heading($section, $filters)]);
            fputcsv($out, ['Period', $period->label(), 'Generated', now()->format('Y-m-d H:i')]);

            foreach ($tables as $table) {
                fputcsv($out, []);
                if (count($tables) > 1) {
                    fputcsv($out, [$table['title']]);
                }
                fputcsv($out, $table['headers']);
                foreach ($table['rows'] as $row) {
                    fputcsv($out, array_map(self::csvSafe(...), $row));
                }
            }

            fclose($out);
        }, $this->filename($section, 'csv', $filters), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function xlsx(string $section, array $filters = []): BinaryFileResponse
    {
        $writer = new XlsxWriter;
        $subtitle = $this->builder->period->label().' · generated '.now()->format('d M Y H:i');

        foreach ($this->tables($section, $filters) as $table) {
            $writer->addSheet($table['name'], $this->heading($section, $filters).' — '.$table['title'], $subtitle, $table['headers'], $table['rows']);
        }

        return response()->download($writer->save(), $this->filename($section, 'xlsx', $filters), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * Neutralises spreadsheet formula injection: a text cell starting with =, +, -, @, tab or CR
     * is prefixed with an apostrophe so spreadsheet apps treat it as text, not a formula.
     */
    public static function csvSafe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
