# Sanitizer — HTML 정제 + XSS 방어

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Sanitizer` |
| 파일 | `catphp/Sanitizer.php` (약 1000줄) |
| Shortcut | `sanitizer()` |
| 싱글턴 | `getInstance()` |
| 의존 도구 | `DOMDocument` (PHP 내장) |
| 테스트 | 195개 XSS 공격 벡터 통과 |

---

## 개요

WYSIWYG 에디터, 댓글, 게시판 등 사용자 입력 HTML에서 XSS 공격 벡터를 제거하는 보안 도구. DOMDocument 기반 파싱 + 7단계 보안 레이어로 다양한 공격을 방어한다.

### 방어 범위

- **기본 XSS**: `<script>`, 이벤트 핸들러 (`on*`)
- **인코딩 우회**: HTML 엔티티, URL 인코딩, 유니코드 정규화
- **mXSS**: Mutation XSS (noscript, noembed, xmp 등)
- **SVG/MathML**: 애니메이션 이벤트, xlink:href
- **DOM Clobbering**: 위험 id/name 속성
- **CSS 공격**: expression(), -moz-binding, behavior:
- **프로토타입 오염**: `__proto__`, constructor, prototype

---

## 메서드 레퍼런스

### 정제 메서드

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `clean` | `clean(string $html): string` | `string` | HTML 정제 (기본: 텍스트만 허용) |

### 허용 설정 (체이닝)

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `allowImages` | `allowImages(bool $v = true): self` | `self` | `<img>` 태그 허용 |
| `allowLinks` | `allowLinks(bool $v = true): self` | `self` | `<a>` 태그 허용 |
| `allowMedia` | `allowMedia(bool $v = true): self` | `self` | `<video>`, `<audio>` 허용 |
| `allowTables` | `allowTables(bool $v = true): self` | `self` | `<table>` 관련 태그 허용 |
| `allowTags` | `allowTags(array $tags): self` | `self` | 추가 태그 허용 |
| `allowAttrs` | `allowAttrs(array $attrs): self` | `self` | 추가 속성 허용 |
| `allowProtocols` | `allowProtocols(array $protocols): self` | `self` | 허용 프로토콜 변경 (기본: http, https, mailto) |

---

## 사용 예제

### 기본 사용법

```php
// 텍스트만 허용 (모든 태그 제거)
$clean = sanitizer()->clean($userHtml);

// 이미지 + 링크 허용
$clean = sanitizer()->allowImages()->allowLinks()->clean($content);

// 미디어 + 테이블 허용
$clean = sanitizer()->allowMedia()->allowTables()->clean($html);
```

### 커스텀 태그/속성

```php
$clean = sanitizer()
    ->allowImages()
    ->allowLinks()
    ->allowTags(['div', 'span', 'p', 'h1', 'h2', 'h3'])
    ->allowAttrs(['class', 'id', 'data-id'])
    ->clean($content);
```

### 블로그/CMS

```php
// 블로그 본문 (이미지, 링크, 기본 포맷팅)
$clean = sanitizer()
    ->allowImages()
    ->allowLinks()
    ->allowTags(['p', 'br', 'strong', 'em', 'u', 'blockquote', 'pre', 'code'])
    ->clean($postContent);
```

---

## 7단계 보안 레이어

```text
clean($html)
├─ 1. 제어문자/Null 바이트 제거
├─ 2. HTML 엔티티 다중 디코딩 (최대 3회)
├─ 3. DOMDocument 파싱
│   ├─ LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
│   └─ LIBXML_NOERROR | LIBXML_NOWARNING
├─ 4. 위험 태그/속성 제거
│   ├─ 위험 태그: script, style, iframe, form, ...
│   ├─ 이벤트 핸들러: on* (전체 패턴)
│   ├─ 위험 속성: style, srcdoc, formaction, xlink:href, ...
│   └─ DOM Clobbering: 위험 id/name 값
├─ 5. URL 프로토콜 검증
│   ├─ 허용: http, https, mailto
│   └─ 차단: javascript, vbscript, data, blob, ...
├─ 6. 위험 내용 검사
│   ├─ 프로토콜 우회 (공백 삽입 등)
│   └─ CSS 공격 (expression, moz-binding)
└─ 7. 정규식 최종 정리
    ├─ mXSS 패턴 제거
    └─ 네임스페이스 속성 제거
