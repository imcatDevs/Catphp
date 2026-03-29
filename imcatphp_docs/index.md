---
title: CatPHP 프레임워크 문서
---

# CatPHP 프레임워크 문서

PHP 8.2+ 전용 경량 프레임워크. **빠른 속도 · 사용 편리 · 쉬운 학습 · 보안** 4대 원칙.

```php
require 'catphp/catphp.php';
require 'config/app.php';
errors(config('app.debug'));

router()->get('/', fn() => 'Hello CatPHP!');
router()->dispatch();
```

---

## 문서 구성

| # | 문서 | 도구 | 설명 |
| --- | --- | --- | --- |
| 0 | [catphp-core.md](catphp-core.md) | 코어 | 부팅, config, autoloader, shortcut, 헬퍼, 확장 가이드 |
| 1 | [quickstart.md](quickstart.md) | — | 설치부터 첫 프로젝트까지 빠른 시작 가이드 |
| 2 | [db.md](db.md) | DB · Migration · DbView | 쿼리 빌더, 스키마 마이그레이션, DB 구조 탐색 |
| 3 | [router.md](router.md) | Router · Request · Response | URL 라우팅, HTTP 요청/응답, 뷰 렌더링 |
| 4 | [security.md](security.md) | Auth · Csrf · Encrypt · Firewall · Ip · Guard | JWT 인증, CSRF, 암호화, IP 차단, XSS 살균 |
| 5 | [cache-log.md](cache-log.md) | Cache · Log · Redis · Session | 파일/Redis 캐시, 로그, 세션 |
| 6 | [api.md](api.md) | Json · Api · Cors · Rate · Http | JSON 응답, API 미들웨어, CORS, 속도 제한, HTTP 클라이언트 |
| 7 | [data.md](data.md) | Valid · Collection · Paginate · Cookie · Env | 입력 검증, 컬렉션, 페이지네이션, 쿠키, 환경변수 |
| 8 | [web.md](web.md) | User · Perm · Flash · Meta · Geo · Search | 사용자 CRUD, 권한, 플래시, SEO, 다국어, 검색 |
| 9 | [content.md](content.md) | Tag · Feed · Text · Slug · Image · Upload | 태그, RSS, 텍스트, 슬러그, 이미지, 파일 업로드 |
| 10 | [infra.md](infra.md) | Mail · Queue · Storage · Schedule · Notify · Excel · Hash | SMTP, 작업 큐, 파일 시스템, 스케줄러, 알림, CSV/XLSX, 해시 |
| 11 | [devtools.md](devtools.md) | Debug · Captcha · Faker · Cli · Event · Spider | 디버그, 캡차, 테스트 데이터, CLI, 이벤트, 크롤러 |
| 12 | [ops.md](ops.md) | Sitemap · Backup · Webhook · Swoole · Telegram | 사이트맵, DB 백업, 웹훅, 비동기 서버, 텔레그램 봇 |

---

## 전체 도구 목록 (56개)

### 코어 (4개)

| Shortcut | 클래스 | 문서 | 한줄 설명 |
| --- | --- | --- | --- |
| `db()` | `Cat\DB` | [db.md](db.md) · [DB](tools/DB.md) | 쿼리 빌더 (MySQL/PostgreSQL/SQLite) |
| `router()` | `Cat\Router` | [router.md](router.md) · [Router](tools/Router.md) | URL 라우팅 + 뷰 렌더링 |
| `cache()` | `Cat\Cache` | [cache-log.md](cache-log.md) · [Cache](tools/Cache.md) | 파일/Redis 캐시 |
| `logger()` | `Cat\Log` | [cache-log.md](cache-log.md) · [Log](tools/Log.md) | PSR-3 호환 로그 |

### HTTP (4개)

| Shortcut | 클래스 | 문서 | 한줄 설명 |
| --- | --- | --- | --- |
| `request()` | `Cat\Request` | [router.md](router.md) · [Request](tools/Request.md) | HTTP 요청 추상화 |
| `response()` | `Cat\Response` | [router.md](router.md) · [Response](tools/Response.md) | HTTP 응답 빌더 |
| `http()` | `Cat\Http` | [api.md](api.md) · [Http](tools/Http.md) | HTTP 클라이언트 (cURL) |
| `cors()` | `Cat\Cors` | [api.md](api.md) · [Cors](tools/Cors.md) | CORS 헤더 처리 |

