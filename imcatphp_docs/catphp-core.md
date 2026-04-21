# catphp.php — 코어 파일 심층 분석

CatPHP 프레임워크의 단일 진입점. `require` 1회로 오토로더, 설정 시스템, 도구 로더, 에러 핸들러, Shortcut 함수, 헬퍼 함수를 모두 부팅한다.

- **경로**: `catphp/catphp.php`
- **버전**: `CATPHP_VERSION` 상수 (`1.1.0`)
- **줄 수**: 632줄
- **의존성**: 없음 (순수 PHP 8.1+)

---

## 목차

1. [부팅 흐름](#1-부팅-흐름)
2. [상수](#2-상수)
3. [오토로더](#3-오토로더)
4. [설정 시스템 — config()](#4-설정-시스템--config)
5. [도구 로더 — cat()](#5-도구-로더--cat)
6. [에러 핸들러 — errors()](#6-에러-핸들러--errors)
7. [Shortcut 함수 전체 레퍼런스](#7-shortcut-함수-전체-레퍼런스)
8. [헬퍼 함수](#8-헬퍼-함수)
9. [내부 동작 원리](#9-내부-동작-원리)
10. [확장 가이드](#10-확장-가이드)

---

## 1. 부팅 흐름

```text
Public/index.php (또는 cli.php)
│
├─ require catphp/catphp.php     ← 코어 로드 (1회)
│   ├─ CATPHP 상수 정의
│   ├─ spl_autoload_register()   ← Cat\X 오토로더
│   ├─ config() 함수 정의
│   ├─ cat() 범용 로더 정의
│   ├─ errors() 에러 핸들러 정의
│   ├─ Shortcut 함수 62개 등록
│   └─ 헬퍼 함수 등록 (input, redirect, e, parse_size, is_cli)
│
├─ require config/app.php        ← config([...]) 호출로 설정 주입
├─ errors(config('app.debug'))   ← 에러 핸들러 활성화
│
└─ router()->dispatch()          ← 첫 도구 사용 시점에 지연 로드
```

### 핵심 원칙

- **require 1회**: `catphp.php` 하나만 require하면 프레임워크 전체 사용 가능
- **지연 로딩**: 도구 클래스는 실제 호출 시점까지 로드하지 않음
- **이중 지연**: 도구 객체 생성 ≠ 리소스(DB 연결 등) 생성

---

## 2. 상수

| 상수 | 값 | 용도 |
| --- | --- | --- |
| `CATPHP` | `true` | include 전용 파일 직접 접근 차단 가드 (`defined('CATPHP') \|\| exit;`) |
| `CATPHP_VERSION` | `'1.1.0'` | 프레임워크 버전 |

```php
// views/*.php 파일 첫 줄에 사용
defined('CATPHP') || exit;
```

---

## 3. 오토로더

```php
spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'Cat\\')) {
        return;
    }
    $file = __DIR__ . '/' . substr($class, 4) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
```

### 매핑 규칙

`Cat\X` 네임스페이스를 `catphp/X.php` 파일에 **플랫 매핑**한다.

| 클래스 | 파일 |
| --- | --- |
| `Cat\DB` | `catphp/DB.php` |
| `Cat\Router` | `catphp/Router.php` |
| `Cat\Auth` | `catphp/Auth.php` |

### 오토로더 특징

- **Cat\\** 접두사가 아닌 클래스는 무시 (Composer 등 다른 오토로더와 공존)
- **플랫 구조**: 하위 디렉토리 없음 → 경로 계산 최소화
- `is_file()` 검증 후 `require` → 존재하지 않는 파일 에러 방지

---

## 4. 설정 시스템 — config()

### 시그니처

```php
function config(string|array $key = '', mixed $default = null): mixed
```

### 사용법

```php
// ① 설정 초기화 (config/app.php에서 호출)
config([
    'app' => ['debug' => true, 'name' => 'My App'],
    'db'  => ['host' => '127.0.0.1', 'name' => 'mydb'],
]);

// ② dot notation 읽기
config('db.host');                    // '127.0.0.1'
config('db.port', 3306);             // 기본값 3306

// ③ 전체 설정 반환
config('');                           // 전체 배열
config();                             // 전체 배열 (동일)

// ④ 설정 업데이트 (재병합)
config(['db' => ['port' => 5432]]);   // db.host는 유지, db.port만 추가
```

### 내부 동작

```text
config('db.host')
│
├─ 캐시 확인 ($_cache['db.host'] 존재?)
│   ├─ 히트 → 즉시 반환
│   └─ 미스 → dot notation 파싱
│
├─ 세그먼트 분리: ['db', 'host']
├─ $_CATPHP_CONFIG에서 순차 탐색
│   ├─ $_CATPHP_CONFIG['db'] → 배열
│   └─ $_CATPHP_CONFIG['db']['host'] → '127.0.0.1'
│
├─ 캐시 저장: $_cache['db.host'] = '127.0.0.1'
└─ 반환: '127.0.0.1'
```

### config 캐싱 메커니즘

- `static $_cache`로 dot notation 파싱 결과를 캐시
- 센티넬 객체(`$_miss = new \stdClass()`)로 **"키가 없음"** 과 **"값이 null"** 을 구분
- `config([...])` 호출 시 캐시 전체 무효화

### config 병합 전략

```php
array_replace_recursive($_CATPHP_CONFIG, $newConfig);
```

- 기존 키는 유지하면서 새 키만 추가/덮어쓰기
- 중첩 배열도 재귀적으로 병합

---

## 5. 도구 로더 — cat()

### cat() 시그니처

```php
function cat(string $name): object
```

### cat() 사용법

```php
$db     = cat('DB');       // Cat\DB::getInstance()
$router = cat('Router');   // Cat\Router::getInstance()
```

### cat() 내부 동작

```text
cat('DB')
│
├─ $_CATPHP_INSTANCES['DB'] 존재? → 즉시 반환
│
├─ $class = 'Cat\DB'
├─ class_exists('Cat\DB')?
│   ├─ 아니오 → RuntimeException("catphp/DB.php 파일을 생성하세요")
│   └─ 예 → 오토로더가 catphp/DB.php를 require
│
├─ Cat\DB::getInstance() 호출
├─ $_CATPHP_INSTANCES['DB']에 저장
└─ 인스턴스 반환
```

### cat() 규칙

1. 모든 도구 클래스는 **`getInstance()`** 정적 메서드를 구현해야 함
2. 인스턴스는 `$_CATPHP_INSTANCES` 전역 배열에 캐시 (싱글턴)
3. 존재하지 않는 도구를 요청하면 **친절한 에러 메시지** 출력

### cat() vs Shortcut 함수

```php
// cat() — 범용, 반환 타입이 object
$db = cat('DB');

// shortcut — 전용, IDE 자동완성 지원 (반환 타입 명시)
$db = db();  // 반환 타입: \Cat\DB
```

> **권장**: 일반 코드에서는 Shortcut 함수를 사용하고, 동적 도구 로딩이 필요한 경우에만 `cat()`을 사용한다.

---

## 6. 에러 핸들러 — errors()

### errors() 시그니처

```php
function errors(bool $debug = false): void
```

### errors() 사용법

```php
// config/app.php 로드 후 호출
errors(config('app.debug'));
```

### errors() 동작 방식

#### set_error_handler

| PHP 에러 레벨 | 처리 |
| --- | --- |
| `E_WARNING`, `E_STRICT` 등 | `ErrorException`으로 변환하여 throw |
| `E_DEPRECATED`, `E_USER_DEPRECATED` | 무시 (서드파티 호환) |
| `E_NOTICE`, `E_USER_NOTICE` | 무시 |

#### set_exception_handler

```text
Uncaught Exception 발생
│
├─ output buffer 정리 (ob_end_clean)
│
├─ API 요청? (REQUEST_URI가 /api/로 시작)
│   ├─ 예 → JSON 에러 응답 {"success": false, "error": {...}}
│   └─ 아니오 → HTML 에러 페이지
│
├─ $debug = true?
│   ├─ 예 → 메시지 + 파일:줄 + 스택 트레이스 출력
│   └─ 아니오 → "서버 오류" 일반 메시지
│
├─ Cat\Log 로드 시도 → 에러 로그 기록
└─ exit(1)
```

### errors() 보안 고려사항

- `$debug = false`일 때 에러 상세 정보 노출 안 함
- `htmlspecialchars()`로 XSS 방어
- `ob_end_clean()`으로 불완전 출력 정리

---

## 7. Shortcut 함수 전체 레퍼런스

모든 Shortcut 함수는 `function_exists()` 가드로 충돌을 방지한다.

### 기본 도구 (4개)

| Shortcut | 반환 타입 | 도구 |
| --- | --- | --- |
| `db()` | `\Cat\DB` | 데이터베이스 쿼리 빌더 |
| `router()` | `\Cat\Router` | URL 라우팅 |
| `cache()` | `\Cat\Cache` | 캐시 (파일/Redis) |
| `logger()` | `\Cat\Log` | 로그 기록 |

```php
// 예시
db()->table('users')->where('id', 1)->first();
router()->get('/home', fn() => render('home'));
cache()->set('key', 'value', 3600);
logger()->info('요청 처리 완료');
```

### 보안 도구 (6개)

| Shortcut | 반환 타입 | 도구 |
| --- | --- | --- |
| `auth()` | `\Cat\Auth` | JWT 인증 |
| `csrf()` | `\Cat\Csrf` | CSRF 토큰 |
| `encrypt()` | `\Cat\Encrypt` | Sodium 암호화 |
| `firewall()` | `\Cat\Firewall` | IP 차단/허용 |
| `ip()` | `\Cat\Ip` | IP 분석 |
| `guard()` | `\Cat\Guard` | XSS 살균 |

```php
auth()->attempt($email, $password);
csrf()->token();
encrypt()->encrypt('비밀 데이터');
guard()->clean($userInput);
```

### 네트워크 도구 (3개)

| Shortcut | 반환 타입 | 도구 |
| --- | --- | --- |
| `http()` | `\Cat\Http` | HTTP 클라이언트 |
| `rate()` | `\Cat\Rate` | 속도 제한 |
| `cors()` | `\Cat\Cors` | CORS 헤더 |

```php
http()->get('https://api.example.com/data');
rate()->limit('api', 100, 60);
cors()->handle();
```

### API 도구 (2개)

| Shortcut | 반환 타입 | 도구 |
| --- | --- | --- |
| `json()` | `\Cat\Json` | JSON 응답 |
| `api()` | `\Cat\Api` | API 미들웨어 |

```php
json()->ok(['users' => $list]);
json()->fail('인증 실패', 401);
api()->middleware(['auth', 'rate']);
```

### 데이터 도구 (4개 + 헬퍼 2개)

| Shortcut | 반환 타입 | 도구 |
| --- | --- | --- |
| `valid()` | `\Cat\Valid` | 입력 검증 |
| `upload()` | `\Cat\Upload` | 파일 업로드 |
| `paginate()` | `\Cat\Paginate` | 페이지네이션 |
| `cookie()` | `\Cat\Cookie` | 쿠키 관리 |
| `render()` | `string` | 템플릿 렌더링 (헬퍼) |
| `e()` | `string` | HTML 이스케이프 (헬퍼) |

```php
// valid() — 규칙 배열을 바로 전달 가능
valid(['name' => 'required|min:2'])->check($data);

// render() — Router의 render() 위임
render('home', ['title' => '홈']);

// e() — 뷰 안에서 사용
<p><?= e($user->name) ?></p>
```

### 웹/CMS 도구 (8개 + 헬퍼 1개)

| Shortcut | 반환 타입 | 도구 |
| --- | --- | --- |
| `user()` | `\Cat\User` | 사용자 CRUD |
| `telegram()` | `\Cat\Telegram` | 텔레그램 봇 |
| `image()` | `\Cat\Image` | GD 이미지 처리 |
| `flash()` | `\Cat\Flash` | 플래시 메시지 |
| `perm()` | `\Cat\Perm` | 권한 관리 |
| `search()` | `\Cat\Search` | 전문 검색 |
| `meta()` | `\Cat\Meta` | SEO 메타 태그 |
| `geo()` | `\Cat\Geo` | 지역/다국어 |
| `trans()` | `string` | 번역 (헬퍼, `geo()->t()` 위임) |

```php
user()->find(1);
image()->open('photo.jpg')->resize(800, 600)->save('thumb.jpg');
meta()->title('페이지 제목')->description('설명')->render();
trans('welcome', ['name' => '홍길동']);
```

### 블로그 도구 (3개)

| Shortcut | 반환 타입 | 도구 |
| --- | --- | --- |
| `tag()` | `\Cat\Tag` | 태그 관리 |
| `feed()` | `\Cat\Feed` | RSS/Atom 피드 |
| `text()` | `\Cat\Text` | 텍스트 처리 |

```php
tag()->attach('post', $postId, ['php', 'framework']);
feed()->title('블로그')->items($posts)->render();
text()->excerpt($content, 200);
```

### 유틸+CLI 도구 (4개)

| Shortcut | 반환 타입 | 도구 |
| --- | --- | --- |
| `event()` | `\Cat\Event` | 이벤트 디스패처 |
| `slug()` | `\Cat\Slug` | URL 슬러그 |
| `cli()` | `\Cat\Cli` | CLI 인터페이스 |
| `spider()` | `\Cat\Spider` | 웹 크롤러 |

```php
event()->on('user.created', fn($user) => notify()->send($user));
slug()->generate('Hello World');  // 'hello-world'
cli()->table(['ID', '이름'], $rows);
```

### 인프라 도구 (8개)

| Shortcut | 반환 타입 | 도구 |
| --- | --- | --- |
| `redis()` | `\Cat\Redis` | Redis 클라이언트 |
| `mailer()` | `\Cat\Mail` | SMTP 메일 |
| `queue()` | `\Cat\Queue` | 작업 큐 |
| `storage()` | `\Cat\Storage` | 파일 시스템 |
| `schedule()` | `\Cat\Schedule` | cron 스케줄러 |
| `notify()` | `\Cat\Notify` | 다채널 알림 |
| `hasher()` | `\Cat\Hash` | 파일 체크섬 |
| `excel()` | `\Cat\Excel` | CSV/XLSX |

```php
redis()->set('session:123', $data, 3600);
mailer()->to('user@example.com')->subject('안녕')->send();
queue()->push('SendEmail', ['to' => $email]);
storage()->put('uploads/file.pdf', $content);
```

### 관리/연동 도구 (5개)

| Shortcut | 반환 타입 | 도구 |
| --- | --- | --- |
| `sitemap()` | `\Cat\Sitemap` | XML 사이트맵 |
| `backup()` | `\Cat\Backup` | DB 백업 |
| `dbview()` | `\Cat\DbView` | DB 구조 조회 |
| `webhook()` | `\Cat\Webhook` | Webhook 발송/수신 |
| `swoole()` | `\Cat\Swoole` | 비동기 서버 |

```php
sitemap()->url('/about', '2024-01-01')->save('sitemap.xml');
backup()->database();
dbview()->tables();
webhook()->to('https://hook.example.com')->payload($data)->send();
```

### 실용 도구 (9개 + dd/dump)

| Shortcut | 반환 타입 | 도구 |
| --- | --- | --- |
| `env()` | `mixed \| \Cat\Env` | 환경변수 (키 있으면 값, 없으면 인스턴스) |
| `request()` | `\Cat\Request` | HTTP 요청 |
| `response()` | `\Cat\Response` | HTTP 응답 |
| `session()` | `mixed \| \Cat\Session` | 세션 (키 있으면 값, 없으면 인스턴스) |
| `collect()` | `\Cat\Collection` | 배열 컬렉션 |
| `migration()` | `\Cat\Migration` | DB 마이그레이션 |
| `debug()` | `\Cat\Debug` | 디버그 도구 |
| `captcha()` | `\Cat\Captcha` | 이미지 캡차 |
| `faker()` | `\Cat\Faker` | 테스트 데이터 |
| `dd()` | `never` | 덤프 후 종료 |
| `dump()` | `void` | 덤프 (계속 실행) |

```php
env('APP_KEY');                          // 환경변수 값
env()->required('APP_KEY');              // 인스턴스 접근

session('user_id');                      // 세션 값 읽기
session()->set('user_id', 123);          // 인스턴스 접근

collect([1, 2, 3])->map(fn($n) => $n * 2)->toArray();

dd($variable);                           // 덤프 후 exit
dump($variable);                         // 덤프 후 계속
```

---

## 8. 헬퍼 함수

### input() — 요청 입력값 통합 읽기

```php
function input(?string $key = null, mixed $default = null, ?array $data = null): mixed
```

`$_GET`, `$_POST`, JSON body를 통합한 입력값 시스템.

```php
// 단일 값 읽기
$name = input('name');
$page = input('page', 1);

// 전체 입력값
$all = input();

// Guard 살균 결과 주입 (내부용)
input(data: $sanitized);
```

#### input 내부 동작

```text
input('name')
│
├─ $_cache === null? (최초 호출)
│   ├─ $_GET + $_POST 병합
│   └─ Content-Type이 application/json이면 php://input도 병합
│
└─ $_cache['name'] 반환 (없으면 $default)
```

#### JSON body 자동 감지

POST 요청의 `Content-Type`이 `application/json`이면 요청 본문을 자동으로 파싱하여 병합한다.

```bash
# 이 요청의 body도 input()으로 읽을 수 있음
curl -X POST -H "Content-Type: application/json" \
     -d '{"name": "홍길동"}' \
     https://example.com/api/users
```

---

### redirect() — URL 리디렉트

```php
function redirect(string $url, int $code = 302): never
```

```php
redirect('/login');             // 302 리디렉트
redirect('/dashboard', 301);   // 301 영구 리디렉트
```

#### redirect 보안 기능

1. **CRLF 인젝션 방어**: `\r`, `\n`, `\0` 문자 제거
2. **오픈 리다이렉트 방어**: 외부 URL은 `config('response.allowed_hosts')`에 등록된 호스트만 허용

```php
// config/app.php
'response' => [
    'allowed_hosts' => ['trusted.example.com'],
],
```

---

### e() — HTML 이스케이프

```php
function e(string $value): string
```

```php
// 뷰 템플릿에서 XSS 방어
<p><?= e($userInput) ?></p>
```

내부적으로 `htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` 호출.

---

### parse_size() — 사이즈 문자열 파싱

```php
function parse_size(string $size): int
```

```php
parse_size('10M');   // 10485760
parse_size('1G');    // 1073741824
parse_size('512K');  // 524288
parse_size('1024');  // 1024
```

| 접미사 | 단위 |
| --- | --- |
| `G` | × 1,073,741,824 (1 GiB) |
| `M` | × 1,048,576 (1 MiB) |
| `K` | × 1,024 (1 KiB) |
| 없음 | 바이트 그대로 |

> Guard, Upload 등 여러 도구에서 공용으로 사용한다.

---

### is_cli() — CLI 환경 감지

```php
function is_cli(): bool
```

```php
if (is_cli()) {
    echo "CLI 모드\n";
}
```

`PHP_SAPI`가 `'cli'` 또는 `'phpdbg'`이면 `true`.

---

## 9. 내부 동작 원리

### 전역 변수

| 변수 | 타입 | 용도 |
| --- | --- | --- |
| `$_CATPHP_CONFIG` | `array<string, mixed>` | 설정 저장소 |
| `$_CATPHP_INSTANCES` | `array<string, object>` | 도구 싱글턴 캐시 |

### 성능 최적화

| 기법 | 위치 | 효과 |
| --- | --- | --- |
| dot notation 캐시 | `config()` 내부 `$_cache` | 반복 읽기 시 파싱 생략 |
| 센티넬 패턴 | `config()` 내부 `$_miss` | null 값과 미존재 키 구분 |
| 싱글턴 캐시 | `cat()` 내부 `$_CATPHP_INSTANCES` | 도구 재생성 방지 |
| `function_exists()` 가드 | 모든 Shortcut | 유저 함수와 충돌 방지 |
| 지연 오토로딩 | `spl_autoload_register` | 미사용 도구 파일 로드 안 함 |

### OPcache Preload 지원

```php
// catphp/preload.php
opcache_compile_file(__DIR__ . '/catphp.php');
// + 자주 사용하는 도구 파일들
```

`php.ini`에서 `opcache.preload`로 지정하면 코어 파일이 공유 메모리에 미리 컴파일된다.

---

## 10. 확장 가이드

### 새 도구 추가 (3단계)

#### 1단계: 도구 파일 생성

```php
// catphp/MyTool.php
<?php declare(strict_types=1);

namespace Cat;

final class MyTool
{
    private static ?self $instance = null;

    private function __construct()
    {
        // 초기화
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    // ... 메서드 구현
}
```

#### 2단계: Shortcut 함수 등록 (catphp.php)

```php
if (!function_exists('mytool')) {
    function mytool(): \Cat\MyTool { return cat('MyTool'); }
}
```

#### 3단계: 사용

```php
mytool()->someMethod();
```

### 도구 작성 규칙

1. `declare(strict_types=1)` 필수
2. `namespace Cat;` 필수
3. `final class` 권장
4. `getInstance(): self` 정적 팩토리 필수
5. 생성자는 `private` (외부 인스턴스화 차단)
6. 매직 메서드 사용 금지 (`__get`, `__set`, `__call` 등)

### 조건부 Shortcut (env/session 패턴)

키를 전달하면 값을 반환하고, 키 없이 호출하면 인스턴스를 반환하는 이중 동작:

```php
if (!function_exists('myconfig')) {
    function myconfig(?string $key = null, mixed $default = null): mixed
    {
        $instance = cat('MyConfig');
        if ($key === null) {
            return $instance;
        }
        return $instance->get($key, $default);
    }
}

// 사용
myconfig('theme');           // 값 반환
myconfig()->set('theme', 'dark');  // 인스턴스 접근
```

---

## Shortcut 함수 요약 (총 66개)

| 그룹 | 개수 | 함수 목록 |
| --- | --- | --- |
| 기본 | 4 | `db`, `router`, `cache`, `logger` |
| 보안 | 7 | `auth`, `csrf`, `encrypt`, `firewall`, `ip`, `guard`, `sanitizer` |
| 네트워크 | 3 | `http`, `rate`, `cors` |
| API | 2 | `json`, `api` |
| 데이터 | 6 | `valid`, `render`, `e`, `upload`, `paginate`, `cookie` |
| 웹/CMS | 9 | `user`, `telegram`, `image`, `flash`, `perm`, `search`, `meta`, `geo`, `trans` |
| 블로그 | 3 | `tag`, `feed`, `text` |
| 유틸+CLI | 4 | `event`, `slug`, `cli`, `spider` |
| 인프라 | 8 | `redis`, `mailer`, `queue`, `storage`, `schedule`, `notify`, `hasher`, `excel` |
| 관리/연동 | 5 | `sitemap`, `backup`, `dbview`, `webhook`, `swoole` |
| 실용 | 11 | `env`, `request`, `response`, `session`, `collect`, `migration`, `debug`, `dd`, `dump`, `captcha`, `faker` |
| 헬퍼 | 4 | `input`, `redirect`, `parse_size`, `is_cli` |
| **합계** | **66** | |
