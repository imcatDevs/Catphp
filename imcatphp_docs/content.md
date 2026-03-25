# 콘텐츠 — Tag · Feed · Text · Slug · Image · Upload

CatPHP의 콘텐츠 관리 계층. 태깅, RSS/Atom 피드, 텍스트 처리, 슬러그, 이미지, 파일 업로드를 제공한다.

| 도구 | Shortcut | 클래스 | 줄 수 |
| --- | --- | --- | --- |
| Tag | `tag()` | `Cat\Tag` | 134 |
| Feed | `feed()` | `Cat\Feed` | 173 |
| Text | `text()` | `Cat\Text` | 103 |
| Slug | `slug()` | `Cat\Slug` | 57 |
| Image | `image()` | `Cat\Image` | 188 |
| Upload | `upload()` | `Cat\Upload` | 155 |

---

## 목차

1. [Tag — 태그/카테고리](#1-tag--태그카테고리)
2. [Feed — RSS/Atom 피드](#2-feed--rssatom-피드)
3. [Text — 본문 처리](#3-text--본문-처리)
4. [Slug — URL 슬러그](#4-slug--url-슬러그)
5. [Image — 이미지 처리 (GD)](#5-image--이미지-처리-gd)
6. [Upload — 파일 업로드](#6-upload--파일-업로드)

---

## 1. Tag — 태그/카테고리

다형성 태깅 시스템. `tags` + `taggables` 테이블로 어떤 모델에도 태그 부착 가능.

### Tag DB 구조

```sql
-- tags 테이블
CREATE TABLE tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE
);

-- taggables 중간 테이블 (다형성)
CREATE TABLE taggables (
    tag_id INT NOT NULL,
    taggable_type VARCHAR(50) NOT NULL,  -- 'posts', 'pages' 등
    taggable_id INT NOT NULL,
    PRIMARY KEY (tag_id, taggable_type, taggable_id)
);
```

### Tag 사용법

```php
// 태그 붙이기 (없으면 자동 생성)
tag()->attach('posts', $postId, ['PHP', 'Framework', '웹개발']);

// 태그 제거
tag()->detach('posts', $postId, ['PHP']);

// 태그 동기화 (기존 전부 제거 후 재설정)
tag()->sync('posts', $postId, ['PHP', 'Laravel']);

// 항목의 태그 목록 (Guard 살균 적용)
$tags = tag()->tags('posts', $postId);
// [['name' => 'PHP', 'slug' => 'php'], ...]

// 특정 태그가 붙은 항목 ID 목록
$postIds = tag()->tagged('posts', 'PHP');
// [1, 5, 12, ...]

// 태그 클라우드 (가중치)
$cloud = tag()->cloud('posts');
// ['PHP' => 15, 'JavaScript' => 8, ...]

// 인기 태그 상위 10개
$popular = tag()->popular(10);
// [['name' => 'PHP', 'slug' => 'php', 'count' => '15'], ...]
```

### Tag 보안

- 태그명에 `guard()->clean()` 자동 적용
- 슬러그 생성에 `slug()->make()` 사용 (XSS 안전)
- 출력 시 `guard()->cleanArray()` 적용

---

## 2. Feed — RSS/Atom 피드

RSS 2.0 / Atom 피드 생성. 캐시 연동.

### Feed 설정

```php
'feed' => [
    'limit'     => 20,    // 피드 항목 수
    'cache_ttl' => 3600,  // 캐시 TTL (초)
],
```

### Feed 사용법

```php
// 수동 아이템 설정
feed()
    ->title('My Blog')
    ->description('최신 게시글')
    ->link('https://example.com')
    ->items([
        ['title' => '첫 글', 'description' => '내용...', 'link' => '/post/1', 'pubDate' => '2024-01-15'],
        ['title' => '둘째 글', 'description' => '내용...', 'link' => '/post/2', 'pubDate' => '2024-01-14'],
    ])
    ->rss();  // Content-Type 설정 + XML 출력 + exit

// DB 쿼리에서 피드 생성
$posts = db()->table('posts')->orderByDesc('created_at')->limit(20)->all();

feed()
    ->title('My Blog')
    ->link('https://example.com')
    ->fromQuery($posts, titleCol: 'title', contentCol: 'content', dateCol: 'created_at', slugCol: 'slug')
    ->atom();  // Atom 출력

// 문자열로 반환 (출력하지 않음)
$xml = feed()->title('Blog')->items($items)->render('rss');
$xml = feed()->title('Blog')->items($items)->render('atom');
```

### Feed 보안

- 아이템 값에 `htmlspecialchars(ENT_XML1)` 이스케이프
- `fromQuery()`에서 `guard()->clean()` 살균 + `strip_tags()` + 300자 제한

---

## 3. Text — 본문 처리

발췌, 읽기 시간, 단어 수 등 텍스트 유틸리티. 한글/영문 별도 처리.

### Text 메서드

```php
// 발췌 (단어 경계 자르기, 한글 인식)
text()->excerpt($html, 200);
// '첫 번째 문장의 내용이 여기에...'

// 읽기 시간 (한글 500자/분, 영문 200단어/분)
text()->readingTime($content);
// '3분'

// 단어 수 (한글 글자 + 영문 단어)
text()->wordCount($content);
// 450

// 문자 수
text()->charCount($content);                    // 공백 제외
text()->charCount($content, includeSpaces: true); // 공백 포함

// HTML 태그 제거
text()->stripTags($html);

// UTF-8 안전 자르기
text()->truncate('긴 텍스트...', 50);
// '긴 텍스트의 일부 내용이 여기...'
```

### Text 발췌 알고리즘

1. HTML 태그 제거
2. 연속 공백 정리
3. 지정 길이에서 자르기
4. **단어 경계** 보정 — 마지막 공백 위치가 70% 이상이면 그곳에서 자름
5. 접미사(`...`) 추가

---

## 4. Slug — URL 슬러그

다국어 유니코드 보존 슬러그 생성.

### Slug 사용법

```php
// 기본 슬러그
slug()->make('Hello World!');      // 'hello-world'
slug()->make('PHP 프레임워크');    // 'php-프레임워크'
slug()->make('CatPHP は速い');     // 'catphp-は速い'

// 커스텀 구분자
slug()->make('Hello World', '_');  // 'hello_world'

// 고유 슬러그 (중복 시 suffix 추가)
$slug = slug()->unique('게시글 제목', function (string $slug): bool {
    return db()->table('posts')->where('slug', $slug)->exists();
});
// 'posts-제목' 또는 'posts-제목-2'
```

### Slug 내부 동작

1. `mb_strtolower()` — 소문자 변환
2. 유니코드 문자(한글/영문/숫자)만 유지 (`\p{L}\p{N}`)
3. 공백 → 구분자
4. 연속 구분자 제거
5. 양끝 구분자 trim

`unique()`는 최대 100회 시도 후 `RuntimeException` (무한루프 방어).

---

## 5. Image — 이미지 처리 (GD)

GD 확장 기반 이미지 리사이즈, 크롭, 썸네일, 워터마크, 포맷 변환.

### Image 설정

```php
'image' => [
    'quality' => 85,  // JPEG/WebP 품질 (0-100)
],
```

### Image 사용법

```php
// 리사이즈
image()->open('/path/to/photo.jpg')
    ->resize(800, 600)
    ->save('/path/to/resized.jpg');

// 썸네일 (비율 유지)
image()->open('/path/to/photo.jpg')
    ->thumbnail(200, 200)
    ->save('/path/to/thumb.jpg');

// 크롭
image()->open('/path/to/photo.jpg')
    ->crop(x: 100, y: 50, width: 400, height: 300)
    ->save('/path/to/cropped.jpg');

// 워터마크
image()->open('/path/to/photo.jpg')
    ->watermark('© MySite', x: 10, y: 10, fontSize: 16)
    ->save('/path/to/watermarked.jpg');

// TrueType 폰트 워터마크 (한글 지원)
image()->open('/path/to/photo.jpg')
    ->watermark('워터마크', fontPath: '/path/to/NanumGothic.ttf')
    ->save('/path/to/output.jpg');

// 포맷 변환 (확장자로 자동 판별)
image()->open('/path/to/photo.jpg')
    ->save('/path/to/photo.webp');

// 정보
$img = image()->open('/path/to/photo.jpg');
$img->width();   // 1920
$img->height();  // 1080
```

### Image 지원 형식

| 형식 | 읽기 | 쓰기 |
| --- | --- | --- |
| JPEG | O | O |
| PNG | O | O |
| GIF | O | O |
| WebP | O | O |

### Image 내부 동작

- **이뮤터블**: `clone` + `__clone()` 시 GdImage 깊은 복사
- **투명도 보존**: PNG/WebP/GIF의 알파 채널 보존
- **GD 확장 체크**: 미설치 시 친절한 에러 메시지

---

## 6. Upload — 파일 업로드

보안 검증 포함 파일 업로드. Guard 연동.

### Upload 설정

```php
'upload' => [
    'max_size' => '10M',
    'allowed'  => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'zip'],
],
```

### Upload 사용법

```php
// 기본 업로드
$filename = upload()
    ->file('avatar')
    ->save('/path/to/uploads');
// 'abc123_photo.jpg' 또는 null (파일 없음)

// 옵션 오버라이드
$filename = upload()
    ->file('document')
    ->maxSize('5M')
    ->allowTypes(['pdf', 'docx'])
    ->save('/path/to/docs', 'custom-name.pdf');

// 파일 이동
upload()->move('/tmp/file.txt', '/final/path/file.txt');
```

### Upload 보안 검증 흐름

```text
upload()->file('avatar')->save('/uploads')
├─ 1. $_FILES 에러 확인 (UPLOAD_ERR_OK)
├─ 2. 파일 크기 검증 (max_size)
├─ 3. 파일명 살균 (guard()->filename())
│     ├─ null 바이트 제거
│     ├─ 이중 확장자 검사 (.php.jpg)
│     └─ 위험 확장자 차단 (.php, .exe 등)
├─ 4. 확장자 허용 목록 확인
├─ 5. MIME 타입 교차 검증 (finfo)
│     └─ 확장자-MIME 매핑 불일치 시 거부
└─ 6. move_uploaded_file()로 안전 저장
```

### Upload MIME 매핑

| 확장자 | 허용 MIME |
| --- | --- |
| `jpg`/`jpeg` | `image/jpeg` |
| `png` | `image/png` |
| `gif` | `image/gif` |
| `webp` | `image/webp` |
| `pdf` | `application/pdf` |
| `zip` | `application/zip`, `application/x-zip-compressed` |
| `csv` | `text/csv`, `text/plain` |
| `xlsx` | `application/vnd.openxmlformats-...spreadsheetml.sheet` |

매핑에 없는 커스텀 확장자는 MIME 검증을 건너뛴다.

---

## 도구 간 연동

```text
Tag → DB (tags/taggables 테이블)
Tag → Slug (슬러그 생성)
Tag → Guard (태그명/출력 살균)
Feed → Guard (아이템 살균)
Feed → Cache (피드 캐싱)
Image → (독립) GD 확장만 필요
Upload → Guard (파일명 살균)
Slug → (독립) 유니코드 처리
Text → (독립) mb_string 함수
```
