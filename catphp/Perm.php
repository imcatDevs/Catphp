<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Perm — 역할/권한 관리 (RBAC)
 *
 * @config array{
 *     roles?: array<string>,     // 역할 목록 (기본 ['admin', 'editor', 'user'])
 *     super_role?: string,       // 슈퍼관리자 역할 (기본 'admin')
 * } perm  → config('perm.roles')
 */
final class Perm
{
    private static ?self $instance = null;

    /** @var array<string, array<string>> 역할별 권한 */
    private array $permissions = [];

    private function __construct(
        private readonly array $roles,
        private readonly string $superRole,
    ) {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self(
            roles: \config('perm.roles') ?? ['admin', 'editor', 'user'],
            superRole: \config('perm.super_role') ?? 'admin',
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

        // 슈퍼관리자는 모든 권한 (config로 설정 가능)
        if ($userRole === $this->superRole) {
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
        if (!in_array($role, $this->roles, true)) {
            throw new \InvalidArgumentException("등록되지 않은 역할: {$role}");
        }

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
                \json()->unauthorized();
            }

            $userRole = $user['role'] ?? 'user';
            if (!in_array($userRole, $allowedRoles, true)) {
                \json()->forbidden();
            }

            return null;
        };
    }
}
