<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Text — 본문 처리
 *
 * 발췌/요약, 읽기 시간, 단어 수, HTML 태그 제거, UTF-8 안전 자르기.
 */
final class Text
{
    private static ?self $instance = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /** 발췌 생성 (한글 인식 자르기) */
    public function excerpt(string $text, int $length = 200, string $suffix = '...'): string
    {
        $plain = $this->stripTags($text);
        $plain = preg_replace('/\s+/', ' ', $plain) ?? $plain;
        $plain = trim($plain);

        if (mb_strlen($plain) <= $length) {
            return $plain;
        }

        $cut = mb_substr($plain, 0, $length);

        // 단어 경계에서 자르기 (공백 기준)
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > $length * 0.7) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return $cut . $suffix;
    }

    /** 읽기 시간 계산 (한글/영문 별도 WPM) */
    public function readingTime(string $text, int $koWpm = 500, int $enWpm = 200): string
    {
        $plain = $this->stripTags($text);

        // 한글 문자 수
        preg_match_all('/[\x{AC00}-\x{D7AF}]/u', $plain, $koMatches);
        $koChars = count($koMatches[0]);

        // 영문 단어 수
        $enText = preg_replace('/[\x{AC00}-\x{D7AF}]/u', '', $plain) ?? '';
        $enWords = str_word_count($enText);

        $minutes = ($koChars / $koWpm) + ($enWords / $enWpm);
        $minutes = max(1, (int) ceil($minutes));

        return "{$minutes}분";
    }

    /** 단어 수 (한글 + 영문) */
    public function wordCount(string $text): int
    {
        $plain = $this->stripTags($text);

        // 한글 문자 수 (한글은 한 글자가 대략 한 단어)
        preg_match_all('/[\x{AC00}-\x{D7AF}]/u', $plain, $koMatches);
        $koCount = count($koMatches[0]);

        // 영문 단어 수
        $enText = preg_replace('/[\x{AC00}-\x{D7AF}]/u', ' ', $plain) ?? '';
        $enCount = str_word_count($enText);

        return $koCount + $enCount;
    }

    /** 문자 수 (공백 제외) */
    public function charCount(string $text, bool $includeSpaces = false): int
    {
        $plain = $this->stripTags($text);
        if (!$includeSpaces) {
            $plain = preg_replace('/\s/', '', $plain) ?? $plain;
        }
        return mb_strlen($plain);
    }

    /** HTML 태그 제거 */
    public function stripTags(string $text): string
    {
        return strip_tags($text);
    }

    /** UTF-8 안전 텍스트 자르기 */
    public function truncate(string $text, int $length, string $suffix = '...'): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length) . $suffix;
    }
}
