<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Valid — 입력 검증
 *
 * 체이닝 규칙 정의 + 내장 검증 규칙.
 */
final class Valid
{
    private static ?self $instance = null;

    /** @var array<string, string> 필드별 규칙 문자열 */
    private array $fieldRules = [];

    /** @var array<string, array<string>> 검증 에러 */
    private array $fieldErrors = [];

    /** @var array<string, callable> 커스텀 규칙 */
    private static array $customRules = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /** 검증 규칙 설정 */
    public function rules(array $rules): self
    {
        $c = clone $this;
        $c->fieldRules = $rules;
        $c->fieldErrors = [];
        return $c;
    }

    /** 검증 실행 (이뮤터블 — 원본 인스턴스 변이 없음) */
    public function check(array $data): self
    {
        $c = clone $this;
        $c->fieldErrors = [];

        foreach ($c->fieldRules as $field => $ruleStr) {
            $rules = explode('|', $ruleStr);
            $value = $data[$field] ?? null;

            // nullable: null/빈 값이면 나머지 규칙 건너뛰기
            $isNullable = in_array('nullable', $rules, true);
            $isRequired = in_array('required', $rules, true);
            if ($isNullable && !$isRequired && ($value === null || $value === '')) {
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }

                [$rule, $params] = self::parseRule($rule);

                $error = $c->validateRule($field, $value, $rule, $params, $data);
                if ($error !== null) {
                    $c->fieldErrors[$field][] = $error;
                }
            }
        }

