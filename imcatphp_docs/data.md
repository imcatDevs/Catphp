# 데이터 처리 — Valid · Collection · Paginate · Cookie · Env

CatPHP의 데이터 검증/조작 계층. 입력 검증, 배열 체이닝, 페이지네이션, 쿠키, 환경변수를 제공한다.

| 도구 | Shortcut | 클래스 | 줄 수 |
| --- | --- | --- | --- |
| Valid | `valid()` | `Cat\Valid` | 263 |
| Collection | `collect()` | `Cat\Collection` | 641 |
| Paginate | `paginate()` | `Cat\Paginate` | 177 |
| Cookie | `cookie()` | `Cat\Cookie` | 99 |
| Env | `env()` | `Cat\Env` | 264 |

---

## 목차

1. [Valid — 입력 검증](#1-valid--입력-검증)
2. [Collection — 배열 체이닝](#2-collection--배열-체이닝)
3. [Paginate — 페이지네이션](#3-paginate--페이지네이션)
4. [Cookie — 쿠키 관리](#4-cookie--쿠키-관리)
5. [Env — 환경변수 관리](#5-env--환경변수-관리)

---

## 1. Valid — 입력 검증

체이닝 규칙 정의 + 내장 검증 규칙. 이뮤터블 (`clone`) 방식.

### Valid 사용법

```php
$v = valid()->rules([
    'name'  => 'required|string|min:2|max:50',
    'email' => 'required|email|unique:users,email',
    'age'   => 'nullable|integer|between:0,150',
])->check($data);

if ($v->fails()) {
    json()->fail('검증 실패', $v->errors());
}
```

### Valid 내장 규칙

| 규칙 | 설명 | 예시 |
| --- | --- | --- |
| `required` | 필수 (null/빈문자열 불가) | `required` |
| `nullable` | null/빈값 허용 (나머지 규칙 건너뜀) | `nullable\|email` |
| `string` | 문자열 타입 | `string` |
| `integer` | 정수 | `integer` |
| `numeric` | 숫자 | `numeric` |
| `array` | 배열 | `array` |
| `email` | 이메일 형식 | `email` |
| `url` | URL 형식 | `url` |
| `ip` | IP 주소 | `ip` |
| `date` | 날짜 (strtotime 가능) | `date` |
| `json` | JSON 문자열 | `json` |
| `alpha` | 알파벳만 | `alpha` |
| `alpha_num` | 알파벳+숫자 | `alpha_num` |
| `min:N` | 최소 길이/값 | `min:2` |
| `max:N` | 최대 길이/값 | `max:255` |
| `between:min,max` | 범위 | `between:1,100` |
| `size:N` | 정확한 크기 | `size:11` |
| `digits:N` | N자리 숫자 | `digits:6` |
| `in:a,b,c` | 목록 중 하나 | `in:active,pending` |
| `regex:pattern` | 정규식 | `regex:/^\d{3}-\d{4}$/` |
| `confirmed` | `{field}_confirmation` 일치 | `confirmed` |
| `same:field` | 다른 필드와 동일 | `same:password` |
| `different:field` | 다른 필드와 다름 | `different:old_password` |
| `unique:table,col` | DB 중복 검사 | `unique:users,email` |
| `date_format:fmt` | 날짜 포맷 | `date_format:Y-m-d` |
| `before:date` | 날짜 이전 | `before:2025-01-01` |
| `after:date` | 날짜 이후 | `after:today` |

### Valid 커스텀 규칙

```php
// 커스텀 규칙 등록 (static)
Valid::extend('phone_kr', function (string $field, mixed $value, array $params, array $data): ?string {
    if (!is_string($value) || !preg_match('/^01[016789]-?\d{3,4}-?\d{4}$/', $value)) {
        return "{$field}은(는) 유효한 한국 전화번호여야 합니다";
    }
    return null; // null = 통과
});

// 사용
valid()->rules(['phone' => 'required|phone_kr'])->check($data);
```

### Valid 에러 메시지

```php
$v->errors();
// [
//     'name'  => ['name은(는) 필수입니다'],
//     'email' => ['email은(는) 이미 사용 중입니다'],
// ]
```

한국어 에러 메시지가 기본 내장되어 있다.

---

## 2. Collection — 배열 체이닝

Laravel Collection 스타일의 배열 조작 유틸. `Countable`, `IteratorAggregate`, `JsonSerializable` 구현.

### Collection 생성

```php
$c = collect([1, 2, 3, 4, 5]);
$c = collect($users);  // DB 결과 배열
$c = new \Cat\Collection($items);
```

### Collection 변환

```php
// map — 각 요소 변환
collect([1, 2, 3])->map(fn($v) => $v * 2)->toArray();
// [2, 4, 6]

// filter — 조건 필터
collect([1, 0, 2, null, 3])->filter()->toArray();
// [1, 2, 3] (falsy 값 제거)

collect($users)->filter(fn($u) => $u['active'])->toArray();

// reject — filter 반대
collect([1, 2, 3, 4])->reject(fn($v) => $v > 2)->toArray();
// [1, 2]

// reduce — 누적 연산
collect([1, 2, 3])->reduce(fn($carry, $v) => $carry + $v, 0);
// 6

// flatten — 평탄화
collect([[1, 2], [3, [4]]])->flatten()->toArray();
// [1, 2, 3, [4]]

collect([[1, 2], [3, [4]]])->flatten(2)->toArray();
// [1, 2, 3, 4]

// flatMap
collect($users)->flatMap(fn($u) => $u['tags'])->unique()->toArray();
```

### Collection 추출

```php
// pluck — 특정 키 추출
collect($users)->pluck('name')->toArray();
// ['Alice', 'Bob', ...]

// pluck + 인덱스
collect($users)->pluck('name', 'id')->toArray();
// [1 => 'Alice', 2 => 'Bob']

// 점 표기법 지원
collect($users)->pluck('address.city')->toArray();

// only / except
collect(['a' => 1, 'b' => 2, 'c' => 3])->only(['a', 'c'])->toArray();
// ['a' => 1, 'c' => 3]

// first / last
collect($users)->first(fn($u) => $u['role'] === 'admin');
collect($users)->last();
collect($users)->nth(2);  // 3번째 요소 (0-indexed)
```

### Collection 조건 필터

```php
// where (키=값)
collect($users)->where('role', 'admin')->toArray();
collect($users)->where('age', '>=', 18)->toArray();

// whereNull / whereNotNull
collect($users)->whereNotNull('email')->toArray();

// whereIn
collect($users)->whereIn('status', ['active', 'pending'])->toArray();

// every / some / contains
collect([2, 4, 6])->every(fn($v) => $v % 2 === 0);  // true
collect([1, 2, 3])->some(fn($v) => $v > 2);           // true
collect([1, 2, 3])->contains(2);                        // true
```

### Collection 정렬 · 그룹

```php
// sort / sortDesc
collect([3, 1, 2])->sort()->values()->toArray();      // [1, 2, 3]
collect($users)->sortBy('name')->toArray();
collect($users)->sortByDesc('created_at')->toArray();

// groupBy — 키 기준 그룹화
collect($users)->groupBy('role')->toArray();
// ['admin' => [...], 'user' => [...]]

// chunk — N개씩 분할
collect(range(1, 10))->chunk(3)->toArray();
// [[1,2,3], [4,5,6], [7,8,9], [10]]

// reverse / slice / take / skip
collect([1, 2, 3])->reverse()->toArray();    // [3, 2, 1]
collect([1, 2, 3, 4])->slice(1, 2)->toArray(); // [2, 3]
collect([1, 2, 3, 4])->take(2)->toArray();    // [1, 2]
collect([1, 2, 3, 4])->skip(2)->toArray();    // [3, 4]
```

### Collection 집계

```php
collect([10, 20, 30])->sum();       // 60
collect($orders)->sum('amount');     // 키 지정
collect([10, 20, 30])->avg();        // 20.0
collect([10, 20, 30])->min();        // 10
collect([10, 20, 30])->max();        // 30
collect([1, 3, 2])->median();        // 2
collect([1, 2, 3, 4])->count();      // 4
```

### Collection 결합 · 유틸

```php
// merge / unique / diff / intersect
collect([1, 2])->merge([3, 4])->toArray();
collect([1, 1, 2, 2])->unique()->toArray();     // [1, 2]
collect([1, 2, 3])->diff([2, 3])->toArray();     // [1]
collect([1, 2, 3])->intersect([2, 3, 4])->toArray(); // [2, 3]

// combine — 키+값 결합
collect(['a', 'b'])->combine([1, 2])->toArray(); // ['a' => 1, 'b' => 2]

// each — 반복 (false 반환 시 중단)
collect($users)->each(function ($user) {
    sendEmail($user['email']);
});

// pipe / tap / when / unless
collect($items)
    ->when($isFiltered, fn($c) => $c->where('active', true))
    ->tap(fn($c) => logger()->info("Count: {$c->count()}"))
    ->toArray();

// 변환
$c->toArray();
$c->toJson();
$c->implode(', ', 'name');
$c->values();   // 0부터 재인덱싱
$c->keys();
$c->flip();
$c->isEmpty();
$c->isNotEmpty();
```

---

## 3. Paginate — 페이지네이션

DB 쿼리 연동, 웹 HTML 링크, API JSON 응답을 모두 지원.

### Paginate DB 연동

```php
// DB 쿼리에서 자동 페이지네이션
$pager = paginate()->fromQuery(
    db()->table('posts')->where('active', 1)->orderByDesc('id'),
    perPage: 20
);

// 웹: HTML 링크 출력
echo $pager->links('/posts?page={page}');

// API: JSON 응답
$arr = $pager->toArray();
json()->paginated($arr['data'], $arr['total'], $arr['page'], $arr['per_page']);
```

### Paginate 수동 설정

```php
$pager = paginate()
    ->page(3)           // 현재 페이지
    ->perPage(20)       // 페이지당 항목 수
    ->total(150)        // 전체 항목 수
    ->items($rows);     // 항목 배열

$offset = $pager->offset();       // 40
$last   = $pager->lastPage();     // 8
$pager->hasNext();                 // true
$pager->hasPrev();                 // true
```

### Paginate 메서드 요약

| 메서드 | 반환 타입 | 설명 |
| --- | --- | --- |
| `page(?int)` | `self` | 현재 페이지 (null이면 `input('page')`) |
| `perPage(int)` | `self` | 페이지당 항목 수 |
| `total(int)` | `self` | 전체 항목 수 |
| `items(array)` | `self` | 항목 배열 |
| `offset()` | `int` | SQL OFFSET 값 |
| `lastPage()` | `int` | 마지막 페이지 번호 |
| `hasNext()` | `bool` | 다음 페이지 존재 |
| `hasPrev()` | `bool` | 이전 페이지 존재 |
| `links(url, window)` | `string` | HTML 페이지 링크 |
| `fromQuery(DB, perPage)` | `self` | DB 쿼리 자동 페이지네이션 |
| `toArray()` | `array` | API용 배열 |

### Paginate links() HTML

윈도우 트렁케이션으로 페이지가 많아도 깔끔한 UI:

```text
페이지 적음: [1] [2] [3] [4] [5]
페이지 많음: [1] ... [5] [6] [7] ... [20]
```

---

## 4. Cookie — 쿠키 관리

Sodium 암호화 연동, Guard 살균 자동 적용.

### Cookie 설정

```php
'cookie' => [
    'encrypt'  => true,    // 암호화 여부 (기본 true)
    'samesite' => 'Lax',   // SameSite 속성
    'secure'   => false,   // HTTPS 전용
],
```

### Cookie 메서드

```php
// 쿠키 설정 (기본 24시간 TTL)
cookie()->set('user_pref', ['theme' => 'dark', 'lang' => 'ko']);
cookie()->set('token', 'abc123', 3600);  // 1시간

// 쿠키 읽기
$pref = cookie()->get('user_pref');
// ['theme' => 'dark', 'lang' => 'ko']

// 기본값
$theme = cookie()->get('missing', 'light');

// 존재 확인
cookie()->has('user_pref');  // true

// 삭제
cookie()->del('user_pref');
```

### Cookie 보안

| 항목 | 구현 |
| --- | --- |
| 암호화 | `encrypt()->seal()` / `open()` (Sodium) |
| XSS 방어 | `httpOnly: true` (JavaScript 접근 차단) |
| 살균 | `guard()->clean()` / `cleanArray()` 자동 적용 |
| SameSite | config 기반 (`Lax` 기본) |
| Secure | config 기반 (HTTPS 전용) |

---

## 5. Env — 환경변수 관리

`.env` 파일 파싱 + `getenv`/`putenv` 래퍼.

### Env Shortcut 이중 동작

```php
// 키 전달 → 값 반환
$host = env('DB_HOST', '127.0.0.1');

// 키 없이 → 인스턴스 반환
env()->load(__DIR__ . '/.env');
```

### Env .env 파일 형식

```bash
# 주석
APP_NAME=CatPHP
APP_DEBUG=true

# 따옴표
DB_HOST="127.0.0.1"
DB_PASS='my password'

# 이스케이프 (큰따옴표만)
GREETING="Hello\nWorld"

# 변수 참조
BASE_URL=https://${APP_HOST}

# export 접두사
export NODE_ENV=production

# 인라인 주석
APP_KEY=secret123 # 이것은 주석
```

### Env 메서드

```php
// 로드
env()->load(__DIR__ . '/.env');
env()->isLoaded();  // true

// 읽기 (타입 자동 캐스팅)
env('APP_DEBUG');    // true (boolean)
env('DB_PORT');      // '3306' (string)
env('NULL_VALUE');   // null ('null' → null)

// 설정
env()->set('APP_KEY', 'new-secret');

// 존재 확인
env()->has('DB_HOST');  // true

// 필수 변수 확인 (누락 시 RuntimeException)
env()->required(['DB_HOST', 'DB_NAME', 'APP_KEY']);

// 전체 변수
$all = env()->all();

// .env 파일에 쓰기 (추가/수정)
env()->write(__DIR__ . '/.env', 'APP_VERSION', '1.0.2');
```

### Env 타입 캐스팅

| .env 값 | PHP 타입 |
| --- | --- |
| `true`, `(true)` | `true` |
| `false`, `(false)` | `false` |
| `null`, `(null)` | `null` |
| `empty`, `(empty)` | `''` |
| 그 외 | `string` |

### Env 조회 우선순위

```text
1. 내부 캐시 ($vars)
2. $_ENV
3. getenv()
```

---

## 도구 간 연동

```text
Valid → DB (unique 규칙에서 db()->table() 사용)
Valid → Log (미등록 규칙 경고)
Paginate → DB (fromQuery에서 count/limit/offset)
Paginate → Json (toArray → paginated 응답)
Cookie → Encrypt (암호화/복호화)
Cookie → Guard (읽기 시 자동 살균)
Env → $_ENV, $_SERVER, putenv (동기화)
```
