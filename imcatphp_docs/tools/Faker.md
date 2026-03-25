# Faker — 테스트 데이터 생성

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Faker` |
| 파일 | `catphp/Faker.php` (888줄) |
| Shortcut | `faker()` |
| 싱글턴 | `getInstance()` |
| 의존 확장 | `ext-mbstring` |

---

## 설정

```php
// config/app.php
'faker' => [
    'locale' => 'ko',  // ko | en
],
```

---

## 메서드 레퍼런스

### 이름

| 메서드 | 반환 | 설명 |
| --- | --- | --- |
| `name()` | `string` | 전체 이름 |
| `firstName()` | `string` | 이름 |
| `lastName()` | `string` | 성 |

### 연락처

| 메서드 | 반환 | 설명 |
| --- | --- | --- |
| `email()` | `string` | 이메일 (gmail, naver 등) |
| `safeEmail()` | `string` | 테스트용 이메일 (@example.com) |
| `phone()` | `string` | 전화번호 |

### 주소

| 메서드 | 반환 | 설명 |
| --- | --- | --- |
| `address()` | `string` | 전체 주소 |
| `city()` | `string` | 도시명 |
| `zipCode()` | `string` | 우편번호 |

### 텍스트

| 메서드 | 반환 | 설명 |
| --- | --- | --- |
| `word()` | `string` | 단어 |
| `sentence(int $wordCount = 0)` | `string` | 문장 |
| `paragraph(int $sentenceCount = 0)` | `string` | 문단 |
| `title()` | `string` | 제목 |
| `slug()` | `string` | URL slug |
| `text(int $maxChars = 200)` | `string` | 지정 글자수 텍스트 |
| `lorem(int $sentences = 3)` | `string` | Lorem Ipsum |
| `sentences(int $count = 3)` | `array` | 복수 문장 배열 |
| `paragraphs(int $count = 3)` | `array` | 복수 문단 배열 |

### 회사/직업

| 메서드 | 반환 | 설명 |
| --- | --- | --- |
| `company()` | `string` | 회사명 |
| `jobTitle()` | `string` | 직함 |
| `department()` | `string` | 부서명 |

### 숫자/날짜

| 메서드 | 반환 | 설명 |
| --- | --- | --- |
| `number(int $min, int $max)` | `int` | 정수 |
| `float(float $min, float $max, int $decimals)` | `float` | 실수 |
| `boolean(int $chanceOfTrue = 50)` | `bool` | 불리언 |
| `date(string $format = 'Y-m-d')` | `string` | 날짜 (최근 3년) |
| `time(string $format = 'H:i:s')` | `string` | 시간 |
| `dateTime()` | `string` | 날짜시간 |
| `pastDate(int $daysBack = 365)` | `string` | 과거 날짜 |
| `futureDate(int $daysForward = 365)` | `string` | 미래 날짜 |

### 금액

| 메서드 | 반환 | 설명 |
| --- | --- | --- |
| `price(float $min, float $max)` | `string` | 가격 (소수점 2자리) |
| `koreanPrice(int $min, int $max)` | `string` | 한국 원화 (100단위 절사) |

### 인터넷

| 메서드 | 반환 | 설명 |
| --- | --- | --- |
| `username()` | `string` | 사용자명 |
| `url()` | `string` | URL |
| `domain()` | `string` | 도메인 |
| `ipv4()` | `string` | IPv4 |
| `ipv6()` | `string` | IPv6 |
| `macAddress()` | `string` | MAC 주소 |
| `imageUrl(int $w, int $h)` | `string` | 플레이스홀더 이미지 URL |
| `userAgent()` | `string` | User Agent |

### 식별자

| 메서드 | 반환 | 설명 |
| --- | --- | --- |
| `uuid()` | `string` | UUID v4 |
| `color()` | `string` | HEX 색상 |
| `rgbColor()` | `string` | RGB 색상 |
| `rgbaColor()` | `string` | RGBA 색상 |
| `colorName()` | `string` | 색상명 |
| `hash(string $algo = 'sha256')` | `string` | 해시 |
| `password(int $length = 12)` | `string` | 비밀번호 |
| `creditCard()` | `string` | 신용카드 번호 (Luhn 유효) |
| `bankAccount()` | `string` | 은행계좌번호 (한국) |

### 한국 특화

| 메서드 | 반환 | 설명 |
| --- | --- | --- |
| `rrn()` | `string` | 주민등록번호 (마스킹) |
| `businessNumber()` | `string` | 사업자등록번호 |
| `koreanPrice()` | `string` | 한국 원화 |
| `koreanCoordinates()` | `array` | 한국 좌표 |

### 파일/지리

| 메서드 | 반환 | 설명 |
| --- | --- | --- |
| `fileName()` | `string` | 파일명 |
| `fileExtension()` | `string` | 확장자 |
| `mimeType()` | `string` | MIME 타입 |
| `country()` | `string` | 국가명 |
| `latitude()` | `float` | 위도 |
| `longitude()` | `float` | 경도 |
| `coordinates()` | `array` | `{lat, lng}` |

### 패턴 기반

| 메서드 | 반환 | 설명 |
| --- | --- | --- |
| `numerify(string $pattern)` | `string` | `#` → 숫자 |
| `lexify(string $pattern)` | `string` | `?` → 문자 |
| `bothify(string $pattern)` | `string` | 숫자 + 문자 혼합 |

