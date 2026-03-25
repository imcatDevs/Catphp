# Slug — URL 슬러그

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Slug` |
| 파일 | `catphp/Slug.php` (57줄) |
| Shortcut | `slug()` |
| 싱글턴 | `getInstance()` |
| 의존 확장 | `ext-mbstring` |

---

## 설정

별도 config 없음.

---

## 메서드 레퍼런스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `make` | `make(string $text, string $separator = '-'): string` | `string` | 슬러그 생성 (유니코드 보존) |
| `unique` | `unique(string $text, callable $existsCheck, string $separator = '-', int $maxAttempts = 100): string` | `string` | 고유 슬러그 생성 (중복 시 suffix) |

---

## 사용 예제

### 기본 슬러그

```php
slug()->make('Hello World');          // 'hello-world'
slug()->make('CatPHP 프레임워크');     // 'catphp-프레임워크'
slug()->make('안녕하세요 세계');       // '안녕하세요-세계'
slug()->make('Hello, World! #2025');  // 'hello-world-2025'
slug()->make('  Multiple   Spaces '); // 'multiple-spaces'
```

### 커스텀 구분자

```php
slug()->make('Hello World', '_');     // 'hello_world'
```

### 고유 슬러그 (DB 중복 확인)

```php
$slug = slug()->unique('게시글 제목', function (string $slug): bool {
    return db()->table('posts')->where('slug', $slug)->first() !== null;
});
// 1차: '게시글-제목'
// 중복 시: '게시글-제목-2', '게시글-제목-3', ...
```

### 게시글 생성 시

```php
$title = input('title');
$slug = slug()->unique($title, fn($s) => db()->table('posts')->where('slug', $s)->first() !== null);

db()->table('posts')->insert([
    'title' => $title,
    'slug'  => $slug,
    'body'  => input('body'),
]);
```

---

## 내부 동작

### make() 흐름

```text
make('Hello, World! #2025')
├─ mb_strtolower → 'hello, world! #2025'
├─ 유니코드 문자(L), 숫자(N), 공백만 유지 → 'hello world 2025'
├─ 공백 → 구분자 → 'hello-world-2025'
├─ 연속 구분자 제거
└─ 양끝 구분자 trim
```

### 유니코드 보존

```php
preg_replace('/[^\p{L}\p{N}\s]/u', '', $text)
```

`\p{L}` — 모든 유니코드 문자 (한글, 일본어, 중국어 포함). 영문 전용 slug가 아닌 **다국어 slug** 생성.

### unique() 흐름

```text
unique('제목', $existsCheck)
├─ base = make('제목') → '제목'
├─ existsCheck('제목') → true (중복)
├─ '제목-2' → true (중복)
├─ '제목-3' → false (고유!)
└─ 반환: '제목-3'
```

최대 100회 시도 후 `RuntimeException`.

---

## 주의사항

1. **유니코드 slug**: 한글/일본어/중국어 문자가 그대로 URL에 포함된다. 영문 전용이 필요하면 transliteration 라이브러리 별도 사용.
2. **특수문자 제거**: `!`, `@`, `#`, `$`, `,`, `.` 등 모든 특수문자가 제거된다.
3. **maxAttempts**: `unique()`는 최대 100회 시도. 매우 인기 있는 제목은 실패할 수 있다.
4. **대소문자**: `mb_strtolower()` — 영문은 소문자로 변환. 'CatPHP' → 'catphp'.

---

## 연관 도구

- [Tag](Tag.md) — 태그 slug 생성 (내부 사용)
- [Feed](Feed.md) — 피드 아이템 링크
- [Meta](Meta.md) — canonical URL
- [DB](DB.md) — `unique()` 중복 확인