### 보안 (6개)

| Shortcut | 클래스 | 문서 | 한줄 설명 |
| --- | --- | --- | --- |
| `auth()` | `Cat\Auth` | [security.md](security.md) · [Auth](tools/Auth.md) | JWT 인증 |
| `csrf()` | `Cat\Csrf` | [security.md](security.md) · [Csrf](tools/Csrf.md) | CSRF 토큰 |
| `encrypt()` | `Cat\Encrypt` | [security.md](security.md) · [Encrypt](tools/Encrypt.md) | Sodium 암호화 |
| `firewall()` | `Cat\Firewall` | [security.md](security.md) · [Firewall](tools/Firewall.md) | IP 차단/허용 |
| `ip()` | `Cat\Ip` | [security.md](security.md) · [Ip](tools/Ip.md) | IP 분석 |
| `guard()` | `Cat\Guard` | [security.md](security.md) · [Guard](tools/Guard.md) | XSS 살균 |

### API (4개)

| Shortcut | 클래스 | 문서 | 한줄 설명 |
| --- | --- | --- | --- |
| `json()` | `Cat\Json` | [api.md](api.md) · [Json](tools/Json.md) | JSON 응답 (ok/fail 통일 포맷) |
| `api()` | `Cat\Api` | [api.md](api.md) · [Api](tools/Api.md) | API 미들웨어 (인증+속도제한+CORS) |
| `rate()` | `Cat\Rate` | [api.md](api.md) · [Rate](tools/Rate.md) | 속도 제한 |
| `webhook()` | `Cat\Webhook` | [ops.md](ops.md) · [Webhook](tools/Webhook.md) | Webhook 발송/수신 + HMAC 서명 |

### 데이터 (7개)

| Shortcut | 클래스 | 문서 | 한줄 설명 |
| --- | --- | --- | --- |
| `valid()` | `Cat\Valid` | [data.md](data.md) · [Valid](tools/Valid.md) | 입력 검증 |
| `collect()` | `Cat\Collection` | [data.md](data.md) · [Collection](tools/Collection.md) | 배열 체이닝 |
| `paginate()` | `Cat\Paginate` | [data.md](data.md) · [Paginate](tools/Paginate.md) | 페이지네이션 |
| `cookie()` | `Cat\Cookie` | [data.md](data.md) · [Cookie](tools/Cookie.md) | 쿠키 관리 |
| `env()` | `Cat\Env` | [data.md](data.md) · [Env](tools/Env.md) | .env 파서 |
| `session()` | `Cat\Session` | [cache-log.md](cache-log.md) · [Session](tools/Session.md) | 세션 관리 |
| `redis()` | `Cat\Redis` | [cache-log.md](cache-log.md) · [Redis](tools/Redis.md) | Redis 클라이언트 |

### DB (2개)

| Shortcut | 클래스 | 문서 | 한줄 설명 |
| --- | --- | --- | --- |
| `migration()` | `Cat\Migration` | [db.md](db.md) · [Migration](tools/Migration.md) | DB 스키마 마이그레이션 |
| `dbview()` | `Cat\DbView` | [db.md](db.md) · [DbView](tools/DbView.md) | DB 구조 조회/탐색 |

### 웹 (6개)

| Shortcut | 클래스 | 문서 | 한줄 설명 |
| --- | --- | --- | --- |
| `user()` | `Cat\User` | [web.md](web.md) · [User](tools/User.md) | 사용자 CRUD |
| `perm()` | `Cat\Perm` | [web.md](web.md) · [Perm](tools/Perm.md) | 역할/권한 관리 |
| `flash()` | `Cat\Flash` | [web.md](web.md) · [Flash](tools/Flash.md) | 플래시 메시지 |
| `meta()` | `Cat\Meta` | [web.md](web.md) · [Meta](tools/Meta.md) | SEO 메타 태그 + JSON-LD |
| `geo()` | `Cat\Geo` | [web.md](web.md) · [Geo](tools/Geo.md) | 지역/다국어 |
| `search()` | `Cat\Search` | [web.md](web.md) · [Search](tools/Search.md) | 전문 검색 (FULLTEXT/LIKE) |

### 콘텐츠 (6개)

