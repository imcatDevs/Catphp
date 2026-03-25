<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Slug — URL 슬러그
 *
 * 다국어 지원 (한글/중국어/일본어 유니코드 보존).
 */
final class Slug
{
    private static ?self $instance = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /** 슬러그 생성 */
    public function make(string $text, string $separator = '-'): string
    {
        // 소문자 변환
        $text = mb_strtolower($text, 'UTF-8');

        // 유니코드 문자(한글, 영문, 숫자)만 유지, 나머지는 구분자로
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text) ?? $text;

        // 공백 → 구분자
        $text = preg_replace('/[\s]+/u', $separator, $text) ?? $text;

        // 연속 구분자 제거
        $text = preg_replace('/' . preg_quote($separator, '/') . '+/', $separator, $text) ?? $text;

        return trim($text, $separator);
    }

    /** 고유 슬러그 생성 (중복 시 suffix 추가) */
    public function unique(string $text, callable $existsCheck, string $separator = '-', int $maxAttempts = 100): string
    {
        $base = $this->make($text, $separator);
        $slug = $base;
        $counter = 1;

        while ($existsCheck($slug)) {
            $counter++;
            if ($counter > $maxAttempts) {
                throw new \RuntimeException("고유 슬러그 생성 실패: {$maxAttempts}회 초과 ({$base})");
            }
            $slug = $base . $separator . $counter;
        }

        return $slug;
    }
}
