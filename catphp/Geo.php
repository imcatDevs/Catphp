<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Geo — 다국어/지역화
 *
 * @config array{
 *     default?: string,            // 기본 로케일 (기본 'ko')
 *     supported?: array<string>,   // 지원 로케일 목록
 *     path?: string,               // 번역 파일 디렉토리
 * } geo  → config('geo.default')
 */
final class Geo
{
    private static ?self $instance = null;

    private string $currentLocale;
    /** @var array<string, array<string, string>> 로케일별 번역 캐시 */
    private array $translations = [];

    private function __construct(
        private readonly string $defaultLocale,
        private readonly array $supported,
        private readonly string $langPath,
    ) {
        $this->currentLocale = $defaultLocale;
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self(
            defaultLocale: \config('geo.default') ?? 'ko',
            supported: \config('geo.supported') ?? ['ko', 'en'],
            langPath: \config('geo.path') ?? __DIR__ . '/../lang',
        );
    }

    /** 현재 로케일 반환 */
    public function getLocale(): string
    {
        return $this->currentLocale;
    }

    /** 로케일 설정 */
    public function locale(string $locale): self
    {
        if (in_array($locale, $this->supported, true)) {
            $this->currentLocale = $locale;
        }
        return $this;
    }

    /** 번역 */
    public function t(string $key, array $replace = []): string
    {
        $this->loadTranslations($this->currentLocale);
        $text = $this->translations[$this->currentLocale][$key] ?? $key;

        foreach ($replace as $k => $v) {
            $text = str_replace(":{$k}", (string) $v, $text);
        }

        return $text;
    }

    /** 자동 언어 감지 (IP + Accept-Language + 쿠키) */
    public function detect(): string
    {
        // 1. 쿠키 확인
        if (class_exists('Cat\\Cookie', false)) {
            $cookieLocale = \cookie()->get('_locale');
            if ($cookieLocale !== null && in_array($cookieLocale, $this->supported, true)) {
                $this->currentLocale = $cookieLocale;
                return $cookieLocale;
            }
        }

        // 2. Accept-Language 헤더
        $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if ($acceptLang !== '') {
            $langs = explode(',', $acceptLang);
            foreach ($langs as $lang) {
                $code = substr(trim(explode(';', $lang)[0]), 0, 2);
                if (in_array($code, $this->supported, true)) {
                    $this->currentLocale = $code;
                    return $code;
                }
            }
        }

        // 3. IP 기반 (Ip.php 연동)
        if (class_exists('Cat\\Ip', false)) {
            $country = \ip()->country();
            if ($country !== null) {
                $localeMap = ['KR' => 'ko', 'US' => 'en', 'GB' => 'en', 'JP' => 'ja', 'CN' => 'zh'];
                $detected = $localeMap[$country] ?? null;
                if ($detected !== null && in_array($detected, $this->supported, true)) {
                    $this->currentLocale = $detected;
                    return $detected;
                }
            }
        }

        return $this->defaultLocale;
    }

    /** 다국어 URL 생성 */
    public function url(string $path, ?string $locale = null): string
    {
        $locale ??= $this->currentLocale;
        return '/' . $locale . '/' . ltrim($path, '/');
    }

    /** 언어 전환 URL */
    public function switch(string $locale): string
    {
        $uri = str_replace(["\r", "\n", "\0"], '', $_SERVER['REQUEST_URI'] ?? '/');
        // 기존 로케일 prefix 제거
        foreach ($this->supported as $sup) {
            if (str_starts_with($uri, "/{$sup}/") || $uri === "/{$sup}") {
                $uri = substr($uri, strlen("/{$sup}"));
                break;
            }
        }
        return '/' . $locale . $uri;
    }

    /** 통화 포맷 */
    public function currency(float|int $amount, ?string $locale = null): string
    {
        $locale ??= $this->currentLocale;

        if (extension_loaded('intl')) {
            $fmt = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            $currencyCode = match ($locale) {
                'ko'    => 'KRW',
                'ja'    => 'JPY',
                'zh'    => 'CNY',
                default => 'USD',
            };
            return $fmt->formatCurrency((float) $amount, $currencyCode);
        }

        // intl 미설치 폴백
        return match ($locale) {
            'ko'    => '₩' . number_format($amount),
            'ja'    => '¥' . number_format($amount),
            default => '$' . number_format((float) $amount, 2),
        };
    }

    /** 날짜 포맷 */
    public function date(int $timestamp, ?string $locale = null): string
    {
        $locale ??= $this->currentLocale;

        if (extension_loaded('intl')) {
            $fmt = new \IntlDateFormatter($locale, \IntlDateFormatter::LONG, \IntlDateFormatter::NONE);
            return $fmt->format($timestamp) ?: date('Y-m-d', $timestamp);
        }

        // intl 미설치 폴백
        return match ($locale) {
            'ko'    => date('Y년 n월 j일', $timestamp),
            'ja'    => date('Y年n月j日', $timestamp),
            default => date('F j, Y', $timestamp),
        };
    }

    /** hreflang 태그 생성 */
    public function hreflang(): string
    {
        $uri = str_replace(["\r", "\n", "\0"], '', $_SERVER['REQUEST_URI'] ?? '/');
        $host = preg_replace('/[^\w.\-:]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        $scheme = ($_SERVER['REQUEST_SCHEME'] ?? 'https');
        $scheme = in_array($scheme, ['http', 'https'], true) ? $scheme : 'https';
        $baseUrl = $scheme . '://' . $host;
        $html = '';

        foreach ($this->supported as $locale) {
            $url = $baseUrl . $this->switch($locale);
            $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            $html .= "<link rel=\"alternate\" hreflang=\"{$locale}\" href=\"{$safeUrl}\">\n";
        }

        // x-default: 기본 로케일을 대표 URL로 지정 (Google SEO 가이드라인)
        $defaultUrl = $baseUrl . $this->switch($this->defaultLocale);
        $safeDefault = htmlspecialchars($defaultUrl, ENT_QUOTES, 'UTF-8');
        $html .= "<link rel=\"alternate\" hreflang=\"x-default\" href=\"{$safeDefault}\">\n";

        return $html;
    }

    /** 미들웨어: 자동 언어 감지 + 리디렉트 */
    public function middleware(): callable
    {
        return function (): ?bool {
            $this->detect();

            // 쿠키에 로케일 저장
            if (class_exists('Cat\\Cookie', false)) {
                \cookie()->set('_locale', $this->currentLocale, 86400 * 365);
            }

            return null;
        };
    }

    /** 번역 파일 로드 (지연) */
    private function loadTranslations(string $locale): void
    {
        if (isset($this->translations[$locale])) {
            return;
        }

        $file = $this->langPath . '/' . $locale . '.php';
        if (is_file($file)) {
            $data = require $file;
            $this->translations[$locale] = is_array($data) ? $this->flatten($data) : [];
        } else {
            $this->translations[$locale] = [];
        }
    }

    /** 중첩 배열 → dot notation 평탄화 */
    private function flatten(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix !== '' ? "{$prefix}.{$key}" : $key;
            if (is_array($value)) {
                $result = array_merge($result, $this->flatten($value, $fullKey));
            } else {
                $result[$fullKey] = (string) $value;
            }
        }
        return $result;
    }
}