| Shortcut | 클래스 | 문서 | 한줄 설명 |
| --- | --- | --- | --- |
| `tag()` | `Cat\Tag` | [content.md](content.md) · [Tag](tools/Tag.md) | 다형성 태그 |
| `feed()` | `Cat\Feed` | [content.md](content.md) · [Feed](tools/Feed.md) | RSS/Atom 피드 |
| `text()` | `Cat\Text` | [content.md](content.md) · [Text](tools/Text.md) | 텍스트 유틸 (발췌, 읽기 시간) |
| `slug()` | `Cat\Slug` | [content.md](content.md) · [Slug](tools/Slug.md) | URL 슬러그 (다국어) |
| `image()` | `Cat\Image` | [content.md](content.md) · [Image](tools/Image.md) | GD 이미지 처리 |
| `upload()` | `Cat\Upload` | [content.md](content.md) · [Upload](tools/Upload.md) | 파일 업로드 |

### 인프라 (7개)

| Shortcut | 클래스 | 문서 | 한줄 설명 |
| --- | --- | --- | --- |
| `mailer()` | `Cat\Mail` | [infra.md](infra.md) · [Mail](tools/Mail.md) | 순수 소켓 SMTP |
| `queue()` | `Cat\Queue` | [infra.md](infra.md) · [Queue](tools/Queue.md) | 비동기 작업 큐 (Redis/DB) |
| `storage()` | `Cat\Storage` | [infra.md](infra.md) · [Storage](tools/Storage.md) | 파일 시스템 (로컬/S3) |
| `schedule()` | `Cat\Schedule` | [infra.md](infra.md) · [Schedule](tools/Schedule.md) | cron 스케줄러 |
| `notify()` | `Cat\Notify` | [infra.md](infra.md) · [Notify](tools/Notify.md) | 다채널 알림 |
| `excel()` | `Cat\Excel` | [infra.md](infra.md) · [Excel](tools/Excel.md) | CSV/XLSX 가져오기·내보내기 |
| `hasher()` | `Cat\Hash` | [infra.md](infra.md) · [Hash](tools/Hash.md) | 파일 체크섬·무결성 |

### 개발 도구 (6개)

| Shortcut | 클래스 | 문서 | 한줄 설명 |
| --- | --- | --- | --- |
| `debug()` | `Cat\Debug` | [devtools.md](devtools.md) · [Debug](tools/Debug.md) | 디버그 바·타이머·덤프 |
| `captcha()` | `Cat\Captcha` | [devtools.md](devtools.md) · [Captcha](tools/Captcha.md) | 이미지/수학 캡차 |
| `faker()` | `Cat\Faker` | [devtools.md](devtools.md) · [Faker](tools/Faker.md) | 테스트 데이터 (한국어/영어) |
| `cli()` | `Cat\Cli` | [devtools.md](devtools.md) · [Cli](tools/Cli.md) | CLI 프레임워크 |
| `event()` | `Cat\Event` | [devtools.md](devtools.md) · [Event](tools/Event.md) | 이벤트 디스패처 |
| `spider()` | `Cat\Spider` | [devtools.md](devtools.md) · [Spider](tools/Spider.md) | 웹 크롤러 |

### 운영 (4개)

| Shortcut | 클래스 | 문서 | 한줄 설명 |
| --- | --- | --- | --- |
| `sitemap()` | `Cat\Sitemap` | [ops.md](ops.md) · [Sitemap](tools/Sitemap.md) | XML 사이트맵 |
| `backup()` | `Cat\Backup` | [ops.md](ops.md) · [Backup](tools/Backup.md) | DB 백업/복원 |
| `swoole()` | `Cat\Swoole` | [ops.md](ops.md) · [Swoole](tools/Swoole.md) | 고성능 비동기 서버 |
| `telegram()` | `Cat\Telegram` | [ops.md](ops.md) · [Telegram](tools/Telegram.md) | 텔레그램 Bot API |

### 헬퍼 함수 (8개)

