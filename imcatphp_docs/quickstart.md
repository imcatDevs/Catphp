# CatPHP 퀵스타트 가이드

설치부터 첫 프로젝트까지 10분 안에 시작하는 가이드.

---

## 목차

1. [요구 사항](#1-요구-사항)
2. [설치](#2-설치)
3. [프로젝트 구조](#3-프로젝트-구조)
4. [설정](#4-설정)
5. [개발 서버 실행](#5-개발-서버-실행)
6. [첫 번째 라우트](#6-첫-번째-라우트)
7. [뷰 템플릿](#7-뷰-템플릿)
8. [데이터베이스 연결](#8-데이터베이스-연결)
9. [CRUD 만들기](#9-crud-만들기)
10. [API 만들기](#10-api-만들기)
11. [인증 추가](#11-인증-추가)
12. [CLI 명령어](#12-cli-명령어)
13. [다음 단계](#13-다음-단계)

---

## 1. 요구 사항

| 항목 | 최소 | 권장 |
| --- | --- | --- |
| PHP | 8.2+ | 8.3+ |
| 확장 | `pdo`, `mbstring`, `json` | + `gd`, `curl`, `sodium`, `redis` |
| DB | SQLite 3 | MySQL 8+ / PostgreSQL 15+ |
| 웹서버 | PHP 내장 서버 | Apache / Nginx |

---

## 2. 설치

```bash
# 프로젝트 디렉토리 생성
mkdir my-project && cd my-project

# CatPHP 복사 (catphp/ 폴더를 프로젝트 루트에 배치)
cp -r /path/to/catphp ./catphp

# 기본 구조 생성
mkdir -p Public/views config storage/{cache,logs,sessions} lang
```

### 최소 파일 구성

```text
my-project/
├── catphp/
│   └── catphp.php      ← 프레임워크 코어 (필수)
├── Public/
│   └── index.php       ← 웹 진입점 (필수)
├── config/
│   └── app.php         ← 설정 파일 (필수)
└── storage/            ← 런타임 데이터 (자동 생성)
```

---

## 3. 프로젝트 구조

```text
my-project/
├── catphp/                 ← 프레임워크 (수정 금지)
│   ├── catphp.php          ← 코어
│   ├── DB.php, Router.php  ← 도구 파일 56개
│   └── ...
├── Public/                 ← 웹 루트 (DocumentRoot)
│   ├── index.php           ← 진입점
│   ├── .htaccess           ← Apache URL 재작성
│   └── views/              ← 뷰 템플릿
│       └── home.php
├── config/
│   └── app.php             ← 설정
├── cli.php                 ← CLI 진입점 (선택)
├── lang/                   ← 다국어 파일 (선택)
│   ├── ko.php
│   └── en.php
├── migrations/             ← DB 마이그레이션 (선택)
├── storage/                ← 캐시, 로그, 세션
│   ├── cache/
│   ├── logs/
│   └── sessions/
└── .env                    ← 환경변수 (선택)
```

---

## 4. 설정

### config/app.php

```php
<?php declare(strict_types=1);

return [
    'app' => [
        'debug'    => true,        // 운영 시 false
        'timezone' => 'Asia/Seoul',
        'key'      => '',          // 암호화 키 (32바이트 hex)
    ],
    'db' => [
        'driver'  => 'sqlite',     // mysql | pgsql | sqlite
        'dbname'  => __DIR__ . '/../storage/app.db',
    ],
    'cache' => [
        'path' => __DIR__ . '/../storage/cache',
        'ttl'  => 3600,
    ],
    'log' => [
        'path'  => __DIR__ . '/../storage/logs',
        'level' => 'debug',
    ],
    'view' => [
        'path' => __DIR__ . '/../Public/views',
    ],
    'session' => [
        'lifetime' => 7200,
    ],
];
```

### MySQL 설정 예시

```php
'db' => [
    'driver'  => 'mysql',
    'host'    => '127.0.0.1',
    'port'    => 3306,
    'dbname'  => 'my_database',
    'user'    => 'root',
    'pass'    => '',
    'charset' => 'utf8mb4',
],
```

### .env 사용 (선택)

```bash
# .env
APP_DEBUG=true
DB_HOST=127.0.0.1
DB_NAME=my_database
DB_USER=root
DB_PASS=secret
```

```php
// config/app.php에서 env() 활용
'db' => [
    'host'   => env('DB_HOST', '127.0.0.1'),
    'dbname' => env('DB_NAME', 'my_database'),
    'user'   => env('DB_USER', 'root'),
    'pass'   => env('DB_PASS', ''),
],
```

---

## 5. 개발 서버 실행

```bash
# 방법 1: CLI 명령어
php cli.php serve

# 방법 2: PHP 내장 서버 직접 실행
php -S 127.0.0.1:8000 -t Public

# 방법 3: 포트 지정
php cli.php serve --port=3000
```

브라우저에서 `http://127.0.0.1:8000` 접속.

### Apache .htaccess

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

### Nginx 설정

```nginx
server {
    listen 80;
    root /path/to/my-project/Public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## 6. 첫 번째 라우트

### Public/index.php

```php
<?php declare(strict_types=1);

require __DIR__ . '/../catphp/catphp.php';
config(require __DIR__ . '/../config/app.php');
errors(config('app.debug'));

// 라우트 정의
router()->get('/', fn() => 'Hello CatPHP!');

router()->get('/about', fn() => render('about'));

router()->get('/user/{id}', function () {
    $id = input('id');
    return "User ID: {$id}";
});

router()->dispatch();
```

### 라우트 메서드

```php
router()->get('/path', $handler);       // GET
router()->post('/path', $handler);      // POST
router()->put('/path', $handler);       // PUT
router()->patch('/path', $handler);     // PATCH
router()->delete('/path', $handler);    // DELETE

// 그룹
router()->group('/admin', function () {
    router()->get('/dashboard', fn() => render('admin/dashboard'));
    router()->get('/users', fn() => render('admin/users'));
});

// 미들웨어
router()->use(guard()->middleware());   // 전역 XSS 살균

// 404
router()->notFound(fn() => render('404'));
```

---

## 7. 뷰 템플릿

### Public/views/home.php

```php
<?php defined('CATPHP') || exit; ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title><?= e($title ?? 'CatPHP') ?></title>
</head>
<body>
    <h1><?= e($title ?? '홈') ?></h1>
    <p>안녕하세요, CatPHP입니다.</p>
</body>
</html>
```

### 렌더링

```php
// 라우트에서 뷰 호출
router()->get('/', fn() => render('home', ['title' => '홈페이지']));
```

- `render('home')` → `config('view.path') . '/home.php'` 로드
- 두 번째 인자 배열이 뷰에서 변수로 사용 가능 (`$title`)
- `e()` — HTML 이스케이프 (XSS 방어)
- `defined('CATPHP') || exit;` — 직접 접근 차단

---

## 8. 데이터베이스 연결

### SQLite (제로 설정)

```php
// config/app.php
'db' => [
    'driver' => 'sqlite',
    'dbname' => __DIR__ . '/../storage/app.db',
],
```

### 테이블 생성 (마이그레이션)

```php
// migrations/001_create_posts.php
<?php declare(strict_types=1);

return new class {
    public function up(): void
    {
        db()->raw("
            CREATE TABLE IF NOT EXISTS posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(255) NOT NULL,
                body TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function down(): void
    {
        db()->raw("DROP TABLE IF EXISTS posts");
    }
};
```

```bash
php cli.php migrate          # 마이그레이션 실행
php cli.php migrate:status   # 상태 확인
```

### 기본 쿼리

```php
// 전체 조회
$posts = db()->table('posts')->all();

// 조건 조회
$post = db()->table('posts')->where('id', 1)->first();

// 삽입
$id = db()->table('posts')->insert([
    'title' => '첫 글',
    'body'  => '내용입니다.',
]);

// 수정
db()->table('posts')->where('id', 1)->update([
    'title' => '수정된 제목',
]);

// 삭제
db()->table('posts')->where('id', 1)->delete();

// 페이지네이션
$result = db()->table('posts')
    ->orderBy('created_at', 'DESC')
    ->paginate(page: 1, perPage: 10);
```

---

## 9. CRUD 만들기

### 게시판 예제 (Public/index.php)

```php
<?php declare(strict_types=1);

require __DIR__ . '/../catphp/catphp.php';
config(require __DIR__ . '/../config/app.php');
errors(config('app.debug'));

router()->use(guard()->middleware());

// 목록
router()->get('/posts', function () {
    $posts = db()->table('posts')->orderBy('created_at', 'DESC')->all();
    return render('posts/list', ['posts' => $posts]);
});

// 상세
router()->get('/posts/{id}', function () {
    $post = db()->table('posts')->where('id', (int) input('id'))->first();
    if (!$post) {
        return render('404');
    }
    return render('posts/show', ['post' => $post]);
});

// 작성 폼
router()->get('/posts/create', fn() => render('posts/create'));

// 저장
router()->post('/posts', function () {
    csrf()->verify();

    $v = valid()->rules([
        'title' => 'required|min:2|max:255',
        'body'  => 'required|min:10',
    ])->check(input());

    if ($v->fails()) {
        flash()->error('입력값을 확인해 주세요.');
        redirect('/posts/create');
    }

    db()->table('posts')->insert([
        'title' => input('title'),
        'body'  => input('body'),
    ]);

    flash()->success('게시글이 작성되었습니다.');
    redirect('/posts');
});

// 삭제
router()->delete('/posts/{id}', function () {
    csrf()->verify();
    db()->table('posts')->where('id', (int) input('id'))->delete();
    flash()->success('삭제되었습니다.');
    redirect('/posts');
});

router()->notFound(fn() => render('404'));
router()->dispatch();
```

### 목록 뷰 (Public/views/posts/list.php)

```php
<?php defined('CATPHP') || exit; ?>
<h1>게시판</h1>
<a href="/posts/create">새 글 작성</a>

<?php if (flash()->has()): ?>
    <div class="alert"><?= e(flash()->get()) ?></div>
<?php endif; ?>

<ul>
<?php foreach ($posts as $post): ?>
    <li>
        <a href="/posts/<?= (int) $post['id'] ?>"><?= e($post['title']) ?></a>
        <small><?= e($post['created_at']) ?></small>
    </li>
<?php endforeach; ?>
</ul>
```

### 작성 폼 뷰 (Public/views/posts/create.php)

```php
<?php defined('CATPHP') || exit; ?>
<h1>새 글 작성</h1>

<form method="POST" action="/posts">
    <?= csrf()->field() ?>

    <label>제목</label>
    <input type="text" name="title" required>

    <label>내용</label>
    <textarea name="body" required></textarea>

    <button type="submit">작성</button>
</form>
```

---

## 10. API 만들기

```php
// API 라우트 그룹
router()->group('/api', function () {
    // 미들웨어 (인증 + 속도 제한 + CORS)
    $mw = fn() => api()->cors()->rateLimit(60, 100)->guard()->apply();

    // 게시글 목록
    router()->get('/posts', function () use ($mw) {
        $mw();
        $posts = db()->table('posts')->orderBy('id', 'DESC')->all();
        json()->ok($posts);
    });

    // 게시글 상세
    router()->get('/posts/{id}', function () use ($mw) {
        $mw();
        $post = db()->table('posts')->where('id', (int) input('id'))->first();
        $post ? json()->ok($post) : json()->fail('Not found', status: 404);
    });

    // 게시글 작성 (인증 필요)
    router()->post('/posts', function () use ($mw) {
        $mw();
        $user = auth()->check();
        if (!$user) {
            json()->fail('인증 필요', status: 401);
            return;
        }

        $v = valid()->rules([
            'title' => 'required|min:2',
            'body'  => 'required',
        ])->check(input());

        if ($v->fails()) {
            json()->fail('검증 실패', $v->errors(), 422);
            return;
        }

        $id = db()->table('posts')->insert([
            'title'   => input('title'),
            'body'    => input('body'),
            'user_id' => $user['sub'],
        ]);

        json()->ok(['id' => $id], 201);
    });
});
```

### API 응답 형식

```json
// 성공
{"ok": true, "data": [...], "meta": null}

// 실패
{"ok": false, "error": {"message": "인증 필요", "code": 401}}
```

### API 테스트

```bash
# 목록
curl http://127.0.0.1:8000/api/posts

# 작성 (JWT 토큰 필요)
curl -X POST http://127.0.0.1:8000/api/posts \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"title": "API 테스트", "body": "내용입니다"}'
```

---

## 11. 인증 추가

### JWT 로그인

```php
// 로그인
router()->post('/api/login', function () {
    $email = input('email');
    $password = input('password');

    $token = auth()->attempt($email, $password);
    if (!$token) {
        json()->fail('이메일 또는 비밀번호가 올바르지 않습니다.', status: 401);
        return;
    }

    json()->ok(['token' => $token]);
});

// 보호된 라우트에서 사용
$user = auth()->check();  // 유효하면 페이로드, 아니면 null
```

### config 설정

```php
'auth' => [
    'secret' => 'your-jwt-secret-key',  // 최소 32바이트
    'ttl'    => 86400,                    // 토큰 유효 시간 (초)
    'algo'   => 'Argon2id',              // 비밀번호 해싱
],
```

---

## 12. CLI 명령어

### cli.php 기본 구조

```php
<?php declare(strict_types=1);

if (php_sapi_name() !== 'cli') { exit; }

require __DIR__ . '/catphp/catphp.php';
config(require __DIR__ . '/config/app.php');

// 내장 명령어
cli()->command('serve', '개발 서버 실행', function () {
    $port = cli()->option('port', '8000');
    passthru("php -S 127.0.0.1:{$port} -t Public");
});

// 커스텀 명령어
cli()->command('greet', '인사 메시지', function () {
    $name = cli()->arg(0, '세계');
    cli()->success("안녕하세요, {$name}님!");
});

cli()->run();
```

### 자주 쓰는 CLI 명령어

```bash
php cli.php serve             # 개발 서버
php cli.php migrate           # DB 마이그레이션
php cli.php cache:clear       # 캐시 삭제
php cli.php log:tail          # 최근 로그
php cli.php check:env         # 환경 진단
php cli.php db:tables         # 테이블 목록
php cli.php db:backup         # DB 백업
```

---

## 13. 다음 단계

### 문서 참조

| 하고 싶은 것 | 문서 |
| --- | --- |
| 쿼리 빌더 상세 | [db.md](db.md) |
| 라우트 그룹, 미들웨어 | [router.md](router.md) |
| JWT 인증, CSRF | [security.md](security.md) |
| 캐시, 로그, 세션 | [cache-log.md](cache-log.md) |
| REST API 구축 | [api.md](api.md) |
| 입력 검증, 페이지네이션 | [data.md](data.md) |
| 사용자 관리, SEO | [web.md](web.md) |
| 파일 업로드, 이미지 처리 | [content.md](content.md) |
| 메일, 큐, 스케줄러 | [infra.md](infra.md) |
| 디버깅, 테스트 데이터 | [devtools.md](devtools.md) |
| 사이트맵, 백업, Swoole | [ops.md](ops.md) |
| 코어 구조, 확장 가이드 | [catphp-core.md](catphp-core.md) |

### 운영 체크리스트

- [ ] `config('app.debug')` → `false`
- [ ] `config('app.key')`, `config('auth.secret')`, `config('encrypt.key')` 설정
- [ ] `storage/` 디렉토리 쓰기 권한 확인
- [ ] `.htaccess` 또는 Nginx 설정 확인
- [ ] HTTPS 적용 (`config('session.secure')` → `true`)
- [ ] `config('cors.origins')` 실제 도메인으로 제한
- [ ] 오래된 로그/캐시 자동 정리 (`schedule()` 또는 cron)
