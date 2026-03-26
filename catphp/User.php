<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\User — 유저 정보 도구 (자동 XSS 필터링)
 *
 * 모든 유저 데이터 조회 시 문자열 필드를 Guard::xss()로 자동 살균.
 * Auth 세션 유저와 DB 유저를 통합 관리.
 *
 * @config array{
 *     table?: string,       // 유저 테이블명 (기본 'users')
 *     primaryKey?: string,  // PK 컬럼명 (기본 'id')
 *     hidden?: string[],    // 응답에서 제외할 필드 (기본 ['password'])
 * } user  → config('user.table')
 *
 * 사용법:
 *   user()->current()                    → 로그인 유저 (XSS 살균됨)
 *   user()->find(1)                      → ID로 조회
 *   user()->findBy('email', $email)      → 필드로 조회
 *   user()->get('name')                  → 현재 유저 필드 (XSS 살균됨)
 *   user()->create([...])                → 유저 생성
 *   user()->update(1, [...])             → 유저 수정
 *   user()->delete(1)                    → 유저 삭제
 *   user()->raw(1)                       → XSS 필터링 없이 원본 조회
 */
final class User
{
    private static ?self $instance = null;

    private function __construct(
        private readonly string $table,
        private readonly string $primaryKey,
        /** @var array<string> 응답에서 제외할 필드 */
        private readonly array $hidden,
    ) {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self(
            table: \config('user.table') ?? 'users',
            primaryKey: \config('user.primary_key') ?? 'id',
            hidden: \config('user.hidden') ?? ['password'],
        );
    }

    // ── 조회 (자동 XSS 필터링) ──

    /** 현재 로그인 유저 (세션 기반, XSS 살균) */
    public function current(): ?array
    {
        $user = \auth()->user();
        return $user !== null ? $this->sanitize($user) : null;
    }

    /** ID로 유저 조회 (XSS 살균) */
    public function find(int|string $id): ?array
    {
        $user = \db()->table($this->table)
            ->where($this->primaryKey, $id)
            ->first();

        return $user !== null ? $this->sanitize($user) : null;
    }

    /** 특정 필드로 유저 조회 (XSS 살균) */
    public function findBy(string $column, mixed $value): ?array
    {
        $user = \db()->table($this->table)
            ->where($column, $value)
            ->first();

        return $user !== null ? $this->sanitize($user) : null;
    }

    /** 현재 로그인 유저의 특정 필드 (XSS 살균) */
    public function get(string $field, mixed $default = null): mixed
    {
        $user = $this->current();
        if ($user === null) {
            return $default;
        }

        return $user[$field] ?? $default;
    }

    /** 여러 유저 조회 (XSS 살균) */
    public function list(int $limit = 20, int $offset = 0): array
    {
        $users = \db()->table($this->table)
            ->limit($limit)
            ->offset($offset)
            ->all();

        return array_map(fn(array $user) => $this->sanitize($user), $users);
    }

    /** 유저 검색 (XSS 살균, LIKE wildcard 이스케이프) */
    public function search(string $column, string $keyword, int $limit = 20): array
    {
        // LIKE 특수문자 이스케이프 (%, _, \)
        $escaped = addcslashes($keyword, '%_\\');

        $users = \db()->table($this->table)
            ->where($column, "%{$escaped}%", 'LIKE')
            ->limit($limit)
            ->all();

        return array_map(fn(array $user) => $this->sanitize($user), $users);
    }

    /** 유저 수 조회 */
    public function count(): int
    {
        return \db()->table($this->table)->count();
    }

    /** 유저 존재 확인 */
    public function exists(string $column, mixed $value): bool
    {
        return \db()->table($this->table)
            ->where($column, $value)
            ->first() !== null;
    }

    // ── 원본 조회 (XSS 필터링 없음 — 내부 로직용) ──

    /** 원본 유저 데이터 (비밀번호 검증 등 내부 로직용) */
    public function raw(int|string $id): ?array
    {
        return \db()->table($this->table)
            ->where($this->primaryKey, $id)
            ->first();
    }

    /** 원본 필드 조회 (내부 로직용) */
    public function rawBy(string $column, mixed $value): ?array
    {
        return \db()->table($this->table)
            ->where($column, $value)
            ->first();
    }

    // ── 쓰기 ──

    /** 유저 생성 (비밀번호 자동 해싱) */
    public function create(array $data): string|false
    {
        $data = $this->sanitizeInput($data);

        // 비밀번호 자동 해싱
        if (isset($data['password']) && !self::isHashed($data['password'])) {
            $data['password'] = \auth()->hashPassword($data['password']);
        }

        return \db()->table($this->table)->insert($data);
    }

