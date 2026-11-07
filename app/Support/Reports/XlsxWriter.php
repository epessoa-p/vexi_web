<?php

namespace App\Support\Reports;

use RuntimeException;
use ZipArchive;

/**
 * Libro de Excel (.xlsx) mínimo: hojas con título, encabezado y filas. Los
 * números van como números, así Excel los suma y los muestra según la
 * configuración regional de quien abre el archivo. Los textos van como texto:
 * un valor que empieza con "=" nunca se ejecuta como fórmula.
 */
class XlsxWriter
{
    private const STYLE_TITLE = 1;

    private const STYLE_HEADER = 2;

    private const STYLE_NUMBER = 3;

    /** @var array<int, array{name: string, title: string, columns: array<int, string>, rows: array<int, array<int, mixed>>}> */
    private array $sheets = [];

    /** @param  array<int, string>  $columns */
    public function addSheet(string $title, array $columns, array $rows): self
    {
        $this->sheets[] = ['name' => $this->sheetName($title), 'title' => $title, 'columns' => $columns, 'rows' => $rows];

        return $this;
    }

    /** Escribe el libro en un archivo temporal y devuelve su ruta. */
    public function save(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo de Excel.');
        }

        $count = count($this->sheets);
        $overrides = '';
        $relations = '';
        $sheets = '';

        foreach ($this->sheets as $index => $sheet) {
            $n = $index + 1;
            $zip->addFromString("xl/worksheets/sheet{$n}.xml", $this->sheetXml($sheet));
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $relations .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
            $sheets .= '<sheet name="'.$this->escape($sheet['name']).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
        }

        $declaration = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $zip->addFromString('[Content_Types].xml', $declaration.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides.'</Types>');
        $zip->addFromString('_rels/.rels', $declaration.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $declaration.'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheets.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $declaration.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relations
            .'<Relationship Id="rId'.($count + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', $declaration.'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
            .'<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="14"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF3F4F6"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs></styleSheet>');

        $zip->close();

        return $path;
    }

    /** @param  array{title: string, columns: array<int, string>, rows: array<int, array<int, mixed>>}  $sheet */
    private function sheetXml(array $sheet): string
    {
        $xml = '';
        $number = 1;

        $xml .= $this->row($number++, [$sheet['title']], self::STYLE_TITLE);
        $number++;
        $xml .= $this->row($number++, $sheet['columns'], self::STYLE_HEADER);
        foreach ($sheet['rows'] as $row) {
            $xml .= $this->row($number++, array_values($row));
        }

        $cols = '';
        foreach ($sheet['columns'] as $i => $column) {
            $width = max(mb_strlen((string) $column), ...array_map(fn ($row) => is_numeric(array_values($row)[$i] ?? '') ? 12 : mb_strlen((string) (array_values($row)[$i] ?? '')), $sheet['rows'] ?: [[]]));
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.min($width + 3, 60).'" customWidth="1"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .($cols ? "<cols>{$cols}</cols>" : '').'<sheetData>'.$xml.'</sheetData></worksheet>';
    }

    private function row(int $number, array $values, ?int $style = null): string
    {
        $cells = '';

        foreach ($values as $i => $value) {
            $ref = $this->column($i).$number;

            if ($value === null || $value === '') {
                continue;
            }

            if (is_int($value) || is_float($value)) {
                $cells .= '<c r="'.$ref.'"'.(is_float($value) ? ' s="'.self::STYLE_NUMBER.'"' : '').'><v>'.$value.'</v></c>';

                continue;
            }

            $cells .= '<c r="'.$ref.'" t="inlineStr"'.($style ? ' s="'.$style.'"' : '').'><is><t xml:space="preserve">'.$this->escape((string) $value).'</t></is></c>';
        }

        return '<row r="'.$number.'">'.$cells.'</row>';
    }

    private function column(int $index): string
    {
        $name = '';

        for ($i = $index; $i >= 0; $i = (int) ($i / 26) - 1) {
            $name = chr(65 + $i % 26).$name;
        }

        return $name;
    }

    /** Excel limita los nombres de hoja a 31 caracteres, sin : \ / ? * [ ] y sin repetirse. */
    private function sheetName(string $title): string
    {
        $base = mb_substr(trim(str_replace([':', '\\', '/', '?', '*', '[', ']'], ' ', $title)), 0, 28);
        $names = array_column($this->sheets, 'name');
        $name = $base;

        for ($i = 2; in_array($name, $names, true); $i++) {
            $name = "{$base} {$i}";
        }

        return $name;
    }

    private function escape(string $value): string
    {
        // Sin caracteres de control: XML no los admite y Excel declararía el archivo dañado.
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
