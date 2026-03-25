<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Perm — 역할/권한 관리 (RBAC)
 *
 * @config array{
 *     roles?: array<string>,  // 역할 목록 (기본 ['admin', 'editor', 'user'])
 * } perm  → config('perm.roles')
 */
final class Perm
{
    private static ?self $instance = null;

    /** @var array<string, array<string>> 역할별 권한 */
    private array $permissions = [];

    private function __construct(
        private readonly array $roles,
    ) {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self(
            roles: \config('perm.roles') ?? ['admin', 'editor', 'user'],
        );
    }

    /** 역할에 권한 부여 */
    public function role(string $role, array $permissions): self
    {
        $this->permissions[$role] = array_merge($this->permissions[$role] ?? [], $permissions);
        return $this;
    }

    /** 현재 사용자 권한 확인 */
    public function can(string $permission): bool
    {
        $user = \auth()->user();
        if ($user === null) {
            return false;
        }

        $userRole = $user['role'] ?? 'user';

        // admin은 모든 권한
        if ($userRole === 'admin') {
            return true;
        }

        return in_array($permission, $this->permissions[$userRole] ?? [], true);
    }

    /** 권한 없음 확인 */
    public function cannot(string $permission): bool
    {
        return !$this->can($permission);
    }

    /** 등록된 역할 목록 */
    public function roles(): array
    {
        return $this->roles;
    }

    /** 사용자에게 역할 할당 (Auth 연동 — 세션 재생성 포함) */
    public function assign(string $role): void
    {
        $user = \auth()->user();
        if ($user !== null) {
            $user['role'] = $role;
            \auth()->login($user);
        }
    }

    /** 미들웨어: 특정 역할만 접근 허용 */
    public function middleware(string ...$allowedRoles): callable
    {
        return function () use ($allowedRoles): ?bool {
            $user = \auth()->user();
            if ($user === null) {
                \json()->error('Unauthorized', 401);
            }

            $userRole = $user['role'] ?? 'user';
            if (!in_array($userRole, $allowedRoles, true)) {
                \json()->error('Forbidden', 403);
            }

            return null;
        };
    }
}
