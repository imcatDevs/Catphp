CREATE TABLE IF NOT EXISTS posts (
    id BIGSERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO posts (title, body) VALUES
    ('Hello Swoole', '첫 번째 포스트입니다.'),
    ('성능 테스트', 'wrk로 벤치마크 예정'),
    ('CatPHP', '가벼운 PHP 프레임워크');
