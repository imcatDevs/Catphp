<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Sanitizer — 웹 에디터용 HTML 정제 도구
 *
 * 웹 에디터(WYSIWYG)에서 작성한 HTML 콘텐츠에서 스크립트와 위험 요소만 제거하고
 * 안전한 HTML 태그는 유지합니다.
 *
 * @config array{
 *     allowed_tags?: array<string>,      // 허용 태그 목록
 *     allowed_attrs?: array<string>,     // 허용 속성 목록
 *     allowed_protocols?: array<string>, // 허용 URL 프로토콜
 * } sanitizer → config('sanitizer.allowed_tags')
 *
 * @example
 * // 기본 사용법
 * $safe = sanitizer()->clean('<p onclick="alert(1)">Hello</p><script>alert(1)</script>');
 * // 결과: <p>Hello</p>
 *
 * // 커스텀 허용 태그
 * $safe = sanitizer()->allowTags(['p', 'br', 'strong'])->clean($html);
 *
 * // 이미지 허용
 * $safe = sanitizer()->allowImages()->clean($html);
 *
 * // 링크 허용
 * $safe = sanitizer()->allowLinks()->clean($html);
 */
final class Sanitizer
{
    // ─────────────────────────────────────────────────────────────────────
    //  위험 요소 상수 (유지보수 용이)
    // ─────────────────────────────────────────────────────────────────────

    /** @var array<string> 위험 태그 (내용까지 완전 제거) */
    private const DANGEROUS_TAGS = [
        // 스크립트/스타일
        'script', 'style', 'noscript', 'template', 'slot',
        // 임베드/프레임
        'iframe', 'frame', 'frameset', 'object', 'embed', 'applet',
        // 메타/링크
        'meta', 'link', 'base',
        // 폼 (폼 기반 XSS 방지)
        'form', 'input', 'button', 'select', 'textarea', 'option', 'optgroup',
        // SVG 애니메이션 (SMIL 기반 XSS)
        'animate', 'animatemotion', 'animatetransform', 'set', 'animacolor',
        // 기타 위험 태그
        'marquee', 'bgsound', 'xml', 'xss',
    ];

    /** @var array<string> 위험 속성 */
    private const DANGEROUS_ATTRS = [
        // 스크립트/코드 실행 관련
        'srcdoc',           // iframe 내 HTML
        'formaction',       // 폼 액션 오버라이드
        'action',           // 폼 액션
        // poster는 URL 검증으로 처리 (allowMedia 시 허용)
        'data',             // object 데이터
        'code',             // applet 코드
        'codebase',         // applet 코드베이스
        // 네임스페이스/외부 참조
        'xlink:href',       // SVG 외부 참조
        'xmlns',            // XML 네임스페이스
        'xmlns:xlink',      // XLink 네임스페이스
        // 스타일 관련 (CSS 공격)
        'style',            // 인라인 스타일 (expression 등)
        // 폼 관련
        'form',             // 폼 ID 참조
        'formmethod',       // 폼 메서드 오버라이드
        'formtarget',       // 폼 타겟 오버라이드
        'formenctype',      // 폼 인코딩 오버라이드
        'formnovalidate',   // 폼 검증 비활성화
        // 추적/네비게이션
        'ping',             // 링크 추적
        // 기타 위험 속성
        'autofocus',        // 자동 포커스 + onfocus 조합
        'contenteditable',  // 사용자 DOM 조작 가능
        'draggable',        // 드래그 이벤트 트리거
        'dropzone',         // 드롭 이벤트 트리거
        'contextmenu',      // 컨텍스트 메뉴 이벤트
        'accesskey',        // 키보드 단축키 이벤트
        'tabindex',         // 포커스 순서 (onfocus 트리거)
        'translate',        // 번역 제어
        'spellcheck',       // 맞춤법 검사 이벤트
    ];

    /** @var array<string> 위험 URL 프로토콜 */
    private const DANGEROUS_PROTOCOLS = [
        'javascript:', 'vbscript:', 'data:', 'blob:',
        'file:', 'about:', 'chrome:', 'mhtml:', 'mocha:',
        'livescript:', 'jscript:',
    ];

