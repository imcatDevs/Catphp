# Text — 본문 처리

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Text` |
| 파일 | `catphp/Text.php` (103줄) |
| Shortcut | `text()` |
| 싱글턴 | `getInstance()` |
| 의존 확장 | `ext-mbstring` |

---

## 설정

별도 config 없음.

---

## 메서드 레퍼런스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `excerpt` | `excerpt(string $text, int $length = 200, string $suffix = '...'): string` | `string` | 발췌 생성 (단어 경계 자르기) |
| `readingTime` | `readingTime(string $text, int $koWpm = 500, int $enWpm = 200): string` | `string` | 읽기 시간 계산 |
| `wordCount` | `wordCount(string $text): int` | `int` | 단어 수 (한글+영문) |
| `charCount` | `charCount(string $text, bool $includeSpaces = false): int` | `int` | 문자 수 |
| `stripTags` | `stripTags(string $text): string` | `string` | HTML 태그 제거 |
| `truncate` | `truncate(string $text, int $length, string $suffix = '...'): string` | `string` | UTF-8 안전 자르기 |

---

## 사용 예제

### 발췌 생성

```php
$excerpt = text()->excerpt($post['body'], 150);
// → '이 게시글은 CatPHP 프레임워크의 핵심 기능을 설명합니다. 빠른 속도와...'

$excerpt = text()->excerpt('<p>HTML <b>포함</b> 텍스트</p>', 50);
// → 'HTML 포함 텍스트' (태그 자동 제거)
```

### 읽기 시간

```php
echo text()->readingTime($article['body']);
// → '3분'

// 커스텀 WPM (한글 400자/분, 영문 250단어/분)
echo text()->readingTime($text, koWpm: 400, enWpm: 250);
```

### 단어/문자 수

```php
$words = text()->wordCount($text);       // 한글 글자 + 영문 단어
$chars = text()->charCount($text);       // 공백 제외
$chars = text()->charCount($text, true); // 공백 포함
```

### UTF-8 자르기

```php
$title = text()->truncate('안녕하세요 CatPHP입니다', 10);
// → '안녕하세요 CatP...'  (mb_substr 0~9 = 10자)
```

---

## 내부 동작

### excerpt() 단어 경계

```text
excerpt('Lorem ipsum dolor sit amet', 15)
├─ strip_tags → 공백 정규화 → trim
├─ mb_strlen > 15? → 자르기
├─ mb_substr(0, 15) → 'Lorem ipsum dol'
├─ 마지막 공백 위치 (>70%) → 'Lorem ipsum'
└─ + '...' → 'Lorem ipsum...'
```

70% 지점 이후에 공백이 있으면 그 위치에서 자른다. 없으면 그냥 길이에서 자름.

### readingTime() 이중 WPM

한글과 영문의 읽기 속도가 다르므로 별도 계산:

```text
readingTime($text)
├─ 한글 글자 수 ÷ koWpm(500)
├─ 영문 단어 수 ÷ enWpm(200)
├─ 합산 → ceil → max(1분)
└─ '{N}분' 반환
```

### 한글 유니코드 범위

```text
[\x{AC00}-\x{D7AF}]  — 한글 완성형 (가~힣)
```

---

## 주의사항

1. **HTML 자동 제거**: `excerpt()`, `readingTime()`, `wordCount()`, `charCount()` 모두 내부에서 `strip_tags()` 호출.
2. **한글 = 1단어**: `wordCount()`에서 한글 한 글자를 1단어로 계산. '안녕하세요' = 5단어.
3. **최소 1분**: `readingTime()`은 항상 최소 1분 반환.
4. **suffix 커스텀**: `excerpt('...', 200, ' [더보기]')` — 접미사 변경 가능.

---

## 연관 도구

- [Feed](Feed.md) — 피드 description 생성
- [Search](Search.md) — 검색 결과 하이라이트
- [Meta](Meta.md) — SEO description 생성