```

---

## 위험 태그 목록

자동 제거되는 태그 (내용까지 완전 삭제):

| 카테고리 | 태그 |
| --- | --- |
| 스크립트 | `script`, `noscript`, `template` |
| 스타일 | `style` |
| 프레임 | `iframe`, `frame`, `frameset` |
| 객체 | `object`, `embed`, `applet` |
| 폼 | `form`, `input`, `button`, `select`, `textarea` |
| 메타 | `meta`, `link`, `base` |
| SVG 애니메이션 | `animate`, `animateMotion`, `animateTransform`, `set` |
| 기타 | `xmp`, `listing`, `plaintext`, `noembed`, `noframes` |

---

## 위험 속성 목록

자동 제거되는 속성:

| 카테고리 | 속성 |
| --- | --- |
| 이벤트 | `on*` (전체 패턴) |
| 스타일 | `style` |
| 스크립트 실행 | `srcdoc`, `formaction`, `action`, `poster`, `data` |
| 네임스페이스 | `xlink:href`, `xmlns`, `xmlns:xlink` |
| 폼 관련 | `form`, `formmethod`, `formtarget`, `formenctype` |
| 기타 | `autofocus`, `contenteditable`, `draggable`, `ping`, `tabindex` |

---

## DOM Clobbering 방어

위험한 id/name 값 자동 제거:

```php
// 차단되는 값 (소문자 기준)
'document', 'window', 'location', 'navigator',
'__proto__', 'constructor', 'prototype',
'attributes', 'createelement', 'getelementbyid', 'queryselector',
'innerhtml', 'outerhtml', 'appendchild', ...
```

---

## 공격 방어 예시

| 입력 | 결과 | 설명 |
| --- | --- | --- |
| `<script>alert(1)</script>` | `(빈 문자열)` | 스크립트 태그 제거 |
| `<img src=x onerror=alert(1)>` | `<img src="x">` | 이벤트 핸들러 제거 |
| `<a href="javascript:alert(1)">X</a>` | `<a>X</a>` | 위험 URL 제거 |
| `<a href="&#x6A;avascript:...">` | `<a>X</a>` | 인코딩 우회 차단 |
| `<img id="document" src="x">` | `<img src="x">` | DOM Clobbering 방어 |
| `<svg><animate onbegin=alert(1)>` | `<svg></svg>` | SVG 이벤트 차단 |

---

## 보안 고려사항

1. **다층 방어**: Sanitizer는 CSP 헤더, 출력 이스케이프와 함께 사용해야 함
2. **허용 태그 최소화**: 필요한 태그만 허용 (`allowTags()` 최소화)
3. **style 속성 차단**: 기본적으로 style 속성은 차단됨 (CSS 공격 방어)
4. **data URI 차단**: `data:` 프로토콜은 기본 차단

---

## 주의사항

1. **기본값은 텍스트만**: `clean()` 기본값은 모든 HTML 태그를 제거함. 필요한 태그는 `allow*()`로 명시.

2. **체이닝 필수**: 허용 설정은 체이닝으로 연결해야 함:
   ```php
   // 올바른 사용
   sanitizer()->allowImages()->allowLinks()->clean($html);
   
   // 잘못된 사용 (설정이 적용되지 않음)
   sanitizer()->allowImages();
   sanitizer()->clean($html);  // 이미 새 인스턴스
   ```

3. **DOMDocument 의존**: PHP `dom` 확장 필요 (`apt install php-dom`).

4. **대용량 HTML**: 매우 큰 HTML은 메모리 사용량 증가. 필요시 분할 처리.

---

## 연관 도구

- [Guard](Guard.md) — 입력 살균 (XSS, SQL 인젝션, 경로 순회)
- [Csrf](Csrf.md) — CSRF 토큰 보호
- [Firewall](Firewall.md) — IP 차단
- [Valid](Valid.md) — 입력 검증