    /** @var array<string> 위험 ID/Name 값 (DOM Clobbering 방어) */
    private const DANGEROUS_IDS = [
        // Prototype pollution
        '__proto__', 'constructor', 'prototype',
        // 전역 함수
        'eval', 'alert', 'confirm', 'prompt',
        // 전역 객체
        'document', 'window', 'globalthis', 'self', 'top', 'parent', 'frames',
        'location', 'navigator', 'cookie', 'localstorage', 'sessionstorage',
        // API
        'xmlhttprequest', 'fetch', 'worker', 'sharedworker',
        // DOM 생성자
        'image', 'audio', 'option', 'element', 'node',
        // 타이머
        'settimeout', 'setinterval', 'requestanimationframe',
        // 인코딩
        'escape', 'unescape', 'decodeuri', 'encodeuri',
        // DOM 메서드 오염
        'attributes', 'createelement', 'getelementbyid', 'queryselector',
        'queryselectorall', 'getelementsbyclassname', 'getelementsbytagname',
        'innerhtml', 'outerhtml', 'insertadjacenthtml',
        'appendchild', 'removechild', 'replacechild',
        'setattribute', 'getattribute', 'removeattribute',
        'addeventlistener', 'removeeventlistener', 'dispatchevent',
        'clonenode', 'importnode', 'adoptnode',
        'createdocumentfragment', 'createtextnode', 'createcomment',
        'createattribute', 'createevent', 'createrange',
        // DOM 속성
        'defaultview', 'ownerdocument', 'parentnode', 'parentelement',
        'childnodes', 'firstchild', 'lastchild', 'nextsibling', 'previoussibling',
        'style', 'classname', 'classlist', 'id', 'name', 'src', 'href',
        'form', 'forms', 'body', 'head', 'documentelement',
        'scripts', 'links', 'images', 'anchors', 'applets',
        'cookie', 'domain', 'referrer', 'url', 'title',
        'readystate', 'status', 'responsetext', 'responsexml',
    ];

    private static ?self $instance = null;

    /** @var array<string> 허용 태그 */
    private array $allowedTags;

    /** @var array<string> 허용 속성 */
    private array $allowedAttrs;

    /** @var array<string> 허용 URL 프로토콜 */
    private array $allowedProtocols;

    /** @var bool 이미지 허용 여부 */
    private bool $allowImages = false;

    /** @var bool 링크 허용 여부 */
    private bool $allowLinks = false;

    /** @var bool 테이블 허용 여부 */
    private bool $allowTables = false;

    /** @var bool 미디어(비디오/오디오) 허용 여부 */
    private bool $allowMedia = false;

    /** @var callable|null 커스텀 필터 콜백 */
    private $customFilter = null;

    private function __construct()
    {
        $this->reset();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * 설정 초기화
     */
    private function reset(): void
    {
        // 기본 허용 태그 (텍스트 포맷팅)
        $this->allowedTags = [
            'p', 'br', 'hr',
            'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'ins',
            'sub', 'sup', 'small', 'mark', 'span',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'blockquote', 'pre', 'code', 'kbd', 'samp', 'var',
            'ul', 'ol', 'li', 'dl', 'dt', 'dd',
            'div', 'article', 'section', 'header', 'footer', 'nav', 'aside',
            'figure', 'figcaption',
            'abbr', 'cite', 'dfn', 'q', 'time', 'address',
        ];

        // 기본 허용 속성
        $this->allowedAttrs = [
            'class', 'id', 'title', 'lang', 'dir',
            'data-*', // data 속성은 별도 처리
        ];

        // 허용 URL 프로토콜
        $this->allowedProtocols = ['http', 'https', 'mailto', 'tel', 'ftp'];

        $this->allowImages = false;
        $this->allowLinks = false;
        $this->allowTables = false;
        $this->allowMedia = false;
        $this->customFilter = null;

        // 설정 파일에서 오버라이드
        $config = \config('sanitizer');
        if (is_array($config)) {
            if (isset($config['allowed_tags']) && is_array($config['allowed_tags'])) {
                $this->allowedTags = $config['allowed_tags'];
            }
            if (isset($config['allowed_attrs']) && is_array($config['allowed_attrs'])) {
                $this->allowedAttrs = $config['allowed_attrs'];
            }
            if (isset($config['allowed_protocols']) && is_array($config['allowed_protocols'])) {
                $this->allowedProtocols = $config['allowed_protocols'];
            }
        }
    }

    /**
     * HTML 정제 (메인 메서드)
     *
     * @param string $html 정제할 HTML
     * @return string 정제된 안전한 HTML
     */
    public function clean(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // 1. null 바이트 및 제어문자 제거
        $html = $this->removeNullBytes($html);

        // 2. 인코딩 우회 방어 (다중 인코딩 디코딩)
        $html = $this->decodeEntities($html);

        // 3. 현재 설정 기반 허용 태그/속성 목록 구성
        $allowedTags = $this->buildAllowedTags();
        $allowedAttrs = $this->buildAllowedAttrs();

        // 4. DOMDocument를 사용한 정제
        $html = $this->sanitizeWithDom($html, $allowedTags, $allowedAttrs);

        // 5. 남아있을 수 있는 위험 패턴 정리
        $html = $this->finalCleanup($html);

        // 6. 커스텀 필터 적용
        if ($this->customFilter !== null) {
            $html = ($this->customFilter)($html);
        }

        // 7. 설정 초기화 (체이닝 후 재사용 대비)
        $this->reset();

        return $html;
    }

    /**
     * null 바이트 및 제어문자 제거
     */
    private function removeNullBytes(string $html): string
    {
        // null 바이트 및 제로폭 문자 제거
        $html = preg_replace('/[\x00\x{200B}\x{200C}\x{200D}\x{FEFF}\x{00AD}]/u', '', $html) ?? $html;
        // 비공백 제어문자 제거
        $html = preg_replace('/[\x01-\x08\x0B\x0C\x0E-\x1F]/', '', $html) ?? $html;
        return $html;
    }

    /**
     * HTML 엔티티 디코딩 (다중 인코딩 우회 방어)
     */
    private function decodeEntities(string $html): string
    {
        $decoded = $html;
        for ($i = 0; $i < 3; $i++) {
            $prev = $decoded;
            $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $prev) {
                break;
            }
        }
        return $decoded;
    }