| 함수 | 반환 | 문서 | 한줄 설명 |
| --- | --- | --- | --- |
| `config()` | `mixed` | [catphp-core.md](catphp-core.md) | 설정 읽기/쓰기 (dot notation) |
| `cat()` | `object` | [catphp-core.md](catphp-core.md) | 범용 도구 로더 |
| `input()` | `mixed` | [catphp-core.md](catphp-core.md) | 요청 입력값 통합 읽기 |
| `redirect()` | `never` | [catphp-core.md](catphp-core.md) | URL 리디렉트 |
| `render()` | `string` | [router.md](router.md) | 뷰 템플릿 렌더링 |
| `e()` | `string` | [catphp-core.md](catphp-core.md) | HTML 이스케이프 |
| `dd()` / `dump()` | `never` / `void` | [devtools.md](devtools.md) | 변수 덤프 |
| `trans()` | `string` | [web.md](web.md) | 번역 (`geo()->t()` 위임) |

---

## 폴더 구조

```text
프로젝트/
├── catphp/                 ← 프레임워크 코어 + 도구 (56개 .php)
│   ├── catphp.php          ← 단일 코어 (autoloader + config + shortcuts)
│   ├── DB.php
│   ├── Router.php
│   └── ...
├── Public/                 ← 웹 진입점
│   ├── index.php           ← 웹 + API 라우트
│   ├── .htaccess           ← Apache rewrite
│   └── views/              ← PHP 뷰 템플릿
├── config/
│   └── app.php             ← 유저 설정
├── cli.php                 ← CLI 진입점
├── lang/                   ← 다국어 번역 파일
├── storage/                ← 런타임 데이터 (캐시, 로그, 세션)
│   ├── cache/
│   ├── logs/
│   ├── sessions/
│   └── backup/
└── .env                    ← 환경변수 (선택)
```

---

## 설계 원칙

### 빠른 속도

- 코어 1파일, `require` 1회 부팅
- 사용한 것만 로드 (autoloader + static 싱글턴)
- 이중 지연 로딩 (객체 생성 ≠ 리소스 연결)
- OPcache preload + JIT 최적화

### 사용 편리

- Shortcut 함수 66개 (`db()`, `json()`, `auth()` 등)
- 체이닝 API (`db()->table('x')->where('id', 1)->first()`)
- `cat()` 범용 로더로 동적 도구 로딩
- API 응답 통일 포맷 (`json()->ok()` / `json()->fail()`)

### 쉬운 학습

- 함수명 = 기능 (`db`, `cache`, `json`, `auth`)
- 반환 타입 명시로 IDE 자동완성 지원
- 친절한 에러 메시지 ("catphp/X.php 파일을 생성하세요")

### 보안

- `declare(strict_types=1)` 전 파일 필수
- PDO prepared statement 전용 (SQL injection 차단)
- `#[\SensitiveParameter]` 비밀번호/키 보호
- Sodium 암호화, Argon2id 해싱
- `hash_equals()` 타이밍 공격 방지

---

## CLI 명령어 요약

```bash
# DB
php cli.php migrate              # 마이그레이션 실행
php cli.php migrate:rollback     # 롤백
php cli.php migrate:status       # 상태
php cli.php migrate:create NAME  # 새 마이그레이션 생성
php cli.php migrate:fresh        # 전체 재실행
php cli.php db:tables            # 테이블 목록
php cli.php db:columns TABLE     # 컬럼 목록
php cli.php db:describe TABLE    # 테이블 상세
php cli.php db:preview TABLE     # 데이터 미리보기
php cli.php db:stats             # DB 통계
php cli.php db:backup            # DB 백업
php cli.php db:restore           # 복원
php cli.php db:backup:list       # 백업 목록
php cli.php db:backup:clean      # 오래된 백업 정리

# 큐
php cli.php queue:work           # 워커 시작
php cli.php queue:size           # 큐 크기
php cli.php queue:clear          # 큐 비우기
php cli.php queue:failed         # 실패 작업 목록
php cli.php queue:retry          # 실패 작업 재시도

# 스케줄
php cli.php schedule:run         # 스케줄 실행
php cli.php schedule:list        # 등록된 태스크 목록

# 사이트맵
php cli.php sitemap:generate     # 사이트맵 생성

# 방화벽
php cli.php firewall:ban IP      # IP 차단
php cli.php firewall:unban IP    # IP 해제

# Swoole 서버
php cli.php swoole:start         # 서버 시작
php cli.php swoole:stop          # 서버 중지
php cli.php swoole:reload        # 워커 리로드
php cli.php swoole:status        # 실행 상태
```
