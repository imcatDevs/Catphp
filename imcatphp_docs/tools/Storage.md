# Storage — 파일시스템 추상화

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Storage` |
| 파일 | `catphp/Storage.php` (468줄) |
| Shortcut | `storage()` |
| 싱글턴 | `getInstance()` — 디스크 선택 시 이뮤터블 (`clone`) |
| 드라이버 | `local`, `s3` (AWS Signature V4) |

---

## 설정

```php
// config/app.php
'storage' => [
    'default' => 'local',
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root'   => __DIR__ . '/../storage/app',
        ],
        'public' => [
            'driver' => 'local',
            'root'   => __DIR__ . '/../Public/uploads',
            'url'    => '/uploads',
        ],
        's3' => [
            'driver'   => 's3',
            'key'      => env()->get('AWS_ACCESS_KEY_ID'),
            'secret'   => env()->get('AWS_SECRET_ACCESS_KEY'),
            'region'   => 'ap-northeast-2',
            'bucket'   => 'my-bucket',
            'endpoint' => null,  // 커스텀 엔드포인트 (MinIO 등)
        ],
    ],
],
```

---

## 메서드 레퍼런스

### 디스크 선택

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `disk` | `disk(string $name): self` | `self` | 디스크 선택 (이뮤터블) |

### 파일 읽기/쓰기

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `put` | `put(string $path, string $content): bool` | `bool` | 파일 저장 |
| `get` | `get(string $path): ?string` | `?string` | 파일 읽기 |
| `append` | `append(string $path, string $content): bool` | `bool` | 파일 추가 쓰기 |
| `exists` | `exists(string $path): bool` | `bool` | 존재 확인 |
| `delete` | `delete(string $path): bool` | `bool` | 파일 삭제 |
| `copy` | `copy(string $from, string $to): bool` | `bool` | 복사 |
| `move` | `move(string $from, string $to): bool` | `bool` | 이동 |

### 정보

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `size` | `size(string $path): int` | `int` | 파일 크기 (바이트) |
| `lastModified` | `lastModified(string $path): int` | `int` | 최종 수정 시간 |
| `mimeType` | `mimeType(string $path): ?string` | `?string` | MIME 타입 |
| `url` | `url(string $path): string` | `string` | 공개 URL |

### 디렉토리

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `files` | `files(string $directory = '', bool $recursive = false): array` | `array` | 파일 목록 |
| `makeDirectory` | `makeDirectory(string $path): bool` | `bool` | 디렉토리 생성 |
| `deleteDirectory` | `deleteDirectory(string $path): bool` | `bool` | 디렉토리 삭제 (재귀) |

### 다운로드

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `stream` | `stream(string $path, ?string $downloadName = null): void` | `void` | 파일 스트리밍 다운로드 |

---

## 사용 예제

### 기본 파일 조작

```php
// 저장 (디렉토리 자동 생성)
storage()->put('reports/2025/q1.txt', $content);

// 읽기
$data = storage()->get('reports/2025/q1.txt');

// 존재 확인
if (storage()->exists('config.json')) { ... }

// 삭제
storage()->delete('temp/cache.dat');
```

### 디스크 전환

```php
// public 디스크 (웹 접근 가능)
storage()->disk('public')->put('avatars/user1.jpg', $imageData);
$url = storage()->disk('public')->url('avatars/user1.jpg');
// → '/uploads/avatars/user1.jpg'

// S3 디스크
storage()->disk('s3')->put('backups/db.sql', $dump);
$url = storage()->disk('s3')->url('backups/db.sql');
// → 'https://my-bucket.s3.ap-northeast-2.amazonaws.com/backups/db.sql'
```

### 파일 목록

```php
$files = storage()->files('uploads');           // 1단계만
$all   = storage()->files('uploads', true);     // 재귀
```

### 파일 스트리밍

```php
router()->get('/download/{file}', function (string $file) {
    storage()->stream("documents/{$file}", $file);
});
```

### 추가 쓰기

```php
storage()->append('logs/custom.log', date('Y-m-d H:i:s') . " 이벤트\n");
```

---

## 내부 동작

### 경로 트래버설 방어

모든 메서드에서 `safePath()` 호출:

```text
safePath($path)
├─ \ → / 변환, \0 제거
├─ ../ 세그먼트 반복 제거 (이중 인코딩 방어)
├─ 빈 경로 / '.' → RuntimeException
└─ realpath 기반 이중 검증 (root 밖 접근 차단)
```

### S3 드라이버 (AWS Signature V4)

```text
s3Request($method, $cfg, $path)
├─ Canonical Request 생성
├─ String to Sign 생성
├─ Signing Key 계산 (HMAC-SHA256 4단계)
├─ Authorization 헤더 생성
└─ cURL 실행
```

### 파일 잠금

`put()`, `append()` 시 `LOCK_EX` — 동시 쓰기 충돌 방지.

---

## 보안 고려사항

- **경로 트래버설 차단**: `../` 제거 + `realpath` 이중 검증
- **null 바이트 제거**: 경로에서 `\0` 제거
- **스트리밍 파일명 살균**: `rawurlencode()` + CRLF/null 제거
- **S3 서명**: AWS Signature V4 — 요청 무결성 보장

---

## 주의사항

1. **디스크 미설정**: 존재하지 않는 디스크명으로 `disk()` 호출 시 `RuntimeException`.
2. **S3 미존재 파일**: `get()` → `null`, `exists()` → `false`.
3. **로컬 파일 잠금**: `put()`은 `LOCK_EX`로 원자적 쓰기. 동시 읽기에는 영향 없음.
4. **deleteDirectory 주의**: 재귀적으로 모든 하위 파일/폴더를 삭제. 복구 불가.
5. **S3 endpoint**: MinIO 등 S3 호환 서비스 사용 시 `endpoint` 설정 필요.

---

## 연관 도구

- [Upload](Upload.md) — 파일 업로드
- [Image](Image.md) — 이미지 처리
- [Backup](Backup.md) — DB 백업 저장