        return $c;
    }

    /**
     * 규칙 문자열 파싱 (regex는 ':' 포함 가능하므로 특수 처리)
     *
     * @return array{0: string, 1: list<string>}
     */
    private static function parseRule(string $raw): array
    {
        if (!str_contains($raw, ':')) {
            return [$raw, []];
        }

        [$name, $paramStr] = explode(':', $raw, 2);

        // regex 규칙: 나머지 전체가 패턴 (내부 ':'·',' 보존)
        if ($name === 'regex') {
            return ['regex', [$paramStr]];
        }

        return [$name, explode(',', $paramStr)];
    }

    /** 검증 실패 여부 */
    public function fails(): bool
    {
        return !empty($this->fieldErrors);
    }

    /** 에러 목록 */
    public function errors(): array
    {
        return $this->fieldErrors;
    }

    /** 커스텀 규칙 등록 */
    public static function extend(string $name, callable $callback): void
    {
        self::$customRules[$name] = $callback;
    }

    /** 개별 규칙 검증 (에러 메시지 반환 또는 null) */
    private function validateRule(string $field, mixed $value, string $rule, array $params, array $data): ?string
    {
        return match ($rule) {
            'required' => ($value === null || $value === '') ? "{$field}은(는) 필수입니다" : null,
            'email'    => (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) ? null : "{$field}은(는) 유효한 이메일이어야 합니다",
            'min'      => $this->checkMin($field, $value, (int) ($params[0] ?? 0)),
            'max'      => $this->checkMax($field, $value, (int) ($params[0] ?? 0)),
            'between'  => $this->checkBetween($field, $value, (int) ($params[0] ?? 0), (int) ($params[1] ?? 0)),
            'in'       => in_array((string) $value, $params, true) ? null : "{$field}은(는) " . implode(', ', $params) . " 중 하나여야 합니다",
            'numeric'  => is_numeric($value) ? null : "{$field}은(는) 숫자여야 합니다",
            'integer'  => (filter_var($value, FILTER_VALIDATE_INT) !== false) ? null : "{$field}은(는) 정수여야 합니다",
            'url'      => (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) ? null : "{$field}은(는) 유효한 URL이어야 합니다",
            'regex'    => (is_string($value) && preg_match($params[0] ?? '//', $value)) ? null : "{$field}은(는) 형식이 올바르지 않습니다",
            'confirmed' => ($value === ($data["{$field}_confirmation"] ?? null)) ? null : "{$field} 확인이 일치하지 않습니다",
            'unique'   => $this->checkUnique($field, $value, $params),
            'string'   => is_string($value) ? null : "{$field}은(는) 문자열이어야 합니다",
            'array'    => is_array($value) ? null : "{$field}은(는) 배열이어야 합니다",
            'alpha'    => (is_string($value) && preg_match('/^[\pL]+$/u', $value)) ? null : "{$field}은(는) 알파벳만 허용됩니다",
            'alpha_num' => (is_string($value) && preg_match('/^[\pL\pN]+$/u', $value)) ? null : "{$field}은(는) 알파벳과 숫자만 허용됩니다",
            'digits'   => (is_string($value) && ctype_digit($value) && strlen($value) === (int) ($params[0] ?? 0)) ? null : "{$field}은(는) " . ($params[0] ?? '0') . "자리 숫자여야 합니다",
            'date'     => (is_string($value) && strtotime($value) !== false) ? null : "{$field}은(는) 유효한 날짜여야 합니다",
            'date_format' => $this->checkDateFormat($field, $value, $params[0] ?? 'Y-m-d'),
            'before'   => $this->checkDateCompare($field, $value, $params[0] ?? '', '<'),
            'after'    => $this->checkDateCompare($field, $value, $params[0] ?? '', '>'),
            'ip'       => (is_string($value) && filter_var($value, FILTER_VALIDATE_IP)) ? null : "{$field}은(는) 유효한 IP 주소여야 합니다",
            'json'     => (is_string($value) && (json_decode($value) !== null || json_last_error() === JSON_ERROR_NONE)) ? null : "{$field}은(는) 유효한 JSON이어야 합니다",
            'same'     => ($value === ($data[$params[0] ?? ''] ?? null)) ? null : "{$field}은(는) " . ($params[0] ?? '') . "와(과) 동일해야 합니다",
            'different' => ($value !== ($data[$params[0] ?? ''] ?? null)) ? null : "{$field}은(는) " . ($params[0] ?? '') . "와(과) 달라야 합니다",
            'size'     => $this->checkSize($field, $value, (int) ($params[0] ?? 0)),
            default    => $this->checkCustom($field, $value, $rule, $params, $data),
        };
    }

    private function checkMin(string $field, mixed $value, int $min): ?string
    {
        if (is_string($value) && mb_strlen($value) < $min) {
            return "{$field}은(는) 최소 {$min}자 이상이어야 합니다";
        }
        if (is_numeric($value) && (float) $value < $min) {
            return "{$field}은(는) {$min} 이상이어야 합니다";
        }
        return null;
    }

    private function checkMax(string $field, mixed $value, int $max): ?string
    {
        if (is_string($value) && mb_strlen($value) > $max) {
            return "{$field}은(는) 최대 {$max}자 이하여야 합니다";
        }
        if (is_numeric($value) && (float) $value > $max) {
            return "{$field}은(는) {$max} 이하여야 합니다";
        }
        return null;
    }

    private function checkBetween(string $field, mixed $value, int $min, int $max): ?string
    {
        if (is_string($value)) {
            $len = mb_strlen($value);
            if ($len < $min || $len > $max) {
                return "{$field}은(는) {$min}~{$max}자 사이여야 합니다";
            }
        }
        if (is_numeric($value)) {
            $num = (float) $value;
            if ($num < $min || $num > $max) {
                return "{$field}은(는) {$min}~{$max} 사이여야 합니다";
            }
        }
        return null;
    }

    /** unique:table,column 검증 (DB 중복 검사) */
    private function checkUnique(string $field, mixed $value, array $params): ?string
    {
        $table = $params[0] ?? '';
        $column = $params[1] ?? $field;

        if ($table === '' || $value === null || $value === '') {
            return null;
        }

        $exists = \db()->table($table)->where($column, $value)->first();
        return $exists !== null ? "{$field}은(는) 이미 사용 중입니다" : null;
    }

    /** 날짜 포맷 검증 */
    private function checkDateFormat(string $field, mixed $value, string $format): ?string
    {
        if (!is_string($value)) {
            return "{$field}은(는) 유효한 날짜여야 합니다";
        }
        $d = \DateTimeImmutable::createFromFormat($format, $value);
        if ($d === false || $d->format($format) !== $value) {
            return "{$field}은(는) {$format} 형식이어야 합니다";
        }
        return null;
    }

    /** 날짜 비교 검증 (before / after) */
    private function checkDateCompare(string $field, mixed $value, string $dateStr, string $op): ?string
    {
        if (!is_string($value) || $dateStr === '') {
            return null;
        }
        $target = strtotime($value);
        $compare = strtotime($dateStr);
        if ($target === false || $compare === false) {
            return "{$field}은(는) 유효한 날짜여야 합니다";
        }
        $label = $op === '<' ? '이전' : '이후';
        if ($op === '<' && $target >= $compare) {
            return "{$field}은(는) {$dateStr} {$label}이어야 합니다";
        }
        if ($op === '>' && $target <= $compare) {
            return "{$field}은(는) {$dateStr} {$label}이어야 합니다";
        }
        return null;
    }

    /** 크기 정확 일치 검증 */
    private function checkSize(string $field, mixed $value, int $size): ?string
    {
        if (is_string($value) && mb_strlen($value) !== $size) {
            return "{$field}은(는) 정확히 {$size}자여야 합니다";
        }
        if (is_numeric($value) && (float) $value !== (float) $size) {
            return "{$field}은(는) {$size}이어야 합니다";
        }
        if (is_array($value) && count($value) !== $size) {
            return "{$field}은(는) {$size}개 항목이어야 합니다";
        }
        return null;
    }

    private function checkCustom(string $field, mixed $value, string $rule, array $params, array $data): ?string
    {
        if (isset(self::$customRules[$rule])) {
            $result = (self::$customRules[$rule])($field, $value, $params, $data);
            return is_string($result) ? $result : null;
        }

        // 미등록 규칙: debug 모드에서 경고 로그 (오타로 검증 누락 방지)
        if ((bool) \config('app.debug', false) && class_exists('Cat\\Log', false)) {
            \logger()->warn("Valid: 미등록 검증 규칙 '{$rule}' (필드: {$field})");
        }

        return null;
    }
}