### 유틸리티

| 메서드 | 반환 | 설명 |
| --- | --- | --- |
| `randomElement(array $arr)` | `mixed` | 배열에서 랜덤 |
| `randomElements(array $arr, int $n)` | `array` | N개 랜덤 |
| `make(int $count, callable $cb)` | `array` | 대량 데이터 생성 |
| `unique(callable $gen, string $group)` | `mixed` | 유니크 값 생성 |
| `resetUnique(?string $group)` | `self` | 유니크 저장소 초기화 |
| `json(int $fields = 5)` | `string` | 랜덤 JSON |
| `userProfile()` | `array` | 유저 프로필 배열 |
| `locale(string $locale)` | `self` | 로케일 변경 |

---

## 사용 예제

### 기본 데이터 생성

```php
faker()->name();          // '김민준'
faker()->email();         // 'a8xk2f@gmail.com'
faker()->phone();         // '010-1234-5678'
faker()->address();       // '서울특별시 강남구 테헤란로 123번길 45'
faker()->sentence();      // '사람 시간 교육 문화 경제 기술 환경.'
```

### 대량 데이터 생성 (Seeder)

```php
$users = faker()->make(100, fn($f, $i) => [
    'name'       => $f->name(),
    'email'      => $f->safeEmail(),
    'phone'      => $f->phone(),
    'company'    => $f->company(),
    'created_at' => $f->pastDate(),
]);

foreach ($users as $user) {
    db()->table('users')->insert($user);
}
```

### 유니크 값

```php
$emails = [];
for ($i = 0; $i < 50; $i++) {
    $emails[] = faker()->unique(fn() => faker()->email(), 'emails');
}
faker()->resetUnique('emails');
```

### 패턴 기반 생성

```php
faker()->numerify('###-####-####');  // '010-1234-5678'
faker()->lexify('????');             // 'abcd'
faker()->bothify('??-###');          // 'ab-123'
```

### 한국 특화 데이터

```php
faker()->rrn();              // '901225-1******'
faker()->businessNumber();   // '123-45-67890'
faker()->koreanPrice(1000, 50000);  // '32,400원'
faker()->koreanCoordinates();       // {lat: 37.5, lng: 127.0}
```

### 영문 로케일

```php
faker()->locale('en');
faker()->name();      // 'John Smith'
faker()->address();   // '1234 Main St, New York'
```

---

## 내부 동작

### random_int() 기반

모든 랜덤 생성이 `random_int()` 사용 — 암호학적으로 안전한 난수.

### 로케일별 데이터셋

```text
ko: 한국 성/이름, 서울 지역 주소, 한국어 단어
en: 영미 성/이름, 미국 도시, 영어 단어
```

### Luhn 체크섬 (신용카드)

```text
creditCard()
├─ Visa 접두사 '4' + 14자리 랜덤
├─ Luhn 알고리즘으로 체크 디짓 계산
└─ XXXX-XXXX-XXXX-XXXX 형식
```

### unique() 중복 방지

```text
unique($generator, 'group')
├─ $generator() 호출
├─ md5(serialize($value))로 해시
├─ uniqueSets[$group]에 존재 → 재시도
├─ 최대 1000회 시도
└─ 초과 시 RuntimeException
```

---

## 주의사항

1. **테스트 전용**: 프로덕션 환경에서 사용 금지. 개발/테스트/시딩 목적.
2. **locale() 전역 변경**: 싱글턴의 로케일을 변경하므로 이후 모든 호출에 영향.
3. **주민등록번호**: 마스킹 형식 (뒷 6자리 `*`). 실제 유효한 번호가 아님.
4. **creditCard()**: Luhn 유효하지만 실제 카드 번호가 아님. 테스트 전용.
5. **make() 메모리**: 대량 생성 시 결과가 메모리에 누적. 10만 건 이상은 배치 처리 권장.

---

## 연관 도구

- [DB](DB.md) — 시딩 데이터 삽입
- [Valid](Valid.md) — 생성 데이터 검증 테스트
