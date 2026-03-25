<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Excel — CSV / Excel 가져오기·내보내기
 *
 * 외부 의존성 없이 CSV와 간단한 XLSX 생성 지원.
 *
 * 사용법:
 *   excel()->from([['이름','나이'],['Alice',30]])->toCsv('export.csv');
 *   excel()->from($rows)->toXlsx('export.xlsx');
 *   $data = excel()->fromCsv('import.csv');
 *   excel()->from($rows)->download('report.csv');
 */
final class Excel
{
    private static ?self $instance = null;

    /** @var array<int, array<int, mixed>> */
    private array $rows = [];

    /** @var string[] */
    private array $headers = [];

    private string $delimiter = ',';
    private string $enclosure = '"';

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    // ── 데이터 설정 ──

    /** 배열 데이터 설정 */
    public function from(array $rows): self
    {
        $c = clone $this;
        $c->rows = $rows;
        return $c;
    }

    /** 헤더 설정 (첫 행으로 추가) */
    public function headers(array $headers): self
    {
        $c = clone $this;
        $c->headers = $headers;
        return $c;
    }

    /** CSV 구분자 설정 */
    public function delimiter(string $delimiter): self
    {
        $c = clone $this;
        $c->delimiter = $delimiter;
        return $c;
    }

    // ── CSV 내보내기 ──

    /** CSV 파일로 저장 */
    public function toCsv(string $path): bool
    {
        $fp = fopen($path, 'w');
        if ($fp === false) {
            throw new \RuntimeException("파일 열기 실패: {$path}");
        }

        // BOM (Excel 한글 호환)
        fwrite($fp, "\xEF\xBB\xBF");

        if (!empty($this->headers)) {
            fputcsv($fp, $this->headers, $this->delimiter, $this->enclosure);
        }

        foreach ($this->rows as $row) {
            fputcsv($fp, is_array($row) ? array_values($row) : [$row], $this->delimiter, $this->enclosure);
        }

        fclose($fp);
        return true;
    }

    /** CSV 문자열 반환 */
    public function toCsvString(): string
    {
        $fp = fopen('php://temp', 'r+');
        if ($fp === false) {
            throw new \RuntimeException('임시 스트림 생성 실패');
        }

        fwrite($fp, "\xEF\xBB\xBF");

        if (!empty($this->headers)) {
            fputcsv($fp, $this->headers, $this->delimiter, $this->enclosure);
        }

        foreach ($this->rows as $row) {
            fputcsv($fp, is_array($row) ? array_values($row) : [$row], $this->delimiter, $this->enclosure);
        }

        rewind($fp);
        $content = stream_get_contents($fp);
        fclose($fp);
        return $content ?: '';
    }

    // ── CSV 가져오기 ──

