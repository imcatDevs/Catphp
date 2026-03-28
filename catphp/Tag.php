<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Tag — 태그/카테고리 관리
 *
 * 다형성 태깅 (posts, pages 등 어떤 테이블에도 적용).
 * DB.php + Slug.php 연동.
 */
final class Tag
{
    private static ?self $instance = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * taggable_type 값 검증 (영숫자+밑줄만 허용)
     *
     * SQL 바인딩으로 사용되어 injection은 아니지만,
     * DB에 저장되는 값이므로 방어적으로 형식을 제한한다.
     */
    private static function validateType(string $table): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new \InvalidArgumentException("Tag: 유효하지 않은 테이블명 '{$table}'");
        }
    }

    /** 태그 붙이기 */
    public function attach(string $table, int|string $id, array $tags): void
    {
        self::validateType($table);
        foreach ($tags as $tagName) {
            $tagName = \guard()->clean($tagName);
            $tagSlug = \slug()->make($tagName);
            $existing = \db()->table('tags')->where('slug', $tagSlug)->first();

            if ($existing === null) {
                $tagId = (int) \db()->table('tags')->insert([
                    'name' => $tagName,
                    'slug' => $tagSlug,
                ]);
            } else {
                $tagId = (int) $existing['id'];
            }

            $exists = \db()->table('taggables')
                ->where('tag_id', $tagId)
                ->where('taggable_type', $table)
                ->where('taggable_id', $id)
                ->first();

            if ($exists === null) {
                \db()->table('taggables')->insert([
                    'tag_id'        => $tagId,
                    'taggable_type' => $table,
                    'taggable_id'   => $id,
                ]);
            }
        }
    }

    /** 태그 제거 */
    public function detach(string $table, int|string $id, array $tags): void
    {
        self::validateType($table);
        foreach ($tags as $tagName) {
            $tagSlug = \slug()->make($tagName);
            $tag = \db()->table('tags')->where('slug', $tagSlug)->first();
            if ($tag !== null) {
                \db()->raw(
                    'DELETE FROM taggables WHERE tag_id = ? AND taggable_type = ? AND taggable_id = ?',
                    [(int) $tag['id'], $table, $id]
                );
            }
        }
    }

    /** 태그 동기화 (기존 태그 제거 후 재설정) */
    public function sync(string $table, int|string $id, array $tags): void
    {
        self::validateType($table);
        \db()->raw(
            'DELETE FROM taggables WHERE taggable_type = ? AND taggable_id = ?',
            [$table, $id]
        );
        $this->attach($table, $id, $tags);
    }

    /** 특정 태그가 붙은 항목 ID 목록 */
    public function tagged(string $table, string $tagName): array
    {
        self::validateType($table);
        $tagSlug = \slug()->make($tagName);
        $tag = \db()->table('tags')->where('slug', $tagSlug)->first();
        if ($tag === null) {
            return [];
        }

        $rows = \db()->raw(
            'SELECT taggable_id FROM taggables WHERE tag_id = ? AND taggable_type = ?',
            [(int) $tag['id'], $table]
        )->fetchAll();

        return array_column($rows, 'taggable_id');
    }

    /** 항목의 태그 목록 (Guard 살균) */
    public function tags(string $table, int|string $id): array
    {
        self::validateType($table);
        $rows = \db()->raw(
            'SELECT t.name, t.slug FROM tags t INNER JOIN taggables tg ON t.id = tg.tag_id WHERE tg.taggable_type = ? AND tg.taggable_id = ?',
            [$table, $id]
        )->fetchAll();

        return array_map(fn(array $row) => \guard()->cleanArray($row), $rows);
    }

    /** 태그 클라우드 (가중치 기반, Guard 살균) */
    public function cloud(string $table): array
    {
        self::validateType($table);
        $rows = \db()->raw(
            'SELECT t.name, t.slug, COUNT(*) as count FROM tags t INNER JOIN taggables tg ON t.id = tg.tag_id WHERE tg.taggable_type = ? GROUP BY t.id, t.name, t.slug ORDER BY count DESC',
            [$table]
        )->fetchAll();

        $cloud = [];
        foreach ($rows as $row) {
            $cloud[\guard()->clean($row['name'])] = (int) $row['count'];
        }
        return $cloud;
    }

    /** 인기 태그 (상위 N개, Guard 살균) */
    public function popular(int $limit = 10): array
    {
        $rows = \db()->raw(
            'SELECT t.name, t.slug, COUNT(*) as count FROM tags t INNER JOIN taggables tg ON t.id = tg.tag_id GROUP BY t.id, t.name, t.slug ORDER BY count DESC LIMIT ?',
            [$limit]
        )->fetchAll();

        return array_map(fn(array $row) => \guard()->cleanArray($row), $rows);
    }
}