    /**
     * 현재 설정 기반 허용 태그 목록 구성
     */
    private function buildAllowedTags(): array
    {
        $tags = $this->allowedTags;

        if ($this->allowLinks) {
            $tags[] = 'a';
        }

        if ($this->allowImages) {
            $tags[] = 'img';
            $tags[] = 'picture';
            $tags[] = 'source';
        }

        if ($this->allowTables) {
            $tags = array_merge($tags, ['table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col']);
        }

        if ($this->allowMedia) {
            $tags = array_merge($tags, ['video', 'audio', 'source', 'track']);
        }

        return array_unique($tags);
    }

    /**
     * 현재 설정 기반 허용 속성 목록 구성
     */
    private function buildAllowedAttrs(): array
    {
        $attrs = $this->allowedAttrs;

        if ($this->allowLinks) {
            $attrs[] = 'href';
            $attrs[] = 'target';
            $attrs[] = 'rel';
            $attrs[] = 'download';
        }

        if ($this->allowImages) {
            $attrs[] = 'src';
            $attrs[] = 'alt';
            $attrs[] = 'width';
            $attrs[] = 'height';
            $attrs[] = 'loading';
            $attrs[] = 'srcset';
            $attrs[] = 'sizes';
        }

        if ($this->allowMedia) {
            $attrs[] = 'controls';
            $attrs[] = 'autoplay';
            $attrs[] = 'loop';
            $attrs[] = 'muted';
            $attrs[] = 'poster';
            $attrs[] = 'preload';
        }

        return array_unique($attrs);
    }

