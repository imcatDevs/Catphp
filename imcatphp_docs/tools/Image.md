# Image — 이미지 처리 (GD)

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Image` |
| 파일 | `catphp/Image.php` (188줄) |
| Shortcut | `image()` |
| 싱글턴 | `getInstance()` — 이뮤터블 체이닝 (`clone` + 깊은 복사) |
| 의존 확장 | `ext-gd` |

---

## 설정

```php
// config/app.php
'image' => [
    'quality' => 85,   // JPEG/WebP 품질 (0~100)
],
```

---

## 메서드 레퍼런스

### 열기 / 저장

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `open` | `open(string $path): self` | `self` | 이미지 열기 (JPEG/PNG/GIF/WebP) |
| `save` | `save(string $path): bool` | `bool` | 이미지 저장 (확장자로 형식 결정) |
| `convert` | `convert(string $outputPath): bool` | `bool` | 포맷 변환 (save 래퍼) |

### 변환

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `resize` | `resize(int $width, int $height): self` | `self` | 리사이즈 |
| `crop` | `crop(int $x, int $y, int $width, int $height): self` | `self` | 크롭 |
| `thumbnail` | `thumbnail(int $maxWidth, int $maxHeight): self` | `self` | 썸네일 (비율 유지) |
| `watermark` | `watermark(string $text, int $x = 10, int $y = 10, int $fontSize = 16, ?string $fontPath = null): self` | `self` | 텍스트 워터마크 |

### 정보

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `width` | `width(): int` | `int` | 이미지 너비 |
| `height` | `height(): int` | `int` | 이미지 높이 |

---

## 사용 예제

### 썸네일 생성

```php
image()
    ->open('storage/uploads/photo.jpg')
    ->thumbnail(300, 300)
    ->save('storage/thumbnails/photo.jpg');
```

### 리사이즈

```php
image()
    ->open('original.png')
    ->resize(800, 600)
    ->save('resized.png');
```

### 크롭

```php
image()
    ->open('photo.jpg')
    ->crop(100, 50, 400, 400)
    ->save('cropped.jpg');
```

### 워터마크

```php
// 기본 (내장 폰트)
image()
    ->open('photo.jpg')
    ->watermark('© CatPHP', 10, 10)
    ->save('watermarked.jpg');

// TrueType 폰트 (한글 지원)
image()
    ->open('photo.jpg')
    ->watermark('© CatPHP', 10, 10, 24, '/path/to/NanumGothic.ttf')
    ->save('watermarked.jpg');
```

### 포맷 변환

```php
// PNG → WebP
image()->open('photo.png')->save('photo.webp');

// JPEG → PNG
image()->open('photo.jpg')->convert('photo.png');
```

### 체이닝

```php
image()
    ->open('original.jpg')
    ->resize(1200, 800)
    ->watermark('© 2025', 10, 780, 14, $fontPath)
    ->save('final.jpg');
```

### 이미지 정보

```php
$img = image()->open('photo.jpg');
echo "크기: {$img->width()} x {$img->height()}";
```

---

## 내부 동작

### 지원 형식

| 형식 | 입력 | 출력 |
| --- | --- | --- |
| JPEG | `imagecreatefromjpeg` | `imagejpeg($res, $path, $quality)` |
| PNG | `imagecreatefrompng` | `imagepng($res, $path)` |
| GIF | `imagecreatefromgif` | `imagegif($res, $path)` |
| WebP | `imagecreatefromwebp` | `imagewebp($res, $path, $quality)` |

### 투명도 보존

PNG/GIF/WebP의 투명 영역이 리사이즈/크롭 시 유지된다:

```php
imagealphablending($image, false);
imagesavealpha($image, true);
imagecolorallocatealpha($image, 0, 0, 0, 127);  // 완전 투명
```

### 깊은 복사 (__clone)

이뮤터블 체이닝 시 GD 리소스가 공유되지 않도록 `__clone()`에서 새 이미지를 생성하고 원본을 복사한다.

### 디렉토리 자동 생성

`save()` 시 대상 디렉토리가 없으면 `mkdir($dir, 0755, true)` 자동 생성.

---

## 주의사항

1. **ext-gd 필수**: GD 확장이 없으면 인스턴스 생성 시 `RuntimeException`.
2. **메모리**: 대용량 이미지(4000×3000+)는 GD 메모리를 많이 소모. `memory_limit` 확인 필요.
3. **품질**: JPEG/WebP만 quality 설정 적용. PNG는 무손실.
4. **워터마크 폰트**: `fontPath` 없이 호출하면 GD 내장 비트맵 폰트 사용 (한글 미지원, 크기 1~5).
5. **BMP/TIFF 미지원**: JPEG, PNG, GIF, WebP만 지원.

---

## 연관 도구

- [Upload](Upload.md) — 이미지 업로드 후 처리
- [Guard](Guard.md) — 파일명 살균 (`guard()->filename()`)
