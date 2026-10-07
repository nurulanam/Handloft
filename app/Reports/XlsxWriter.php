<?php

namespace App\Reports;

use ZipArchive;

/**
 * A small, dependency-free writer for real .xlsx (Office Open XML) workbooks:
 * several sheets, each with a title block, a bold header row that stays frozen
 * while scrolling, typed number cells and sensible column widths. Strings are
 * written as inline strings, so cell text is never interpreted as a formula.
 */
final class XlsxWriter
{
    /** @var list<array{name: string, title: string, subtitle: string, headers: list<string>, rows: list<list<mixed>>}> */
    private array $sheets = [];

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     */
    public function addSheet(string $name, string $title, string $subtitle, array $headers, array $rows): self
    {
        $name = trim(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $name)) ?: 'Sheet';
        $this->sheets[] = ['name' => mb_substr($name, 0, 31), 'title' => $title, 'subtitle' => $subtitle, 'headers' => $headers, 'rows' => $rows];

        return $this;
    }

    /**
     * Writes the workbook to a temporary file and returns its path.
     */
    public function save(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $count = count($this->sheets);

        $zip->addFromString('[Content_Types].xml', $this->contentTypes($count));
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels($count));
        $zip->addFromString('xl/styles.xml', $this->styles());

        foreach ($this->sheets as $index => $sheet) {
            $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $this->sheet($sheet));
        }

        $zip->close();

        return $path;
    }

    private function contentTypes(int $count): string
    {
        $sheets = '';
        for ($i = 1; $i <= $count; $i++) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$sheets.'</Types>';
    }

    private function workbook(): string
    {
        $sheets = '';
        foreach ($this->sheets as $index => $sheet) {
            $sheets .= '<sheet name="'.$this->xml($sheet['name']).'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets></workbook>';
    }

    private function workbookRels(int $count): string
    {
        $rels = '';
        for ($i = 1; $i <= $count; $i++) {
            $rels .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
        }
        $rels .= '<Relationship Id="rId'.($count + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>';
    }

    /**
     * Styles: 0 default · 1 header (bold white on brand green) · 2 title (bold, larger) · 3 muted subtitle.
     */
    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="4">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="14"/><color rgb="FF10512A"/><name val="Calibri"/></font>'
            .'<font><sz val="10"/><color rgb="FF71717A"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF10512A"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    /**
     * @param  array{name: string, title: string, subtitle: string, headers: list<string>, rows: list<list<mixed>>}  $sheet
     */
    private function sheet(array $sheet): string
    {
        $headerRow = 4;
        $rowsXml = $this->row(1, [$sheet['title']], 2)
            .$this->row(2, [$sheet['subtitle']], 3)
            .$this->row($headerRow, $sheet['headers'], 1);

        foreach ($sheet['rows'] as $offset => $row) {
            $rowsXml .= $this->row($headerRow + 1 + $offset, $row, 0);
        }

        $cols = '';
        foreach ($sheet['headers'] as $index => $header) {
            $longest = mb_strlen((string) $header);
            foreach ($sheet['rows'] as $row) {
                $longest = max($longest, mb_strlen((string) ($row[$index] ?? '')));
            }
            $width = min(60, max(8, $longest + 2));
            $cols .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="'.$headerRow.'" topLeftCell="A'.($headerRow + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .($cols !== '' ? '<cols>'.$cols.'</cols>' : '')
            .'<sheetData>'.$rowsXml.'</sheetData></worksheet>';
    }

    /**
     * @param  list<mixed>  $values
     */
    private function row(int $number, array $values, int $style): string
    {
        $cells = '';
        foreach (array_values($values) as $index => $value) {
            $ref = $this->column($index).$number;
            $styleAttr = $style ? ' s="'.$style.'"' : '';

            if ($value === null || $value === '') {
                continue;
            }

            $cells .= is_int($value) || is_float($value)
                ? '<c r="'.$ref.'"'.$styleAttr.'><v>'.$value.'</v></c>'
                : '<c r="'.$ref.'"'.$styleAttr.' t="inlineStr"><is><t xml:space="preserve">'.$this->xml((string) $value).'</t></is></c>';
        }

        return '<row r="'.$number.'">'.$cells.'</row>';
    }

    private function column(int $index): string
    {
        $name = '';
        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $name = chr(65 + (($index - 1) % 26)).$name;
        }

        return $name;
    }

    private function xml(string $value): string
    {
        // Strip characters that are illegal in XML 1.0 before escaping.
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
