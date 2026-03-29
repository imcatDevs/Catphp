# CatPHP

PHP 8.2+ 전용 경량 프레임워크. **빠른 속도 · 사용 편리 · 쉬운 학습 · 보안**

## 설치

```php
require __DIR__ . '/catphp/catphp.php';
config(require __DIR__ . '/config/app.php');
```

## 기본 사용법

### 라우팅

```php
router()->get('/', fn() => '<h1>Hello</h1>');
router()->get('/posts/{slug}', fn(string $slug) => render('post', ['slug' => $slug]));
router()->group('/admin', function() {
    router()->use(perm()->middleware('admin'));
    router()->get('/dashboard', fn() => render('admin/dashboard'));
});
router()->dispatch();
```

### DB

```php
$users = db()->table('users')->all();
$user  = db()->table('users')->where('id', 1)->first();
$id    = db()->table('users')->insert(['name' => '홍길동', 'email' => 'hong@example.com']);
db()->table('users')->where('id', 1)->update(['name' => '김철수']);
db()->table('users')->where('id', 1)->delete();
```

### 캐시

```php
cache()->set('key', $value, 3600);
$value = cache()->get('key');
$value = cache()->remember('key', fn() => db()->table('posts')->all(), 600);
```

### 로그

```php
logger()->info('사용자 로그인', ['user_id' => 1]);
logger()->error('DB 연결 실패');
```

## API 개발

```php
router()->group('/api', function() {
    api()->cors()->rateLimit(60, 100)->auth()->guard()->apply();

    router()->get('/posts', fn() => json()->ok(db()->table('posts')->all()));

    router()->post('/posts', function(): void {
        $v = valid(['title' => 'required|min:2', 'content' => 'required']);
        if ($v->check(input())->fails()) {
            json()->fail('입력값 오류', 422, $v->errors());
        }
        $id = db()->table('posts')->insert(input());
        json()->created(['id' => $id]);
    });
});
```

### JSON 응답 포맷

```json
{"success": true, "statusCode": 200, "data": [...], "message": "Success", "error": null, "timestamp": ...}
{"success": false, "statusCode": 404, "message": "Not Found", "error": {"message": "Not Found", "name": "NotFound"}, ...}
```

## 보안

```php
// 미들웨어 적용
router()->use(guard()->middleware());
router()->use(csrf()->verify(...));

// 공격 감지 시 텔레그램 알림
guard()->onAttack(fn($type, $ip) =>
    telegram()->to(config('telegram.admin_chat'))
        ->html("<b>🚨 공격 감지</b>\nIP: {$ip}")
        ->send()
);

// 인증
$token = auth()->createToken(['user_id' => 1]);
$payload = auth()->verifyToken($token);
$hash = auth()->hashPassword('password');

// HTML 정제 (XSS 방어)
$clean = sanitizer()->allowImages()->allowLinks()->clean($userHtml);
```

## 웹/CMS

```php
// SEO 메타 태그
meta()->title('페이지 제목')->description('설명')->og('image', $url)->render();

// 이미지 처리
image()->open($path)->resize(800, 600)->watermark('logo')->save('output.webp');

// 다국어
geo()->t('welcome.title');           // '환영합니다'
geo()->locale('en')->t('welcome.title'); // 'Welcome'
geo()->currency(29900);              // '₩29,900'

// 검색
search()->query('키워드')->in('posts', ['title', 'content'])->results();
```

## 블로그

```php
// 태그
tag()->attach('posts', $postId, ['PHP', 'CatPHP']);
tag()->cloud('posts');  // ['PHP' => 15, 'CatPHP' => 8]

// RSS 피드
feed()->title('My Blog')->fromQuery(db()->table('posts')->all())->rss();

// 본문 처리
text()->excerpt($content, 200);    // 200자 발췌
text()->readingTime($content);     // '3분'
```

## CLI

```bash
php cli.php help
php cli.php cache:clear
php cli.php firewall:list
php cli.php firewall:ban 1.2.3.4
php cli.php log:tail --lines=50
```

## 도구 추가

`catphp/` 디렉토리에 PHP 파일 1개를 추가하면 자동으로 도구가 등록됩니다.

```php
// catphp/MyTool.php
namespace Cat;
final class MyTool {
    private static ?self $instance = null;
    public static function getInstance(): self {
        return self::$instance ??= new self();
    }
}

// catphp/catphp.php에 shortcut 추가
if (!function_exists('mytool')) {
    function mytool(): \Cat\MyTool { return cat('MyTool'); }
}
```

## OPcache

```ini
; php.ini — 기본 설정 (preload 없이, Windows 호환)
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0          ; 프로덕션: 0 / 개발: 1
opcache.jit=1255
opcache.jit_buffer_size=100M
```

코어 1파일 + 지연 로딩이라 preload 없이도 성능 영향이 미미합니다.

Linux 환경에서 preload를 추가로 사용하려면:

```ini
; (선택) Linux 전용 — 첫 요청부터 코어 미리 로드
opcache.preload=catphp/preload.php
opcache.preload_user=www-data
```

## 라이선스

[Apache License 2.0](LICENSE)
