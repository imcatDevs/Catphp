# 캐시 · 로그 · 세션 — Cache · Log · Redis · Session

CatPHP의 데이터 저장/조회 계층. 파일 캐시, 로깅, Redis, 세션 관리를 제공한다.

| 도구 | Shortcut | 클래스 | 줄 수 |
| --- | --- | --- | --- |
| Cache | `cache()` | `Cat\Cache` | 129 |
| Log | `logger()` | `Cat\Log` | 189 |
| Redis | `redis()` | `Cat\Redis` | 304 |
| Session | `session()` | `Cat\Session` | 283 |

---

## 목차

1. [Cache — 파일 캐시](#1-cache--파일-캐시)
2. [Log — 로거](#2-log--로거)
3. [Redis — Redis 클라이언트](#3-redis--redis-클라이언트)
4. [Session — 세션 관리](#4-session--세션-관리)

---

## 1. Cache — 파일 캐시

파일 기반 키-값 캐시. 외부 의존성 없이 동작.

### Cache 설정

```php
'cache' => [
    'path' => dirname(__DIR__) . '/storage/cache',  // 캐시 디렉토리
    'ttl'  => 3600,                                   // 기본 TTL (초)
],
```

### Cache 메서드

| 메서드 | 반환 타입 | 설명 |
| --- | --- | --- |
| `get(key, default)` | `mixed` | 캐시 읽기 |
| `set(key, value, ttl)` | `bool` | 캐시 쓰기 |
| `del(key)` | `bool` | 캐시 삭제 |
| `has(key)` | `bool` | 존재 확인 |
| `clear()` | `bool` | 전체 삭제 |
| `remember(key, callback, ttl)` | `mixed` | 없으면 콜백 실행 후 저장 |

### Cache 사용 예시

```php
// 기본 사용
cache()->set('user:1', $userData, 3600);   // 1시간 캐시
$user = cache()->get('user:1');             // 읽기
cache()->del('user:1');                     // 삭제

// TTL 0 = 영구 캐시
cache()->set('config:site', $siteConfig, 0);

// 존재 확인
if (cache()->has('user:1')) { ... }

// 전체 삭제
cache()->clear();
```

### Cache remember 패턴

```php
// 캐시에 없으면 콜백 실행 → 결과 저장 → 반환
$users = cache()->remember('all_users', function () {
    return db()->table('users')->all();
}, 1800);  // 30분 캐시
```

`remember()`는 **null 값도 캐싱** 가능하다 (내부 센티넬 객체로 "미존재"와 "null 값"을 구분).

### Cache 내부 동작

- **키 → 파일**: `xxh3` 해시로 파일명 생성 (`storage/cache/{hash}.cache`)
- **직렬화**: PHP `serialize()` — 만료 시간 + 값을 함께 저장
- **역직렬화**: `unserialize(['allowed_classes' => false])` — 객체 역직렬화 RCE 방지
- **파일 락**: 쓰기 시 `LOCK_EX` 배타 락
- **만료 정리**: 읽기 시 만료된 캐시 자동 삭제 (lazy expiration)

---

## 2. Log — 로거

일별 로그 파일 기반 로거. PSR-3 스타일 API.

### Log 설정

```php
'log' => [
    'path'  => dirname(__DIR__) . '/storage/logs',  // 로그 디렉토리
    'level' => 'debug',                               // 최소 레벨: debug | info | warn | error
],
```

### Log 레벨

| 레벨 | 값 | 용도 |
| --- | --- | --- |
| `DEBUG` | 0 | 개발 디버깅 |
| `INFO` | 1 | 일반 정보 |
| `WARN` | 2 | 경고 |
| `ERROR` | 3 | 에러 |

설정된 최소 레벨보다 낮은 로그는 무시된다.

### Log 메서드

```php
logger()->debug('디버그 메시지', ['key' => 'value']);
logger()->info('사용자 로그인', ['user_id' => 1]);
logger()->warn('디스크 사용률 높음', ['usage' => '90%']);
logger()->error('DB 연결 실패', ['host' => '127.0.0.1']);
```

출력 형식:

```text
[2024-01-15 10:30:00] [INFO] 사용자 로그인 {"user_id":1}
```

### Log 관리

```php
// 오늘 로그 마지막 20줄 읽기
$recent = logger()->tail(20);

// 30일 이전 로그 파일 삭제 (로테이션)
$deleted = logger()->clean(30);  // 삭제된 파일 수 반환

// 오늘 로그 파일 삭제
logger()->clear();
```

### Log 내부 동작

- **파일명**: `YYYY-MM-DD.log` (일별 자동 분리)
- **파일 락**: `FILE_APPEND | LOCK_EX` (동시 쓰기 안전)
- **로그 인젝션 방어**: 메시지 내 `\r`, `\n`, `\0` 제거
- **tail()**: `fseek` 역방향 8KB 버퍼 읽기 — 대용량 로그에서도 효율적

---

## 3. Redis — Redis 클라이언트

`ext-redis` 기반 Redis 래퍼. 캐시, 세션, 큐, Pub/Sub 등 다목적.

### Redis 설정

```php
'redis' => [
    'host'     => '127.0.0.1',
    'port'     => 6379,
    'password' => null,
    'database' => 0,
    'prefix'   => 'catphp:',
    'timeout'  => 2.0,
],
```

### Redis String 명령어

```php
redis()->set('key', 'value', 60);      // 60초 TTL
redis()->set('key', ['a' => 1]);        // 배열도 가능 (JSON 직렬화)
redis()->get('key');                     // 값 읽기
redis()->get('missing', 'default');      // 기본값
redis()->del('key');                     // 삭제
redis()->exists('key');                  // 존재 확인
redis()->expire('key', 300);            // TTL 설정
redis()->ttl('key');                     // 남은 TTL
redis()->incr('counter');                // 1 증가
redis()->incr('counter', 5);             // 5 증가
redis()->decr('counter');                // 1 감소
```

### Redis Hash 명령어

```php
redis()->hSet('user:1', 'name', 'Alice');
redis()->hGet('user:1', 'name');               // 'Alice'
redis()->hGetAll('user:1');                     // ['name' => 'Alice', ...]
redis()->hDel('user:1', 'name');
redis()->hExists('user:1', 'name');
```

### Redis List 명령어 (큐)

```php
redis()->rPush('queue:jobs', $jobData);        // 큐 입력 (오른쪽)
redis()->lPop('queue:jobs');                    // 큐 소비 (왼쪽)
redis()->blPop('queue:jobs', 10);              // 블로킹 소비 (10초 대기)
redis()->lLen('queue:jobs');                    // 큐 길이
redis()->lRange('queue:jobs', 0, -1);          // 전체 조회
```

### Redis Set 명령어

```php
redis()->sAdd('tags', 'php', 'framework');
redis()->sMembers('tags');                      // ['php', 'framework']
redis()->sIsMember('tags', 'php');              // true
redis()->sRem('tags', 'php');
```

### Redis Sorted Set 명령어

```php
redis()->zAdd('leaderboard', 100.0, 'player1');
redis()->zAdd('leaderboard', 200.0, 'player2');
redis()->zRange('leaderboard', 0, -1);                  // 점수 순
redis()->zRange('leaderboard', 0, -1, withScores: true); // 점수 포함
redis()->zScore('leaderboard', 'player1');                // 100.0
redis()->zRem('leaderboard', 'player1');
```

### Redis Pub/Sub

```php
redis()->publish('notifications', ['type' => 'alert', 'msg' => '새 주문']);
```

### Redis 유틸리티

```php
redis()->remember('cache:users', fn() => db()->table('users')->all(), 1800);
redis()->keys('user:*');        // 패턴 검색 (프로덕션 주의)
redis()->flush();                // 현재 DB 전체 삭제
redis()->ping();                 // 연결 확인 (true/false)
redis()->raw();                  // 원시 \Redis 인스턴스 (고급 사용)
```

### Redis 내부 동작

- **지연 연결**: 첫 명령 실행 시점에 연결 수립
- **직렬화**: `SERIALIZER_JSON` — PHP unserialize RCE 방지
- **접두사**: `catphp:` 자동 접두사 (다른 앱과 키 충돌 방지)
- **인증**: 비밀번호 실패 시 `RuntimeException`
- **확장 체크**: `ext-redis` 미설치 시 친절한 에러 메시지
- **소멸자**: `close()` 자동 호출

---

## 4. Session — 세션 관리

PHP 네이티브 세션을 config 기반으로 초기화하고 편의 메서드 제공.

### Session 설정

```php
'session' => [
    'lifetime' => 7200,       // 세션 쿠키 수명 (초)
    'path'     => '/',         // 쿠키 경로
    'secure'   => false,       // HTTPS 전용
    'httponly'  => true,       // JavaScript 접근 차단
    'samesite'  => 'Lax',     // SameSite 정책
],
```

### Session Shortcut 이중 동작

```php
// 키 전달 → 값 반환 (session()->get() 위임)
$userId = session('user_id');
$role   = session('role', 'guest');

// 키 없이 → 인스턴스 반환
session()->set('user_id', 123);
session()->forget('cart');
```

### Session 기본 CRUD

```php
session()->set('key', 'value');          // 설정
session()->get('key', 'default');        // 읽기
session()->has('key');                    // 존재 확인
session()->forget('key');                // 삭제
session()->pull('key');                   // 읽기 + 삭제
session()->all();                         // 전체
session()->clear();                       // 전체 초기화
```

### Session Flash 데이터

다음 요청까지만 유지되는 일회성 데이터.

```php
// flash 설정 (리디렉트 전)
session()->flash('success', '저장 완료');
session()->flash('errors', ['name' => '필수 항목']);

// flash 읽기 (리디렉트 후 뷰에서)
$msg = session()->getFlash('success');

// flash 존재 확인
session()->hasFlash('success');

// 현재 flash를 한 번 더 유지
session()->reflash();

// 특정 flash만 유지
session()->keep(['success']);
```

#### Flash 내부 동작

```text
요청 1: flash('msg', 'hello') → _flash_new = ['msg']
        ─ 세션 저장 ─
요청 2: ageFlash() → _flash_old = ['msg'], _flash_new = []
        getFlash('msg') → 'hello' (읽기 가능)
        ─ 세션 저장 ─
요청 3: ageFlash() → 'msg' 키 삭제 (수명 종료)
```

### Session 관리

```php
// 세션 ID 재생성 (세션 고정 공격 방지)
session()->regenerate();
session()->regenerate(deleteOldSession: false);  // 이전 세션 유지

// 세션 파괴 (로그아웃)
session()->destroy();

// 세션 정보
session()->id();           // 세션 ID 문자열
session()->name();         // 세션 이름 (기본 'PHPSESSID')
session()->isStarted();    // 시작 여부
```

### Session 유틸리티

```php
// 없으면 콜백 결과를 저장 후 반환
$cart = session()->remember('cart', fn() => []);

// 증감
session()->increment('page_views');
session()->decrement('credits', 5);

// CSRF 토큰
$token = session()->token();  // 없으면 자동 생성
```

### Session CLI 환경

CLI(`PHP_SAPI === 'cli'`)에서는 `session_start()`를 호출하지 않고, `$_SESSION` 배열을 메모리에서 직접 관리한다. 테스트에서 세션을 사용할 수 있다.

### Session 보안

| 항목 | 구현 |
| --- | --- |
| 세션 고정 | `regenerate()` — 로그인 시 자동 호출 (Auth 연동) |
| 쿠키 보안 | `httpOnly`, `secure`, `sameSite` config |
| 세션 파괴 | 쿠키 삭제 + `session_destroy()` |
| GC | `gc_maxlifetime` = session lifetime |

---

## 도구 간 연동

```text
Cache ← Ip (GeoIP 캐싱)
Cache ← Search, Feed, Sitemap 등 (결과 캐싱)
Redis ← Queue (작업 큐 백엔드)
Redis ← Rate (속도 제한 카운터)
Session ← Auth (세션 로그인/로그아웃)
Session ← Csrf (CSRF 토큰 저장)
Session ← Flash (플래시 메시지)
Log ← errors() (전역 에러 핸들러)
Log ← Guard (공격 감지 로깅)
```