    /**
     * CSV 파일 읽기
     *
     * @return array<int, array<int|string, string>>
     */
    public function fromCsv(string $path, bool $hasHeader = true): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException("파일 없음: {$path}");
        }

        $fp = fopen($path, 'r');
        if ($fp === false) {
            throw new \RuntimeException("파일 열기 실패: {$path}");
        }

        // BOM 건너뛰기
        $bom = fread($fp, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fp);
        }

        $rows = [];
        $headers = null;

        while (($row = fgetcsv($fp, 0, $this->delimiter, $this->enclosure)) !== false) {
            if ($hasHeader && $headers === null) {
                $headers = $row;
                continue;
            }
            // CSV injection 방어: 각 셀 값 살균
            $row = array_map(fn($v) => is_string($v) ? $this->sanitizeCell($v) : $v, $row);

            if ($headers !== null) {
                $rows[] = array_combine($headers, array_pad($row, count($headers), '')) ?: $row;
            } else {
                $rows[] = $row;
            }
        }

        fclose($fp);
        return $rows;
    }

    /** CSV 문자열에서 읽기 */
    public function fromCsvString(string $content, bool $hasHeader = true): array
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'catcsv_');
        if ($tmpFile === false) {
            throw new \RuntimeException('임시 파일 생성 실패');
        }
        file_put_contents($tmpFile, $content);
        $rows = $this->fromCsv($tmpFile, $hasHeader);
        @unlink($tmpFile);
        return $rows;
    }

    // ── XLSX 내보내기 (간이) ──

    /**
     * XLSX 파일로 저장
     *
     * Office Open XML 최소 구현 (ZipArchive 필요)
     */
    public function toXlsx(string $path): bool
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ext-zip 확장이 필요합니다.');
        }

        $allRows = [];
        if (!empty($this->headers)) {
            $allRows[] = $this->headers;
        }
        foreach ($this->rows as $row) {
            $allRows[] = is_array($row) ? array_values($row) : [$row];
        }

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("XLSX 파일 생성 실패: {$path}");
        }

        // [Content_Types].xml
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '</Types>');

        // _rels/.rels
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        // xl/_rels/workbook.xml.rels
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '</Relationships>');

        // xl/workbook.xml
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');

        // Shared Strings 수집
        $strings = [];
        $stringIndex = [];
        $totalStringRefs = 0;
        foreach ($allRows as $row) {
            foreach ($row as $cell) {
                if (is_numeric($cell) && !is_string($cell)) {
                    continue;
                }
                $val = (string) $cell;
                $totalStringRefs++;
                if (!isset($stringIndex[$val])) {
                    $stringIndex[$val] = count($strings);
                    $strings[] = $val;
                }
            }
        }

        // xl/sharedStrings.xml
        $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $totalStringRefs . '" uniqueCount="' . count($strings) . '">';
        foreach ($strings as $s) {
            $ssXml .= '<si><t>' . htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></si>';
        }
        $ssXml .= '</sst>';
        $zip->addFromString('xl/sharedStrings.xml', $ssXml);

        // xl/worksheets/sheet1.xml
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($allRows as $rowIdx => $row) {
            $rowNum = $rowIdx + 1;
            $sheetXml .= "<row r=\"{$rowNum}\">";
            foreach ($row as $colIdx => $cell) {
                $colLetter = $this->columnLetter($colIdx);
                $cellRef = $colLetter . $rowNum;
                $val = (string) $cell;

                if (is_numeric($cell) && !is_string($cell)) {
                    $sheetXml .= "<c r=\"{$cellRef}\"><v>{$cell}</v></c>";
                } else {
                    $idx = $stringIndex[$val] ?? 0;
                    $sheetXml .= "<c r=\"{$cellRef}\" t=\"s\"><v>{$idx}</v></c>";
                }
            }
            $sheetXml .= '</row>';
        }

        $sheetXml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

        $zip->close();
        return true;
    }

    // ── 다운로드 ──

    /** CSV 브라우저 다운로드 */
    public function download(string $filename = 'export.csv'): never
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $safeFilename = rawurlencode(str_replace(["\r", "\n", "\0"], '', $filename));

        if ($ext === 'xlsx') {
            $tmpFile = tempnam(sys_get_temp_dir(), 'catxlsx_');
            if ($tmpFile === false) {
                throw new \RuntimeException('임시 파일 생성 실패');
            }
            $this->toXlsx($tmpFile);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment; filename=\"{$safeFilename}\"");
            header('Content-Length: ' . filesize($tmpFile));
            readfile($tmpFile);
            @unlink($tmpFile);
        } else {
            $content = $this->toCsvString();
            header('Content-Type: text/csv; charset=UTF-8');
            header("Content-Disposition: attachment; filename=\"{$safeFilename}\"");
            header('Content-Length: ' . strlen($content));
            echo $content;
        }
        exit;
    }

    // ── 내부 ──

    /**
     * CSV injection 방어: 위험한 시작 문자 제거
     *
     * 스프레드시트 프로그램(Excel, LibreOffice)이 =, +, -, @, \t, \r로
     * 시작하는 셀을 수식으로 해석하여 임의 코드 실행 가능.
     */
    private function sanitizeCell(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }
        return $value;
    }

    /** 컬럼 인덱스 → 알파벳 (0=A, 25=Z, 26=AA) */
    private function columnLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }
        return $letter;
    }

}
