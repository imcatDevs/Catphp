# Excel — CSV / Excel 가져오기·내보내기

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Excel` |
| 파일 | `catphp/Excel.php` (340줄) |
| Shortcut | `excel()` |
| 싱글턴 | `getInstance()` — 이뮤터블 체이닝 (`clone`) |
| 선택 확장 | `ext-zip` (XLSX 생성 시 필수) |

---

## 설정

별도 config 없음.

---

## 메서드 레퍼런스

### 데이터 설정 (이뮤터블)

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `from` | `from(array $rows): self` | `self` | 데이터 배열 설정 |
| `headers` | `headers(array $headers): self` | `self` | 헤더 설정 (첫 행 추가) |
| `delimiter` | `delimiter(string $delimiter): self` | `self` | CSV 구분자 (기본 `,`) |

### CSV 내보내기

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `toCsv` | `toCsv(string $path): bool` | `bool` | CSV 파일 저장 |
| `toCsvString` | `toCsvString(): string` | `string` | CSV 문자열 반환 |

### CSV 가져오기

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `fromCsv` | `fromCsv(string $path, bool $hasHeader = true): array` | `array` | CSV 파일 읽기 |
| `fromCsvString` | `fromCsvString(string $content, bool $hasHeader = true): array` | `array` | CSV 문자열 읽기 |

### XLSX 내보내기

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `toXlsx` | `toXlsx(string $path): bool` | `bool` | XLSX 파일 저장 |

### 다운로드

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `download` | `download(string $filename = 'export.csv'): never` | `never` | 브라우저 다운로드 (CSV/XLSX) |

---

## 사용 예제

### CSV 파일 내보내기

```php
$users = db()->table('users')->all();

excel()
    ->headers(['이름', '이메일', '가입일'])
    ->from(array_map(fn($u) => [$u['name'], $u['email'], $u['created_at']], $users))
    ->toCsv('storage/exports/users.csv');
```

### CSV 파일 가져오기

```php
$data = excel()->fromCsv('storage/imports/data.csv');
// [['이름' => '홍길동', '이메일' => 'hong@example.com'], ...]

foreach ($data as $row) {
    db()->table('users')->insert([
        'name'  => $row['이름'],
        'email' => $row['이메일'],
    ]);
}
```

### XLSX 파일 내보내기

```php
excel()
    ->headers(['상품명', '가격', '재고'])
    ->from([
        ['CatPHP Pro', 50000, 100],
        ['CatPHP Lite', 30000, 250],
    ])
    ->toXlsx('storage/exports/products.xlsx');
```

### 브라우저 다운로드

```php
router()->get('/export/users', function () {
    $users = db()->table('users')->all();

    excel()
        ->headers(['ID', '이름', '이메일'])
        ->from(array_map(fn($u) => [$u['id'], $u['name'], $u['email']], $users))
        ->download('users.csv');
});

// XLSX 다운로드
router()->get('/export/report', function () {
    excel()->headers([...])->from([...])->download('report.xlsx');
});
```

### TSV (탭 구분)

```php
excel()->delimiter("\t")->from($data)->toCsv('export.tsv');
```

### 헤더 없는 CSV 읽기

```php
$data = excel()->fromCsv('raw.csv', hasHeader: false);
// [[0 => '값1', 1 => '값2'], ...]
```

---

## 내부 동작

### BOM (Byte Order Mark)

CSV 출력 시 UTF-8 BOM(`\xEF\xBB\xBF`)을 앞에 추가. Excel에서 한글이 깨지지 않도록 보장.

CSV 읽기 시 BOM 자동 감지 및 건너뛰기.

### CSV Injection 방어

```php
private function sanitizeCell(string $value): string {
    if (in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $value;  // 작은따옴표 접두사
    }
    return $value;
}
```

Excel/LibreOffice가 `=`, `+`, `-`, `@`로 시작하는 셀을 수식으로 해석하여 임의 코드 실행이 가능한 취약점을 차단.

### XLSX 구조

```text
.xlsx (ZIP 파일)
├─ [Content_Types].xml
├─ _rels/.rels
├─ xl/
│   ├─ _rels/workbook.xml.rels
│   ├─ workbook.xml
│   ├─ sharedStrings.xml    ← 문자열 풀
│   └─ worksheets/
│       └─ sheet1.xml       ← 셀 데이터
```

- 숫자: 인라인 `<v>` 값
- 문자열: `sharedStrings.xml` 인덱스 참조 (`t="s"`)

### 컬럼 알파벳 변환

```text
0 → A, 25 → Z, 26 → AA, 27 → AB, ...
```

---

## 보안 고려사항

- **CSV Injection 방어**: `=`, `+`, `-`, `@`, `\t`, `\r`로 시작하는 셀에 `'` 접두사
- **다운로드 파일명 살균**: `rawurlencode()` + CRLF/null 제거
- **XML 이스케이프**: XLSX 내 sharedStrings에 `htmlspecialchars(ENT_XML1)` 적용

---

## 주의사항

1. **ext-zip 필수 (XLSX)**: XLSX 생성 시 `ZipArchive` 클래스가 필요. 없으면 `RuntimeException`.
2. **메모리**: 대용량 데이터는 전체를 메모리에 로드. 수만 행 이상은 스트리밍 방식 권장.
3. **XLSX 제한**: 단일 시트(Sheet1)만 지원. 스타일/수식/차트 미지원.
4. **헤더 자동 매핑**: `fromCsv(hasHeader: true)` 시 첫 행을 키로 사용. 열 수가 다르면 `array_pad` 적용.
5. **download()은 never**: 내부에서 `exit` 호출.

---

## 연관 도구

- [DB](DB.md) — 데이터 소스
- [Response](Response.md) — 파일 다운로드 헤더