    /**
     * DOMDocument를 사용한 HTML 정제
     */
    private function sanitizeWithDom(string $html, array $allowedTags, array $allowedAttrs): string
    {
        // UTF-8 인코딩 처리를 위한 래퍼
        $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';

        $dom = new \DOMDocument();
        $dom->encoding = 'UTF-8';

        // libxml 에러 억제 (HTML5 태그 등)
        $previousLibxml = libxml_use_internal_errors(true);

        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);

        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxml);

        $body = $dom->getElementsByTagName('body')->item(0);

        if ($body === null) {
            return '';
        }

        // 노드 정제 (재귀)
        $this->sanitizeNode($body, $allowedTags, $allowedAttrs);

        // 결과 추출
        $result = '';
        foreach ($body->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return $result;
    }

    /**
     * 노드 재귀 정제
     */
    private function sanitizeNode(\DOMNode $node, array $allowedTags, array $allowedAttrs): void
    {
        // 제거할 노드들을 나중에 한 번에 처리
        $toRemove = [];

        /** @var \DOMNode $child */
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $tagName = strtolower($child->nodeName);

                // 위험 태그는 내용까지 완전 제거
                if (in_array($tagName, self::DANGEROUS_TAGS, true)) {
                    $toRemove[] = $child;
                    continue;
                }

                // 허용되지 않은 태그 제거 (내용은 유지)
                if (!in_array($tagName, $allowedTags, true)) {
                    // 태그 제거하되 내용은 유지
                    $toRemove[] = $child;
                    continue;
                }

                // 속성 정제
                if ($child->hasAttributes()) {
                    $this->sanitizeAttributes($child, $allowedAttrs, $tagName);
                }

                // 재귀 처리
                $this->sanitizeNode($child, $allowedTags, $allowedAttrs);
            } elseif ($child->nodeType === XML_COMMENT_NODE) {
                // 주석 제거 (IE 조건부 주석 포함)
                $toRemove[] = $child;
            } elseif ($child->nodeType === XML_CDATA_SECTION_NODE) {
                // CDATA 섹션 제거 (XML/XHTML에서 스크립트 실행 가능)
                $toRemove[] = $child;
            } elseif ($child->nodeType === XML_PI_NODE) {
                // Processing Instruction 제거 (<?xml-stylesheet 등)
                $toRemove[] = $child;
            }

            // 텍스트 노드 검사 (위험한 내용 포함 여부)
            if ($child->nodeType === XML_TEXT_NODE && $child->nodeValue !== null) {
                // 텍스트 노드 내 위험 패턴 검사는 finalCleanup에서 처리
            }
        }

        // 제거 처리
        foreach ($toRemove as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $tagName = strtolower($child->nodeName);

                // 위험 태그는 내용까지 완전 제거
                if (in_array($tagName, self::DANGEROUS_TAGS, true)) {
                    $child->parentNode->removeChild($child);
                    continue;
                }

                // 그 외 태그는 내용을 부모로 이동 후 제거
                while ($child->firstChild !== null) {
                    $child->parentNode->insertBefore($child->firstChild, $child);
                }
            }
            if ($child->parentNode !== null) {
                $child->parentNode->removeChild($child);
            }
        }
    }

    /**
     * 속성 정제
     */
    private function sanitizeAttributes(\DOMElement $element, array $allowedAttrs, string $tagName): void
    {
        $toRemove = [];

        foreach ($element->attributes as $attr) {
            $attrName = strtolower($attr->name);
            $attrValue = $attr->value;

            // 속성명 HTML 엔티티 디코딩 (우회 방어)
            $decodedName = $attrName;
            for ($i = 0; $i < 3; $i++) {
                $prev = $decodedName;
                $decodedName = html_entity_decode($decodedName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($decodedName === $prev) break;
            }
            $decodedName = strtolower($decodedName);

            // ── DOM clobbering 방어 ──
            // id/name 속성으로 전역 변수 오염 방지
            if (in_array($decodedName, ['id', 'name'], true)) {
                $lowerValue = strtolower(trim($attrValue));
                if (in_array($lowerValue, self::DANGEROUS_IDS, true)) {
                    $toRemove[] = $attr;
                    continue;
                }
                // 숫자로 시작하는 id도 차단 (배열 인덱스 오염)
                if (preg_match('/^\d/', $attrValue)) {
                    $toRemove[] = $attr;
                    continue;
                }
            }

            // ── Prototype pollution 방어 ──
            // data-* 속성에서 위험한 키
            if (str_starts_with($decodedName, 'data-')) {
                $lowerValue = strtolower($attrValue);
                $dangerousDataKeys = ['__proto__', 'constructor', 'prototype', '__defineGetter__', '__defineSetter__'];
                foreach ($dangerousDataKeys as $key) {
                    if (str_contains($decodedName, $key) || str_contains($lowerValue, $key)) {
                        $toRemove[] = $attr;
                        continue 2;
                    }
                }
            }

            // 이벤트 핸들러 패턴 검사 (on* 속성)
            if (str_starts_with($decodedName, 'on') || preg_match('/\bon\w+/i', $decodedName)) {
                $toRemove[] = $attr;
                continue;
            }

            // ── 위험 속성 명시적 차단 ──
            if (in_array($decodedName, self::DANGEROUS_ATTRS, true)) {
                $toRemove[] = $attr;
                continue;
            }

            // data-* 속성 특별 처리
            if (str_starts_with($decodedName, 'data-')) {
                // data 속성은 값 검증만
                if ($this->hasDangerousContent($attrValue)) {
                    $toRemove[] = $attr;
                }
                continue;
            }

            // 허용된 속성인지 확인 (원본명과 디코딩된명 모두 검사)
            if (!in_array($attrName, $allowedAttrs, true) && !in_array($decodedName, $allowedAttrs, true)) {
                $toRemove[] = $attr;
                continue;
            }

            // href, src 속성 특별 검증
            if (in_array($decodedName, ['href', 'src', 'srcset'], true)) {
                if (!$this->isSafeUrl($attrValue, $decodedName)) {
                    $toRemove[] = $attr;
                    continue;
                }
            }

            // 위험한 내용 검사
            if ($this->hasDangerousContent($attrValue)) {
                $toRemove[] = $attr;
            }
        }

        // 속성 제거
        foreach ($toRemove as $attr) {
            $element->removeAttributeNode($attr);
        }
    }

    /**
     * URL 안전성 검사
     */
    private function isSafeUrl(string $url, string $attrName): bool
    {
        $url = trim($url);

        // 빈 URL 허용
        if ($url === '') {
            return true;
        }

        // ── 1. 제어문자/공백 제거 (우회 방어) ──
        // java\x00script:, java\x09script:, java\x0ascript: 등
        $url = preg_replace('/[\x00-\x20\x7f]/', '', $url);

        // ── 2. 유니코드 정규화 (NFKC) ──
        // 전각 문자 등 정상 문자로 변환 후 검사
        if (function_exists('normalizer_normalize')) {
            $normalized = normalizer_normalize($url, \Normalizer::FORM_KC);
            if ($normalized !== false) {
                $url = $normalized;
            }
        }

        // ── 3. 대소문자 정규화 ──
        $lowerUrl = strtolower($url);

        // ── 4. 위험 프로토콜 직접 검사 (parse_url 우회 방어) ──
        // parse_url은 "javascript:alert(1)"을 scheme으로 파싱하지만
        // "java\tscript:" 등은 파싱 실패할 수 있음
        foreach (self::DANGEROUS_PROTOCOLS as $prefix) {
            if (str_starts_with($lowerUrl, $prefix)) {
                return false;
            }
        }

        // ── 5. 인코딩된 위험 프로토콜 검사 ──
        // &#106;avascript: (j가 &#106;)
        $decodedUrl = $url;
        for ($i = 0; $i < 3; $i++) {
            $prev = $decodedUrl;
            $decodedUrl = html_entity_decode($decodedUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decodedUrl === $prev) break;
        }
        $lowerDecoded = strtolower($decodedUrl);

        foreach (self::DANGEROUS_PROTOCOLS as $prefix) {
            if (str_starts_with($lowerDecoded, $prefix)) {
                return false;
            }
        }

        // ── 6. URL 인코딩된 위험 프로토콜 검사 ──
        // %6A%61%76%61%73%63%72%69%70%74 (javascript)
        $urlDecoded = $url;
        for ($i = 0; $i < 3; $i++) {
            $prev = $urlDecoded;
            $urlDecoded = rawurldecode($urlDecoded);
            if ($urlDecoded === $prev) break;
        }
        $lowerUrlDecoded = strtolower($urlDecoded);

        foreach (self::DANGEROUS_PROTOCOLS as $prefix) {
            if (str_starts_with($lowerUrlDecoded, $prefix)) {
                return false;
            }
        }

        // ── 7. 상대 경로 허용 ──
        if (str_starts_with($url, '/') || str_starts_with($url, './') || str_starts_with($url, '../')) {
            // 하지만 //example.com 같은 프로토콜 상대 URL은 검증 필요
            if (str_starts_with($url, '//')) {
                // 프로토콜 상대 URL - 현재 페이지의 프로토콜 사용
                // 허용하되, 위험한 도메인은 차단하지 않음 (단순화)
                return true;
            }
            return true;
        }

        // ── 8. 앵커 허용 ──
        if (str_starts_with($url, '#')) {
            // DOM clobbering 방지: id="x" name="x" 등
            // 앵커 자체는 안전하므로 허용
            return true;
        }

        // ── 9. 프로토콜 추출 ──
        $protocol = parse_url($url, PHP_URL_SCHEME);

        if ($protocol === null) {
            // 프로토콜 없음 = 상대 경로 또는 프로토콜 상대 URL
            return true;
        }

        $protocol = strtolower($protocol);

        // ── 10. 허용된 프로토콜인지 확인 ──
        if (!in_array($protocol, $this->allowedProtocols, true)) {
            return false;
        }

        // ── 11. data URI 특별 처리 ──
        if ($protocol === 'data') {
            // data: URI는 기본적으로 차단
            // 이미 위에서 차단했지만 한 번 더 확인
            return false;
        }

        return true;
    }

    /**
     * 위험한 내용 포함 여부 검사
     * 주의: 속성명 자체가 차단된 것들은 여기서 검사하지 않음
     */
    private function hasDangerousContent(string $value): bool
    {
        // ── 1. 제어문자 제거 후 검사 ──
        $cleaned = preg_replace('/[\x00-\x20\x7f]/', '', $value);

        // ── 2. 유니코드 정규화 ──
        if (function_exists('normalizer_normalize')) {
            $normalized = normalizer_normalize($cleaned, \Normalizer::FORM_KC);
            if ($normalized !== false) {
                $cleaned = $normalized;
            }
        }

        $lower = strtolower($cleaned);

        // ── 3. 위험한 프로토콜 ──
        // 공백/탭 삽입 우회 방어
        if (preg_match('/(j\s*a\s*v\s*a\s*s\s*c\s*r\s*i\s*p\s*t|v\s*b\s*s\s*c\s*r\s*i\s*p\s*t|d\s*a\s*t\s*a)\s*:/i', $cleaned)) {
            return true;
        }

        // ── 4. CSS 표현식 공격 ──
        // style 속성은 차단되지만, 다른 곳에 포함될 수 있으니 검사
        if (str_contains($lower, 'expression(')) {
            return true;
        }
        if (preg_match('/expr\s*e\s*s\s*s\s*i\s*o\s*n/i', $cleaned)) {
            return true;
        }

        // ── 5. Firefox 바인딩 공격 ──
        if (str_contains($lower, '-moz-binding') || str_contains($lower, 'moz-binding')) {
            return true;
        }

        // ── 6. IE behavior 공격 ──
        if (str_contains($lower, 'behavior:')) {
            return true;
        }

        // ── 7. 인코딩된 위험 문자열 ──
        $decoded = $cleaned;
        for ($i = 0; $i < 3; $i++) {
            $prev = $decoded;
            $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $prev) break;
        }

        if (strtolower($decoded) !== $lower) {
            // 디코딩 후 다시 검사
            return $this->hasDangerousContent($decoded);
        }

        return false;
    }

    /**
     * 최종 정리 (정규식 기반)
     */
    private function finalCleanup(string $html): string
    {
        // ── 1. 스크립트 태그 제거 ──
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        // 자체 닫힘 스크립트
        $html = preg_replace('/<script\b[^>]*\/?>/i', '', $html) ?? $html;

        // ── 2. 이벤트 핸들러 제거 ──
        // 공백/탭/개행/슬래시로 구분된 on* 속성
        $html = preg_replace('/[\s\/]+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|`[^`]*`|[^\s>]*)/i', '', $html) ?? $html;

        // ── 3. style 속성 내 위험 패턴 ──
        // javascript:, expression(), behavior, -moz-binding
        $html = preg_replace('/style\s*=\s*"[^"]*(javascript|expression|behavior|-moz-binding|url\s*\()[^"]*"/is', '', $html) ?? $html;
        $html = preg_replace('/style\s*=\s*\'[^\']*(javascript|expression|behavior|-moz-binding|url\s*\()[^\']*\'/is', '', $html) ?? $html;
        $html = preg_replace('/style\s*=\s*[^"\'][^\s>]*(javascript|expression|behavior|-moz-binding)/i', '', $html) ?? $html;

        // ── 4. 위험한 프로토콜이 포함된 href/src ──
        $html = preg_replace('/(href|src|srcset|action|formaction|poster|data|code|codebase|cite|background|longdesc|usemap|profile)\s*=\s*"(javascript|vbscript|data|blob|file):[^"]*"/is', '', $html) ?? $html;
        $html = preg_replace('/(href|src|srcset|action|formaction|poster|data|code|codebase|cite|background|longdesc|usemap|profile)\s*=\s*\'(javascript|vbscript|data|blob|file):[^\']*\'/is', '', $html) ?? $html;

        // ── 5. SVG 관련 위험 속성 ──
        $html = preg_replace('/xlink:href\s*=\s*"[^"]*"/i', '', $html) ?? $html;
        $html = preg_replace('/xlink:href\s*=\s*\'[^\']*\'/i', '', $html) ?? $html;

        // ── 6. xmlns 네임스페이스 ──
        $html = preg_replace('/xmlns\s*=\s*"[^"]*"/i', '', $html) ?? $html;
        $html = preg_replace('/xmlns:\w+\s*=\s*"[^"]*"/i', '', $html) ?? $html;

        // ── 7. data-* 속성 내 위험 패턴 ──
        // data-* 속성은 허용하지만 위험한 값은 제거
        $html = preg_replace('/data-[\w-]+\s*=\s*"(javascript|vbscript|data):[^"]*"/i', '', $html) ?? $html;
        $html = preg_replace('/data-[\w-]+\s*=\s*\'(javascript|vbscript|data):[^\']*\'/i', '', $html) ?? $html;

        // ── 8. srcset 특별 처리 ──
        // srcset="image.png 1x, javascript:alert(1) 2x"
        if (preg_match('/srcset\s*=/i', $html)) {
            $html = preg_replace_callback('/srcset\s*=\s*("[^"]+"|\'[^\']+\'|[^\s>]+)/i', function ($matches) {
                $attrValue = trim($matches[1], '"\'');
                $urls = explode(',', $attrValue);
                $safe = [];
                foreach ($urls as $url) {
                    $url = trim($url);
                    if ($this->isSafeUrl($url, 'srcset')) {
                        $safe[] = $url;
                    }
                }
                return 'srcset="' . implode(', ', $safe) . '"';
            }, $html) ?? $html;
        }

        // ── 9. mXSS 방어: 중첩 태그 정리 ──
        // <<script> -> <script> 등
        $html = preg_replace('/<+</', '<', $html) ?? $html;
        $html = preg_replace('/>+</', '><', $html) ?? $html;

        // ── 10. DOM clobbering 방어 ──
        // id="x" name="x" 등은 별도 처리하지 않음
        // (전역 변수 오염 방지는 사용자 측에서 처리)

        // ── 11. HTML5 시맨틱 태그 내 위험 요소 ──
        // <template>, <slot> 등은 기본적으로 제거됨

        // ── 12. null 바이트 최종 제거 ──
        $html = str_replace("\x00", '', $html);

        // ── 13. 제어문자 최종 제거 ──
        $html = preg_replace('/[\x01-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $html) ?? $html;

        // ── 14. IE 조건부 주석 제거 ──
        // <!--[if IE]><script>alert(1)</script><![endif]-->
        $html = preg_replace('/<!--\[if[^\]]*\]>.*?<!\[endif\]-->/is', '', $html) ?? $html;
        $html = preg_replace('/<!\[if[^\]]*\]>.*?<!\[endif\]>/is', '', $html) ?? $html;

        // ── 15. SVG use 태그 (외부 참조) ──
        // <use xlink:href="http://evil.com/x.svg#xss">
        $html = preg_replace('/<use\b[^>]*>/i', '', $html) ?? $html;

        // ── 16. poster 속성 (비디오 썸네일) ──
        $html = preg_replace('/poster\s*=\s*"(javascript|vbscript|data):[^"]*"/i', '', $html) ?? $html;
        $html = preg_replace('/poster\s*=\s*\'(javascript|vbscript|data):[^\']*\'/i', '', $html) ?? $html;

        // ── 17. tabindex 음수 값 (접근성 공격) ──
        // 음수 tabindex는 포커스 불가, 하지만 보안상 위험하지 않음
        // 그러나 tabindex와 onfocus 조합 주의

        // ── 18. contenteditable 속성 ──
        // contenteditable="true"는 사용자가 DOM 조작 가능
        $html = preg_replace('/contenteditable\s*=\s*["\']?true["\']?/i', '', $html) ?? $html;

        // ── 19. srcdoc 속성 (iframe) ──
        $html = preg_replace('/srcdoc\s*=\s*"[^"]*"/i', '', $html) ?? $html;
        $html = preg_replace('/srcdoc\s*=\s*\'[^\']*\'/i', '', $html) ?? $html;

        // ── 20. autofocus + onfocus 조합 ──
        // autofocus는 이미 제거됨 (허용 속성 아님)

        // ── 21. SVG foreignObject ──
        // foreignObject 내 HTML 허용
        $html = preg_replace('/<foreignObject\b[^>]*>.*?<\/foreignObject>/is', '', $html) ?? $html;

        // ── 22. MathML annotation-xml ──
        $html = preg_replace('/<annotation-xml\b[^>]*>.*?<\/annotation-xml>/is', '', $html) ?? $html;

        // ── 23. base 태그 재확인 ──
        $html = preg_replace('/<base\b[^>]*>/i', '', $html) ?? $html;

        // ── 24. meta refresh ──
        $html = preg_replace('/<meta\b[^>]*http-equiv\s*=\s*["\']?refresh[^>]*>/i', '', $html) ?? $html;

        // ── 25. noscript 태그 ──
        $html = preg_replace('/<noscript\b[^>]*>.*?<\/noscript>/is', '', $html) ?? $html;

        // ── 26. template 태그 ──
        $html = preg_replace('/<template\b[^>]*>.*?<\/template>/is', '', $html) ?? $html;

        // ── 27. CSS @import, @charset 등 ──
        // style 태그는 이미 제거됨

        // ── 29. HTML5 양식 속성 ──
        // formaction, formmethod, formtarget, formenctype
        $html = preg_replace('/form(action|method|target|enctype)\s*=\s*"[^"]*"/i', '', $html) ?? $html;
        $html = preg_replace('/form(action|method|target|enctype)\s*=\s*\'[^\']*\'/i', '', $html) ?? $html;

        // ── 30. ping 속성 (링크 추적) ──
        $html = preg_replace('/\bping\s*=\s*"[^"]*"/i', '', $html) ?? $html;
        $html = preg_replace('/\bping\s*=\s*\'[^\']*\'/i', '', $html) ?? $html;

        // ── 31. referrerpolicy 속성 ──
        // 보안상 위험하지 않으므로 유지

        // ── 32. 최종 HTML 엔티티 정리 ──
        // 위험한 제어문자 엔티티만 정리
        // &#0; &#x0; 등 null 바이트 엔티티
        $html = preg_replace('/&#x?0+;?/i', '', $html) ?? $html;

        return $html;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  체이닝 설정 메서드
    // ─────────────────────────────────────────────────────────────────────

    /**
     * 이미지 태그 허용
     */
    public function allowImages(bool $allow = true): self
    {
        $this->allowImages = $allow;
        return $this;
    }

    /**
     * 링크 태그 허용
     */
    public function allowLinks(bool $allow = true): self
    {
        $this->allowLinks = $allow;
        return $this;
    }

    /**
     * 테이블 태그 허용
     */
    public function allowTables(bool $allow = true): self
    {
        $this->allowTables = $allow;
        return $this;
    }

    /**
     * 비디오/오디오 태그 허용
     */
    public function allowMedia(bool $allow = true): self
    {
        $this->allowMedia = $allow;
        return $this;
    }

    /**
     * 커스텀 허용 태그 추가
     *
     * @param array<string> $tags
     */
    public function allowTags(array $tags): self
    {
        $this->allowedTags = array_merge($this->allowedTags, $tags);
        return $this;
    }

    /**
     * 커스텀 허용 속성 추가
     *
     * @param array<string> $attrs
     */
    public function allowAttrs(array $attrs): self
    {
        $this->allowedAttrs = array_merge($this->allowedAttrs, $attrs);
        return $this;
    }

    /**
     * 허용 태그 설정 (기존 목록 교체)
     *
     * @param array<string> $tags
     */
    public function setAllowedTags(array $tags): self
    {
        $this->allowedTags = $tags;
        return $this;
    }

    /**
     * 허용 속성 설정 (기존 목록 교체)
     *
     * @param array<string> $attrs
     */
    public function setAllowedAttrs(array $attrs): self
    {
        $this->allowedAttrs = $attrs;
        return $this;
    }

    /**
     * 허용 프로토콜 설정
     *
     * @param array<string> $protocols
     */
    public function setAllowedProtocols(array $protocols): self
    {
        $this->allowedProtocols = $protocols;
        return $this;
    }

    /**
     * 허용 프로토콜 추가 (기존 목록에 추가)
     *
     * @param array<string> $protocols
     */
    public function allowProtocols(array $protocols): self
    {
        $this->allowedProtocols = array_merge($this->allowedProtocols, $protocols);
        return $this;
    }

    /**
     * 커스텀 필터 설정
     *
     * @param callable $filter 정제 후 추가 처리할 콜백
     */
    public function setCustomFilter(callable $filter): self
    {
        $this->customFilter = $filter;
        return $this;
    }

    /**
     * 모든 기능 허용 (위험 - 신뢰할 수 있는 관리자 콘텐츠에만 사용)
     */
    public function allowAll(): self
    {
        return $this->allowImages()->allowLinks()->allowTables()->allowMedia();
    }

    /**
     * 최소한의 태그만 허용 (텍스트 포맷팅만)
     */
    public function textOnly(): self
    {
        $this->allowedTags = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's'];
        $this->allowedAttrs = [];
        $this->allowImages = false;
        $this->allowLinks = false;
        $this->allowTables = false;
        $this->allowMedia = false;
        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  유틸리티 메서드
    // ─────────────────────────────────────────────────────────────────────

    /**
     * HTML에서 텍스트만 추출 (모든 태그 제거)
     */
    public function stripTags(string $html): string
    {
        return strip_tags($html);
    }

    /**
     * HTML 이스케이프 (특수문자 변환)
     */
    public function escape(string $html): string
    {
        return htmlspecialchars($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * 줄바꿈을 <br>로 변환
     */
    public function nl2br(string $text): string
    {
        return nl2br($this->escape($text));
    }

    /**
     * 안전한 HTML 출력 (이스케이프 + 줄바꿈)
     */
    public function safeOutput(string $text): string
    {
        return $this->nl2br($text);
    }
}