    /** 유저 수정 */
    public function update(int|string $id, array $data): int
    {
        $data = $this->sanitizeInput($data);

        // 비밀번호가 포함되면 자동 해싱
        if (isset($data['password']) && !self::isHashed($data['password'])) {
            $data['password'] = \auth()->hashPassword($data['password']);
        }

        return \db()->table($this->table)
            ->where($this->primaryKey, $id)
            ->update($data);
    }

    /** 유저 삭제 */
    public function delete(int|string $id): int
    {
        return \db()->table($this->table)
            ->where($this->primaryKey, $id)
            ->delete();
    }

    // ── 인증 통합 ──

    /**
     * 이메일+비밀번호로 로그인 시도 (타이밍 공격 + 브루트포스 방어)
     *
     * 브루트포스 방어:
     *   - IP 기준 5분간 10회 실패 시 차단 (Rate 도구 연동)
     *   - IP 기준 30분간 50회 실패 시 Firewall 자동 밴
     */
    public function attempt(string $email, #[\SensitiveParameter] string $password, string $emailColumn = 'email'): bool
    {
        // 브루트포스 방어: IP 기준 레이트 리미트 (5분/10회)
        if (!\rate()->limit('login', 300, 10)) {
            // 극단적 남용 감지: 30분/50회 초과 시 Firewall 자동 밴
            // limit()으로 기록 + 검사 (check()는 조회만 하여 카운터 미증가)
            if (class_exists('Cat\\Firewall', false) && !\rate()->limit('login_ban', 1800, 50)) {
                \firewall()->ban(\ip()->address(), '브루트포스 로그인 시도 자동 차단');
            }
            return false;
        }

        $user = $this->rawBy($emailColumn, $email);

        // 타이밍 공격 방어: 유저 미존재 시에도 동일한 시간을 소비하여 존재 여부 유추 차단
        $hash = $user['password'] ?? '$argon2id$v=19$m=65536,t=4,p=1$dW5rbm93bg$dW5rbm93bg';
        $verified = \auth()->verifyPassword($password, $hash);

        if ($user === null || !$verified) {
            return false;
        }

        // 로그인 성공 시 레이트 리미트 초기화
        \rate()->reset('login');
        \rate()->reset('login_ban');

        // hidden 필드 제거 후 세션 저장
        $safe = $this->removeHidden($user);
        \auth()->login($safe);

        return true;
    }

    /** 현재 유저 세션 새로고침 (DB에서 최신 정보 로드) */
    public function refresh(): ?array
    {
        $current = \auth()->user();
        if ($current === null) {
            return null;
        }

        $id = $current[$this->primaryKey] ?? null;
        if ($id === null) {
            return null;
        }

        $user = \db()->table($this->table)
            ->where($this->primaryKey, $id)
            ->first();

        if ($user === null) {
            \auth()->logout();
            return null;
        }

        $safe = $this->removeHidden($user);
        \auth()->login($safe);

        return $this->sanitize($safe);
    }

    // ── 내부 헬퍼 (Guard 연동) ──

    /**
     * 유저 데이터 종합 살균 + hidden 필드 제거
     *
     * Guard::cleanArray()로 모든 문자열 필드에
     * 제어문자 제거 + CRLF 제거 + XSS 살균 + SQL 탐지를 일괄 적용.
     */
    private function sanitize(array $user): array
    {
        $user = $this->removeHidden($user);
        return \guard()->cleanArray($user);
    }

    /** hidden 필드 제거 */
    private function removeHidden(array $user): array
    {
        foreach ($this->hidden as $field) {
            unset($user[$field]);
        }
        return $user;
    }

    /**
     * 입력 데이터 살균 (쓰기 전) — Guard::cleanArray() + trim
     *
     * password 필드는 살균에서 제외 (해싱 전 원본 유지 필요).
     */
    /** 비밀번호 해시 여부 판단 (password_get_info 대신 명시적 프리픽스 검사) */
    private static function isHashed(string $value): bool
    {
        return str_starts_with($value, '$2y$')
            || str_starts_with($value, '$2a$')
            || str_starts_with($value, '$2b$')
            || str_starts_with($value, '$argon2id$')
            || str_starts_with($value, '$argon2i$');
    }

    /** @var list<string> 살균 제외 비밀번호 필드명 */
    private const PASSWORD_FIELDS = ['password', 'passwd', 'password_hash', 'pass', 'secret'];

    private function sanitizeInput(array $data): array
    {
        // Guard 종합 살균 (비밀번호 관련 필드 제외)
        $data = \guard()->cleanArray($data, self::PASSWORD_FIELDS);

        // 문자열 필드 trim
        foreach ($data as $key => $value) {
            if (is_string($value) && !in_array($key, self::PASSWORD_FIELDS, true)) {
                $data[$key] = trim($value);
            }
        }
        return $data;
    }
}
