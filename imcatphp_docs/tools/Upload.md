# Upload — 파일 업로드

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Upload` |
| 파일 | `catphp/Upload.php` (155줄) |
| Shortcut | `upload()` |
| 싱글턴 | `getInstance()` — 이뮤터블 체이닝 (`clone`) |
| 의존 도구 | `Cat\Guard` (파일명 살균) |
| 의존 확장 | `ext-fileinfo` (MIME 검증) |

---

## 설정

```php
// config/app.php
'upload' => [
    'max_size' => '10M',                                          // 최대 파일 크기
    'allowed'  => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'zip'],  // 허용 확장자
],
```

---

## 메서드 레퍼런스

### 빌더 (이뮤터블)

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `file` | `file(string $fieldName): self` | `self` | 업로드 필드 지정 |
| `maxSize` | `maxSize(string $size): self` | `self` | 최대 크기 오버라이드 (예: `'5M'`) |
| `allowTypes` | `allowTypes(array $types): self` | `self` | 허용 확장자 오버라이드 |

### 실행

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `save` | `save(string $directory, ?string $filename = null): ?string` | `?string` | 파일 저장 → 파일명 반환 |
| `move` | `move(string $from, string $to): bool` | `bool` | 파일 이동 |

---

## 사용 예제

### 기본 업로드

```php
router()->post('/upload', function () {
    $filename = upload()
        ->file('avatar')
        ->save('storage/uploads');

    if ($filename === null) {
        json()->fail('파일이 선택되지 않았습니다');
    }

    json()->ok(['filename' => $filename]);
});
```

### 크기/타입 제한

```php
$filename = upload()
    ->file('document')
    ->maxSize('5M')
    ->allowTypes(['pdf', 'docx', 'xlsx'])
    ->save('storage/documents');
```

### 커스텀 파일명

```php
$filename = upload()
    ->file('photo')
    ->save('storage/photos', 'profile_' . auth()->id() . '.jpg');
```

### 이미지 업로드 + 썸네일

```php
router()->post('/photos', function () {
    $filename = upload()
        ->file('photo')
        ->allowTypes(['jpg', 'jpeg', 'png', 'webp'])
        ->maxSize('5M')
        ->save('storage/photos');

    if ($filename) {
        image()
            ->open("storage/photos/{$filename}")
            ->thumbnail(300, 300)
            ->save("storage/thumbnails/{$filename}");
    }

    json()->ok(['filename' => $filename]);
});
```

### 파일 이동

```php
upload()->move('storage/temp/file.pdf', 'storage/documents/report.pdf');
```

---

## 내부 동작

### save() 흐름

```text
save('storage/uploads')
├─ $_FILES[$fieldName] 확인
├─ UPLOAD_ERR_OK 확인
├─ 크기 검증: $file['size'] <= maxSize
├─ 파일명 살균: guard()->filename($file['name'])
│   └─ null 바이트 제거, 이중 확장자 차단, 위험 확장자 .blocked
├─ 확장자 검증: strtolower(pathinfo(EXTENSION)) in $allowed
├─ MIME 교차 검증: finfo(FILEINFO_MIME_TYPE)
│   └─ 확장자-MIME 매핑 불일치 → RuntimeException
├─ 파일명 생성: uniqid() . '_' . $safeName (또는 커스텀)
├─ 디렉토리 자동 생성
└─ move_uploaded_file() → 파일명 반환
```

### MIME 교차 검증

확장자와 실제 MIME 타입을 비교하여 확장자 위조 공격을 차단:

| 확장자 | 허용 MIME |
| --- | --- |
| `jpg`/`jpeg` | `image/jpeg` |
| `png` | `image/png` |
| `gif` | `image/gif` |
| `webp` | `image/webp` |
| `svg` | `image/svg+xml` |
| `pdf` | `application/pdf` |
| `zip` | `application/zip`, `application/x-zip-compressed` |
| `csv` | `text/csv`, `text/plain` |
| `txt` | `text/plain` |
| `json` | `application/json`, `text/plain` |
| `xml` | `application/xml`, `text/xml` |
| `mp4` | `video/mp4` |
| `mp3` | `audio/mpeg` |
| `doc` | `application/msword` |
| `docx` | `application/vnd.openxmlformats-officedocument.wordprocessingml.document` |
| `xls` | `application/vnd.ms-excel` |
| `xlsx` | `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` |

매핑에 없는 확장자는 통과 (커스텀 파일 타입 허용).

### 파일명 생성

```php
$filename = uniqid() . '_' . $safeName;
// 예: '65f8a1b2c3d4e_photo.jpg'
```

`uniqid()` — 마이크로초 기반 고유 접두사로 파일명 충돌 방지.

---

## 보안 고려사항

### Guard 파일명 살균

`guard()->filename()` — null 바이트, 이중 확장자(`file.php.jpg`), 위험 확장자(php, phar, exe 등) 차단.

### MIME 타입 검증

`finfo` 확장으로 파일의 실제 내용(매직 바이트)을 확인. `.jpg`로 이름을 변경한 PHP 파일 업로드를 차단.

### 실행 방지

업로드 디렉토리에 `.htaccess`로 PHP 실행을 차단하는 것이 권장:

```apache
# storage/uploads/.htaccess
php_flag engine off
<FilesMatch "\.ph(p[3-8]?|ar|tml)$">
    Deny from all
</FilesMatch>
```

---

## 주의사항

1. **`file()` 필수**: `save()` 호출 전에 `file()` 미지정 시 `RuntimeException`.

2. **반환값 `null`**: 파일이 선택되지 않았거나 업로드 에러 발생 시 `null` 반환 (예외 아님).

3. **예외 발생 조건**: 크기 초과, 확장자 불허, MIME 불일치, 저장 실패 시 `RuntimeException`.

4. **커스텀 파일명**: `save($dir, $filename)` 시 확장자가 포함된 전체 파일명을 지정해야 한다.

5. **디렉토리 자동 생성**: 저장 디렉토리가 없으면 `mkdir(0755, true)` 자동 생성.

6. **php.ini 제한**: `upload_max_filesize`, `post_max_size` PHP 설정도 확인 필요.

---

## 연관 도구

- [Guard](Guard.md) — 파일명 살균 (`guard()->filename()`)
- [Image](Image.md) — 업로드 후 이미지 처리
- [Storage](Storage.md) — 파일 저장소 관리
