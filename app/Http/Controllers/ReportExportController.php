<?php

namespace App\Http\Controllers;

use App\Reports\ReportBuilder;
use App\Reports\ReportExport;
use App\Reports\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReportExportController extends Controller
{
    /**
     * CSV or Excel download of a report section (Excel only for the full "all" workbook).
     */
    public function export(Request $request, string $section, string $format)
    {
        Gate::authorize('export-data');

        abort_unless(in_array($section, ReportExport::SECTIONS, true) && in_array($format, ['csv', 'xlsx'], true), 404);
        abort_if($section === 'all' && $format === 'csv', 404);

        $export = new ReportExport(new ReportBuilder($this->period($request)));

        return $format === 'csv'
            ? $export->csv($section, $this->filters($request))
            : $export->xlsx($section, $this->filters($request));
    }

    /**
     * A clean, print-ready page (A4) of a report section. Opens the print dialog automatically when ?autoprint=1.
     */
    public function print(Request $request)
    {
        $section = in_array($request->query('section'), ReportExport::SECTIONS, true) ? $request->query('section') : 'overview';

        // Everyone can print their own report; anything else needs view-reports.
        $ownReport = $section === 'person' && (int) $request->query('user') === $request->user()->id;
        abort_unless($ownReport || $request->user()->can('view-reports'), 403);

        $builder = new ReportBuilder($this->period($request));
        $export = new ReportExport($builder);

        return view('reports.print', [
            'section' => $section,
            'heading' => $export->heading($section, $this->filters($request)),
            'period' => $builder->period,
            'overview' => in_array($section, ['overview', 'all'], true) ? $builder->overview() : null,
            'person' => $section === 'person' ? $builder->person($export->person($this->filters($request))) : null,
            'tables' => $export->tables($section, $this->filters($request)),
            'autoprint' => $request->boolean('autoprint'),
        ]);
    }

    private function period(Request $request): ReportPeriod
    {
        return ReportPeriod::fromInput($request->query('period'), $request->query('date'), $request->query('from'), $request->query('to'));
    }

    /**
     * @return array{project: ?string, status: ?string, assignee: ?string, search: ?string, user: ?string}
     */
    private function filters(Request $request): array
    {
        return [
            'project' => $request->query('project'),
            'status' => $request->query('status'),
            'assignee' => $request->query('assignee'),
            'search' => $request->query('q'),
            'user' => $request->query('user'),
        ];
    }
}
