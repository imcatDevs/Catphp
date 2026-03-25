# 개발 도구 — Debug · Captcha · Faker · Cli · Event · Spider

CatPHP의 개발·테스트 유틸리티 계층. 디버깅, 캡차, 테스트 데이터 생성, CLI, 이벤트, 웹 스크래핑을 제공한다.

| 도구 | Shortcut | 클래스 | 줄 수 |
| --- | --- | --- | --- |
| Debug | `debug()` | `Cat\Debug` | 346 |
| Captcha | `captcha()` | `Cat\Captcha` | 253 |
| Faker | `faker()` | `Cat\Faker` | 888 |
| Cli | `cli()` | `Cat\Cli` | 248 |
| Event | `event()` | `Cat\Event` | 121 |
| Spider | `spider()` | `Cat\Spider` | 698 |

---

## 목차

1. [Debug — 디버그 유틸](#1-debug--디버그-유틸)
2. [Captcha — 캡차 생성·검증](#2-captcha--캡차-생성검증)
3. [Faker — 테스트 데이터 생성](#3-faker--테스트-데이터-생성)
4. [Cli — CLI 프레임워크](#4-cli--cli-프레임워크)
5. [Event — 이벤트 디스패처](#5-event--이벤트-디스패처)
6. [Spider — 웹 스크래핑](#6-spider--웹-스크래핑)

---

## 1. Debug — 디버그 유틸

변수 덤프, 타이머, 메모리 측정, 쿼리 로그, HTML 디버그 바.

### Debug 변수 덤프

```php
// 예쁜 출력 (계속 실행)
debug()->dump($var);
debug()->dump($a, $b, $c);   // 여러 변수

// 출력 후 종료 (호출 위치 표시)
debug()->dd($var);
// --- dd() at /path/to/file.php:42 ---
```

- **CLI**: `var_dump` 출력
- **웹**: Catppuccin 테마 styled `<pre>` 출력 (타입별 색상 구분)

### Debug 타이머

```php
// 구간 측정
debug()->timer('db');
// ... DB 쿼리 실행 ...
$ms = debug()->timerEnd('db');  // 경과 시간 (ms)

// 앱 시작부터 경과 시간
$total = debug()->elapsed();    // ms

// 콜백 실행 시간 측정
$result = debug()->measure('작업명', function () {
    return heavyOperation();
});
```

### Debug 메모리

```php
debug()->memory();       // '12.5 MB' (현재 사용량)
debug()->peakMemory();   // '18.2 MB' (피크)
debug()->memoryUsage();  // 13107200 (바이트)
```

### Debug 로그

```php
// 로그 기록
debug()->log('query', 'SELECT * FROM users', 12.3);
debug()->log('timer', 'db: 45.2ms');
debug()->log('error', '연결 실패');

// 로그 조회
debug()->getLogs();     // 전체 로그 배열
debug()->logCount();    // 로그 개수
debug()->clearLogs();   // 초기화
```

로그 엔트리 구조: `{type, message, time, memory}`

### Debug 스택 트레이스

```php
debug()->trace();       // 호출 스택 출력 (최대 10단계)
debug()->trace(5);      // 5단계까지
```

### Debug 디버그 바

```php
// 웹 페이지 하단에 삽입
echo debug()->bar();
```

- **프로덕션 환경** (`app.debug = false`): 빈 문자열 반환 (정보 노출 방지)
- **웹**: 고정 하단 바 — 경과 시간, 메모리, 피크, 로그 개수, PHP 버전 표시. 클릭 시 상세 로그 토글
- **CLI**: 텍스트 형식 요약 출력
- **로그 색상**: query(파란), timer(초록), error(빨강), warning(주황)

---

## 2. Captcha — 캡차 생성·검증

GD 이미지 캡차 + 수학 캡차. 세션 기반 정답 저장.

### Captcha 설정

```php
'captcha' => [
    'width'       => 150,
    'height'      => 50,
    'length'      => 5,
    'charset'     => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ',  // 혼동 문자 제외 (0/O, 1/I/L)
    'session_key' => '_captcha',
],
```

### Captcha 이미지 캡차

```php
// 이미지 직접 출력 (라우트 핸들러에서)
captcha()->image();   // Content-Type: image/png + exit

// base64 data URI (img src에 삽입)
$src = captcha()->src();
echo "<img src=\"{$src}\" alt=\"captcha\">";

// HTML img 태그 (리프레시 지원)
echo captcha()->html('captcha-img', '/api/captcha/refresh');

// 크기/길이 변경 (이뮤터블)
captcha()->size(200, 60)->length(6)->image();
```

### Captcha 수학 캡차

```php
$result = captcha()->math();
// ['question' => '12 + 8 = ?', 'html' => '<span>...</span>']

echo $result['html'];  // 브라우저에 수학 문제 표시
```

연산: `+`, `-`, `×` (빼기는 음수 방지)

### Captcha 검증

```php
$valid = captcha()->verify($userInput);  // true/false
```

- **1회용**: 검증 후 세션에서 정답 자동 삭제
- **타이밍 공격 방어**: `hash_equals()` 사용
- **대소문자 무시**: 입력값 소문자 변환 비교

### Captcha 이미지 생성 내부

- 랜덤 배경색 (밝은 계열)
- 노이즈 라인 6개 + 노이즈 점 100개
- 글자별 랜덤 위치 오프셋 (자동 OCR 방해)
- GD 내장 폰트 사용 (외부 폰트 불필요)

---

## 3. Faker — 테스트 데이터 생성

한국어/영어 지원 가짜 데이터 생성기. 외부 의존성 없음.

### Faker 로케일

```php
// 기본값: config('faker.locale', 'ko')
faker()->locale('en');  // 영어로 전환 (싱글턴 전역)
```

### Faker 이름·연락처

```php
faker()->name();        // '김민준' (ko) / 'Smith James' (en)
faker()->firstName();   // '민준'
faker()->lastName();    // '김'
faker()->email();       // 'abc123@gmail.com'
faker()->safeEmail();   // 'abcd1234@example.com' (테스트 안전)
faker()->phone();       // '010-1234-5678' (ko) / '+1-123-456-7890' (en)
```

### Faker 주소

```php
faker()->address();     // '서울특별시 강남구 테헤란로 123번길 45'
faker()->city();        // '서울특별시'
faker()->zipCode();     // '12345' (ko) / '12345-6789' (en)
```

### Faker 텍스트

```php
faker()->word();                  // '사회'
faker()->sentence();              // '기술 환경 건강 생활 정보 세계.'
faker()->sentence(5);             // 5단어 문장
faker()->paragraph();             // 3~6문장 문단
faker()->sentences(5);            // 문장 배열 (5개)
faker()->paragraphs(3);           // 문단 배열 (3개)
faker()->text(200);               // 지정 글자수 텍스트
faker()->title();                 // 제목 (3~7단어)
faker()->slug();                  // 'time-year-people' (영어 기반)
faker()->lorem(3);                // Lorem Ipsum 3문장
```

### Faker 회사·직업

```php
faker()->company();       // '(주) 미래소프트'
faker()->jobTitle();      // '백엔드 개발자'
faker()->department();    // '개발팀'
```

### Faker 숫자·날짜

```php
faker()->number(1, 100);         // 42
faker()->float(0.0, 100.0, 2);   // 73.28
faker()->boolean();               // true (50% 확률)
faker()->boolean(80);             // true (80% 확률)

faker()->date();                  // '2024-03-15'
faker()->time();                  // '14:32:07'
faker()->dateTime();              // '2024-03-15 14:32:07'
faker()->pastDate(30);            // 30일 이내 과거
faker()->futureDate(90);          // 90일 이내 미래
```

### Faker 금액

```php
faker()->price(10.0, 999.99);    // '123.45'
faker()->koreanPrice(1000, 50000); // '32,400원' (100단위 절사)
```

### Faker 인터넷

```php
faker()->username();       // 'time123'
faker()->url();            // 'https://example.com/time-year-people'
faker()->domain();         // 'abc123def.com'
faker()->ipv4();           // '192.168.1.42'
faker()->ipv6();           // '2001:0db8:85a3:...'
faker()->macAddress();     // 'a1:b2:c3:d4:e5:f6'
faker()->imageUrl(400, 300); // 'https://placehold.co/400x300'
faker()->userAgent();      // 'Mozilla/5.0 (Windows NT 10.0; ...) ...'
```

### Faker 식별자

```php
faker()->uuid();           // '550e8400-e29b-41d4-a716-446655440000'
faker()->color();          // '#a3f2e1'
faker()->rgbColor();       // 'rgb(123, 45, 200)'
faker()->rgbaColor();      // 'rgba(123, 45, 200, 0.75)'
faker()->colorName();      // 'teal'
faker()->hash();           // SHA-256 해시
faker()->password(16);     // 'aB3$kL9!mN2@pQ7&'
faker()->creditCard();     // '4123-4567-8901-2345' (Luhn 유효)
faker()->bankAccount();    // 'KB국민 123-45-6789-012'
```

### Faker 한국 특화

```php
faker()->rrn();               // '850315-1******' (마스킹)
faker()->businessNumber();    // '123-45-67890'
faker()->koreanCoordinates(); // ['lat' => 37.5665, 'lng' => 126.9780]
```

### Faker 파일·지리

```php
faker()->fileName();       // 'abc123def.pdf'
faker()->fileExtension();  // 'jpg'
faker()->mimeType();       // 'image/jpeg'
faker()->country();        // '대한민국'
faker()->latitude();       // 37.123456
faker()->longitude();      // 126.987654
faker()->coordinates();    // ['lat' => ..., 'lng' => ...]
```

### Faker 패턴 기반

```php
faker()->numerify('###-####-####');  // '010-1234-5678'
faker()->lexify('????');             // 'abcd'
faker()->bothify('??-###');          // 'ab-123'
```

### Faker 구조화 데이터

```php
// 랜덤 JSON 문자열
faker()->json(5);

// 유저 프로필 배열
faker()->userProfile();
// ['name', 'email', 'phone', 'address', 'company', 'job_title', 'department', 'avatar', 'created_at']
```

### Faker 대량 생성

```php
// 10명 유저 데이터
$users = faker()->make(10, fn($f, $i) => [
    'id'    => $i + 1,
    'name'  => $f->name(),
    'email' => $f->safeEmail(),
]);

// 유니크 값 (중복 불가)
$email = faker()->unique(fn() => faker()->email(), 'emails');

// 유니크 저장소 초기화
faker()->resetUnique('emails');
faker()->resetUnique();  // 전체 초기화
```

### Faker 배열 유틸

```php
faker()->randomElement(['a', 'b', 'c']);      // 'b'
faker()->randomElements(['a', 'b', 'c'], 2);  // ['a', 'c']
```

---

## 4. Cli — CLI 프레임워크

명령어 등록, 인자/옵션 파싱, 사용자 입력, 출력 헬퍼.

### Cli 명령어 등록

```php
// cli.php
cli()->command('greet', '인사 명령어', function () {
    cli()->success('안녕하세요!');
});

cli()->command('user:create', '유저 생성', function () {
    $name = cli()->prompt('이름');
    $email = cli()->prompt('이메일');
    cli()->success("유저 생성: {$name} <{$email}>");
});

// 명령어 그룹
cli()->group('cache', function (string $prefix) {
    cli()->command("{$prefix}:clear", '캐시 삭제', fn() => cache()->clear());
    cli()->command("{$prefix}:stats", '캐시 통계', fn() => cli()->info('...'));
});

// 실행
cli()->run();
```

```bash
php cli.php greet
php cli.php user:create
php cli.php help             # 전체 도움말
php cli.php help user:create # 명령어 도움말
```

### Cli 인자·옵션

```php
// php cli.php migrate --seed --step=5 users
cli()->arg(0);                     // 'users' (위치 인자)
cli()->option('seed');             // true (플래그)
cli()->option('step');             // '5' (값)
cli()->option('verbose', false);   // false (기본값)
```

### Cli 사용자 입력

```php
// 확인 (y/n)
if (cli()->confirm('진행할까요?', true)) { ... }  // Enter = Y

// 텍스트 입력
$name = cli()->prompt('프로젝트 이름', 'my-app');

// 선택지
$env = cli()->choice('환경 선택', ['local', 'staging', 'production']);
```

### Cli 출력 헬퍼

```php
cli()->info('정보 메시지');      // 파란색
cli()->success('성공!');         // 초록색 ✓
cli()->warn('주의');             // 노란색 ⚠
cli()->error('오류 발생');       // 빨간색 ✗

cli()->newLine(2);               // 빈 줄 2개
cli()->hr();                     // ────── 구분선
cli()->hr('=', 40);              // ======== 구분선 (40자)
```

### Cli 테이블

```php
cli()->table(
    ['이름', '나이', '도시'],
    [
        ['김민준', '25', '서울'],
        ['이서연', '30', '부산'],
    ]
);
// +--------+------+------+
// | 이름   | 나이 | 도시 |
// +--------+------+------+
// | 김민준 | 25   | 서울 |
// | 이서연 | 30   | 부산 |
// +--------+------+------+
```

- `mb_strwidth` 기반 유니코드 너비 계산 (한글 2칸 반영)

### Cli 프로그레스 바

```php
$total = 100;
for ($i = 0; $i <= $total; $i++) {
    cli()->progress($i, $total);
    // [████████████████░░░░░░░░░░░░░░░░░░░░░░░░] 40% (40/100)
}
```

---

## 5. Event — 이벤트 디스패처

이벤트 등록/발산, 우선순위, 전파 중단.

### Event 리스너 등록

```php
// 일반 리스너
$id = event()->on('user.created', function (array $user) {
    mailer()->to($user['email'])->subject('환영합니다')->body('...')->send();
});

// 우선순위 (높을수록 먼저 실행)
event()->on('user.created', fn($u) => logger()->info("가입: {$u['name']}"), priority: 10);
event()->on('user.created', fn($u) => cache()->forget('user_count'), priority: 5);

// 일회성 리스너 (1번 실행 후 자동 제거)
event()->once('app.booted', function () {
    logger()->info('앱 시작');
});
```

### Event 이벤트 발산

```php
// 이벤트 발생 (등록된 리스너 순차 실행)
event()->emit('user.created', ['name' => '홍길동', 'email' => 'hong@example.com']);

// 여러 인자
event()->emit('order.placed', $order, $user);
```

### Event 전파 중단

```php
event()->on('user.login', function (array $user) {
    if ($user['banned']) {
        logger()->warn("차단 유저 로그인 시도: {$user['name']}");
        return false;  // 이후 리스너 실행 중단
    }
});

event()->on('user.login', function (array $user) {
    // banned 유저일 때는 실행되지 않음
    session()->set('user', $user);
});
```

### Event 리스너 관리

```php
// 리스너 존재 확인
event()->hasListeners('user.created');  // true

// 특정 리스너 제거 (등록 시 반환된 ID 사용)
event()->off('user.created', $id);

// 이벤트의 모든 리스너 제거
event()->off('user.created');
```

### Event 내부 동작

- **우선순위 정렬**: 지연 정렬 (dirty 플래그) — `emit()` 시 필요할 때만 정렬
- **once 리스너**: 실행 후 역순 인덱스 제거 (배열 인덱스 충돌 방지)
- **반환값**: `false` 반환 시 전파 중단, 그 외 값은 무시

---

## 6. Spider — 웹 스크래핑

토큰/정규식 기반 패턴 매칭으로 웹 페이지에서 구조화된 데이터 추출. Http 도구 활용.

### Spider 설정

```php
'spider' => [
    'user_agent' => 'CatPHP Spider/1.0',
    'timeout'    => 30,
],
```

### Spider 토큰 패턴 파싱

```php
// 시작/끝 토큰 사이 텍스트 추출
$items = spider()
    ->pattern('title', '<h2 class="name">', '</h2>')
    ->pattern('price', '<span class="price">', '</span>', ['$', ','])  // '$', ',' 제거
    ->startAt('<div class="product-list">')  // 파싱 시작점
    ->parse('https://example.com/products');
// [['title' => '상품A', 'price' => '29900'], ['title' => '상품B', 'price' => '15000'], ...]
```

### Spider 정규식 패턴

```php
$items = spider()
    ->regex('email', '/[\w.+-]+@[\w-]+\.[\w.]+/')
    ->regex('phone', '/\d{3}-\d{4}-\d{4}/', 0)
    ->parse('https://example.com/contacts');
```

### Spider 혼합 패턴

토큰 + 정규식 동시 사용 가능. 행 수가 적은 쪽 기준으로 병합.

### Spider 페이지네이션

```php
// 10페이지까지 자동 순회
$items = spider()
    ->pattern('title', '<h2>', '</h2>')
    ->paginate('page', 1, 10)   // ?page=1, ?page=2, ...
    ->delay(2)                  // 페이지 간 2초 대기
    ->parse('https://example.com/list');

// 콜백 모드 (메모리 절약 — 페이지마다 즉시 처리)
spider()
    ->pattern('title', '<h2>', '</h2>')
    ->paginate('page', 1, null)  // 데이터 소진까지
    ->each(function (array $pageItems, int $pageNo) {
        db()->table('items')->insert($pageItems);
    })
    ->parse('https://example.com/list');
```

### Spider HTTP 옵션

```php
spider()
    ->userAgent('Mozilla/5.0 ...')
    ->referer('https://example.com')
    ->cookie('session', 'abc123')
    ->header('Authorization', 'Bearer token')
    ->timeout(60)
    ->encoding('EUC-KR', 'UTF-8')   // 인코딩 변환
    ->sanitize(false)                // Guard 살균 비활성화
    ->parse('https://example.com');
```

### Spider 유틸리티

```php
// HTTP 요청만 (파싱 없이)
$html = spider()->fetch('https://example.com');

// 문자열에서 직접 파싱 (HTTP 요청 없이)
$items = spider()
    ->pattern('title', '<h2>', '</h2>')
    ->parseContent($htmlString);

// 단일 값 빠른 추출
$title = spider()->find($html, '<title>', '</title>');

// 응답 정보
$s = spider()->pattern('title', '<h2>', '</h2>');
$s->parse('https://example.com');
$s->status();            // 200
$s->responseHeaders();   // 원본 헤더 문자열
$s->body();              // 응답 본문
```

### Spider 내부 동작

- **이뮤터블**: 모든 설정 메서드가 `clone` 사용
- **토큰 추출**: 순차 오프셋 이동 — 필드 순서대로 시작/끝 토큰 매칭 반복
- **정규식 추출**: `preg_match_all` 기반 전체 매칭
- **혼합 모드**: 토큰 결과 + 정규식 결과 병합 (짧은 쪽 기준)
- **안전장치**: 최대 반복 100,000회 (무한 루프 방지)
- **Guard 연동**: 기본 살균 활성화 (`guard()->clean()` / `guard()->cleanArray()`)
- **Http 연동**: 내부적으로 `http()` 도구 사용 (cURL 기반)

### Spider 고급 기능

```php
// 건너뛰기 토큰 (중간에 동일 패턴이 있을 때)
spider()
    ->pattern('title', '<h2>', '</h2>')
    ->skipAfter('title', '</div>')  // title 추출 후 </div>까지 건너뛰기
    ->parse($url);
```

---

## 도구 간 연동

```text
Debug → (독립) PHP 내장 함수
Captcha → Session (정답 저장/검증)
Faker → (독립) random_int 기반
Cli → (독립) $argv 파싱
Event → (독립) 콜백 관리
Spider → Http (HTTP 요청) + Guard (XSS 살균)
```
