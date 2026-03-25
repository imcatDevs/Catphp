<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Faker — 테스트 데이터 생성
 *
 * 한국어/영어 지원 가짜 데이터 생성기.
 *
 * 사용법:
 *   faker()->name();
 *   faker()->email();
 *   faker()->phone();
 *   faker()->address();
 *   faker()->sentence();
 *   faker()->paragraph();
 *   faker()->number(1, 100);
 *   faker()->date();
 *   faker()->boolean();
 *   faker()->uuid();
 *   faker()->make(10, fn($f, $i) => ['name' => $f->name(), 'email' => $f->email()]);
 *   faker()->company();
 *   faker()->jobTitle();
 *   faker()->username();
 *   faker()->creditCard();
 *   faker()->price(1000, 50000);
 *   faker()->imageUrl(400, 300);
 *   faker()->country();
 *   faker()->coordinates();
 *   faker()->numerify('###-####-####');
 */
final class Faker
{
    private static ?self $instance = null;

    private string $locale;

    /** @var array<string, list<string>> */
    private array $data = [];

    /** @var array<string, array<string, true>> unique() 중복 검사 저장소 */
    private array $uniqueSets = [];

    private function __construct()
    {
        $this->locale = (string) \config('faker.locale', 'ko');
        $this->loadData();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /** 로케일 변경 (싱글턴 전역 상태 변경 — 이후 모든 faker() 호출에 영향) */
    public function locale(string $locale): self
    {
        $this->locale = $locale;
        $this->loadData();
        return $this;
    }

    // ── 이름 ──

    /** 전체 이름 */
    public function name(): string
    {
        $sep = $this->locale === 'ko' ? '' : ' ';
        return $this->lastName() . $sep . $this->firstName();
    }

    /** 성 */
    public function lastName(): string
    {
        return $this->pick('lastNames');
    }

    /** 이름 */
    public function firstName(): string
    {
        return $this->pick('firstNames');
    }

    // ── 연락처 ──

    /** 이메일 */
    public function email(): string
    {
        $domains = ['gmail.com', 'naver.com', 'daum.net', 'kakao.com', 'outlook.com', 'yahoo.com'];
        $user = strtolower($this->alphaNum(random_int(5, 10)));
        return $user . '@' . $domains[array_rand($domains)];
    }

    /** 안전한 이메일 (테스트용) */
    public function safeEmail(): string
    {
        return strtolower($this->alphaNum(8)) . '@example.com';
    }

    /** 전화번호 */
    public function phone(): string
    {
        if ($this->locale === 'ko') {
            $prefixes = ['010', '011', '016', '017', '018', '019'];
            return $prefixes[array_rand($prefixes)] . '-' . $this->digits(4) . '-' . $this->digits(4);
        }
        return '+1-' . $this->digits(3) . '-' . $this->digits(3) . '-' . $this->digits(4);
    }

    // ── 주소 ──

    /** 주소 */
    public function address(): string
    {
        if ($this->locale === 'ko') {
            return $this->pick('cities') . ' ' . $this->pick('districts') . ' '
                 . $this->pick('streets') . ' ' . random_int(1, 300) . '번길 ' . random_int(1, 50);
        }
        return random_int(100, 9999) . ' ' . $this->pick('streets') . ' '
             . $this->pick('streetSuffixes') . ', ' . $this->pick('cities');
    }

    /** 도시 */
    public function city(): string
    {
        return $this->pick('cities');
    }

    /** 우편번호 */
    public function zipCode(): string
    {
        if ($this->locale === 'ko') {
            return $this->digits(5);
        }
        return $this->digits(5) . '-' . $this->digits(4);
    }

    // ── 텍스트 ──

    /** 단어 */
    public function word(): string
    {
        return $this->pick('words');
    }

    /**
     * 문장
     *
     * @param int $wordCount 단어 수
     */
    public function sentence(int $wordCount = 0): string
    {
        $count = $wordCount > 0 ? $wordCount : random_int(5, 12);
        $words = [];
        for ($i = 0; $i < $count; $i++) {
            $words[] = $this->word();
        }
        $sentence = implode(' ', $words);
        return mb_strtoupper(mb_substr($sentence, 0, 1)) . mb_substr($sentence, 1) . '.';
    }

    /**
     * 문단
     *
     * @param int $sentenceCount 문장 수
     */
    public function paragraph(int $sentenceCount = 0): string
    {
        $count = $sentenceCount > 0 ? $sentenceCount : random_int(3, 6);
        $sentences = [];
        for ($i = 0; $i < $count; $i++) {
            $sentences[] = $this->sentence();
        }
        return implode(' ', $sentences);
    }

    /** 제목 */
    public function title(): string
    {
        return $this->sentence(random_int(3, 7));
    }

    /** slug */
    public function slug(): string
    {
        $words = [];
        for ($i = 0; $i < random_int(2, 5); $i++) {
            $words[] = strtolower($this->pick('enWords'));
        }
        return implode('-', $words);
    }

    // ── 회사/직업 ──

    /** 회사명 */
    public function company(): string
    {
        if ($this->locale === 'ko') {
            $prefixes = ['주식회사', '(주)', ''];
            $names = [
                '한국기술', '미래소프트', '디지털라인', '테크비전', '스마트웨',
                '클라우드넷', '데이터플러스', '와이즈랜드', '네오시스', '인프라텍',
                '그린에너지', '보안연구소', '로지스택', '바이오메드', '엠아이테크',
            ];
            return trim($prefixes[array_rand($prefixes)] . ' ' . $names[array_rand($names)]);
        }

        $names = [
            'Acme', 'Global', 'Tech', 'First', 'Prime', 'Nova', 'Apex', 'Core',
            'Peak', 'Next', 'Blue', 'Iron', 'Silver', 'Gold', 'Bright',
        ];
        $suffixes = [
            'Corp', 'Inc', 'LLC', 'Ltd', 'Group', 'Solutions', 'Systems',
            'Technologies', 'Industries', 'Enterprises', 'Services',
        ];
        return $names[array_rand($names)] . ' ' . $suffixes[array_rand($suffixes)];
    }

    /** 직업/직함 */
    public function jobTitle(): string
    {
        if ($this->locale === 'ko') {
            $titles = [
                '소프트웨어 개발자', '프론트엔드 개발자', '백엔드 개발자', '풀스택 개발자',
                'UI/UX 디자이너', '프로덕트 매니저', '프로젝트 매니저', '데이터 분석가',
                '시스템 엔지니어', 'DevOps 엔지니어', '보안 전문가', '마케팅 매니저',
                'CEO', 'CTO', 'CFO', '경영지원팀장', '영업부장', '인사담당자', '회계사',
                '컬설턴트', '연구원', '기획자', '교수', '의사', '간호사', '디자이너',
            ];
            return $titles[array_rand($titles)];
        }

        $titles = [
            'Software Engineer', 'Frontend Developer', 'Backend Developer', 'Full Stack Developer',
            'UI/UX Designer', 'Product Manager', 'Project Manager', 'Data Analyst',
            'System Administrator', 'DevOps Engineer', 'Security Specialist', 'Marketing Manager',
            'CEO', 'CTO', 'CFO', 'Sales Director', 'HR Manager', 'Accountant',
            'Consultant', 'Researcher', 'Architect', 'Professor', 'Doctor', 'Nurse',
        ];
        return $titles[array_rand($titles)];
    }

    /** 부서명 */
    public function department(): string
    {
        if ($this->locale === 'ko') {
            $depts = [
                '개발팀', '기획팀', '마케팅팀', '영업팀', '인사팀', '경영지원팀',
                '디자인팀', '데이터팀', '백엔드팀', '프론트엔드팀', '인프라팀',
                'QA팀', '보안팀', '고객지원팀', '연구소', '법무팀', '재무팀',
            ];
            return $depts[array_rand($depts)];
        }

        $depts = [
            'Engineering', 'Marketing', 'Sales', 'HR', 'Finance', 'Operations',
            'Design', 'Data', 'QA', 'Security', 'Support', 'Legal', 'Research',
        ];
        return $depts[array_rand($depts)];
    }

    // ── 숫자 ──

    /** 정수 */
    public function number(int $min = 0, int $max = PHP_INT_MAX): int
    {
        return random_int($min, $max);
    }

    /** 실수 */
    public function float(float $min = 0.0, float $max = 1.0, int $decimals = 2): float
    {
        return round($min + random_int(0, PHP_INT_MAX) / PHP_INT_MAX * ($max - $min), $decimals);
    }

    /** 불리언 */
    public function boolean(int $chanceOfTrue = 50): bool
    {
        return random_int(1, 100) <= $chanceOfTrue;
    }

    // ── 날짜/시간 ──

    /** 날짜 (Y-m-d) */
    public function date(string $format = 'Y-m-d', ?string $max = null): string
    {
        $maxTs = $max ? (strtotime($max) ?: time()) : time();
        $minTs = $maxTs - (365 * 24 * 3600 * 3); // 3년 전부터
        $ts = random_int($minTs, $maxTs);
        return date($format, $ts);
    }

    /** 시간 (H:i:s) */
    public function time(string $format = 'H:i:s'): string
    {
        return date($format, random_int(0, 86399));
    }

    /** 날짜시간 */
    public function dateTime(string $format = 'Y-m-d H:i:s'): string
    {
        return $this->date($format);
    }

    /** 과거 날짜 */
    public function pastDate(int $daysBack = 365): string
    {
        $ts = time() - random_int(1, $daysBack * 86400);
        return date('Y-m-d', $ts);
    }

    /** 미래 날짜 */
    public function futureDate(int $daysForward = 365): string
    {
        $ts = time() + random_int(1, $daysForward * 86400);
        return date('Y-m-d', $ts);
    }

    // ── 금액 ──

    /** 가격 (소수점 2자리) */
    public function price(float $min = 1.0, float $max = 999.99): string
    {
        return number_format($this->float($min, $max, 2), 2);
    }

    /** 한국 원화 가격 (100단위 절사) */
    public function koreanPrice(int $min = 1000, int $max = 1000000): string
    {
        $price = (int) (random_int($min, $max) / 100) * 100;
        return number_format($price) . '원';
    }

    // ── 인터넷 ──

    /** 사용자명 */
    public function username(): string
    {
        $styles = [
            fn() => strtolower($this->pick('enWords')) . random_int(1, 999),
            fn() => $this->alphaNum(random_int(4, 8)) . '_' . $this->digits(2),
            fn() => strtolower($this->pick('enWords')) . '.' . $this->alphaNum(4),
        ];
        return $styles[array_rand($styles)]();
    }

    /** URL */
    public function url(): string
    {
        $domains = ['example.com', 'test.org', 'sample.net', 'demo.kr'];
        return 'https://' . $domains[array_rand($domains)] . '/' . $this->slug();
    }

    /** 도메인 */
    public function domain(): string
    {
        $tlds = ['.com', '.net', '.org', '.kr', '.io'];
        return strtolower($this->alphaNum(random_int(5, 10))) . $tlds[array_rand($tlds)];
    }

    /** IPv4 */
    public function ipv4(): string
    {
        return random_int(1, 255) . '.' . random_int(0, 255) . '.'
             . random_int(0, 255) . '.' . random_int(1, 254);
    }

    /** IPv6 */
    public function ipv6(): string
    {
        $parts = [];
        for ($i = 0; $i < 8; $i++) {
            $parts[] = str_pad(dechex(random_int(0, 0xFFFF)), 4, '0', STR_PAD_LEFT);
        }
        return implode(':', $parts);
    }

    /** MAC 주소 */
    public function macAddress(): string
    {
        $parts = [];
        for ($i = 0; $i < 6; $i++) {
            $parts[] = str_pad(dechex(random_int(0, 0xFF)), 2, '0', STR_PAD_LEFT);
        }
        return implode(':', $parts);
    }

    /** 플레이스홀더 이미지 URL */
    public function imageUrl(int $width = 640, int $height = 480, ?string $category = null): string
    {
        $url = "https://placehold.co/{$width}x{$height}";
        if ($category !== null) {
            $url .= '?text=' . urlencode($category);
        }
        return $url;
    }

    /** User Agent */
    public function userAgent(): string
    {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/537.36 Safari/17.0',
            'Mozilla/5.0 (X11; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15',
        ];
        return $agents[array_rand($agents)];
    }

    // ── 식별자 ──

    /** UUID v4 */
    public function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /** 색상 HEX */
    public function color(): string
    {
        return '#' . str_pad(dechex(random_int(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
    }

    /** 해시 */
    public function hash(string $algo = 'sha256'): string
    {
        return hash($algo, random_bytes(32));
    }

    /** 비밀번호 */
    public function password(int $length = 12): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
        $pass = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $pass .= $chars[random_int(0, $max)];
        }
        return $pass;
    }

    /** 신용카드 번호 (테스트용, Luhn 쳋크섬 유효) */
    public function creditCard(): string
    {
        // Visa 테스트 패턴
        $prefix = '4';
        $num = $prefix;
        for ($i = 0; $i < 14; $i++) {
            $num .= (string) random_int(0, 9);
        }
        // Luhn 쳋크섬
        $num .= $this->luhnCheckDigit($num);
        return substr($num, 0, 4) . '-' . substr($num, 4, 4) . '-' . substr($num, 8, 4) . '-' . substr($num, 12, 4);
    }

    /** 은행계좌번호 (한국 형식) */
    public function bankAccount(): string
    {
        $banks = [
            ['KB국민', 3, 2, 4, 3],
            ['신한', 3, 2, 6, 1],
            ['우리', 4, 3, 6],
            ['하나', 3, 6, 5],
            ['NH농협', 3, 4, 4, 2],
        ];
        $bank = $banks[array_rand($banks)];
        $name = (string) $bank[0];
        $segments = array_slice($bank, 1);
        $parts = array_map(fn(int $len) => $this->digits($len), $segments);
        return $name . ' ' . implode('-', $parts);
    }

    // ── 한국 특화 ──

    /** 주민등록번호 (마스킹 형식, 테스트용) */
    public function rrn(): string
    {
        // 1970~1999 → 뒷자리 1(남)/2(여), 2000~2010 → 3(남)/4(여)
        $isOld = $this->boolean();
        $year = $isOld ? random_int(70, 99) : random_int(0, 10);
        $gender = $isOld ? random_int(1, 2) : random_int(3, 4);
        $month = str_pad((string) random_int(1, 12), 2, '0', STR_PAD_LEFT);
        $day = str_pad((string) random_int(1, 28), 2, '0', STR_PAD_LEFT);
        return str_pad((string) $year, 2, '0', STR_PAD_LEFT) . $month . $day . '-' . $gender . '******';
    }

    /** 사업자등록번호 (테스트용) */
    public function businessNumber(): string
    {
        return $this->digits(3) . '-' . $this->digits(2) . '-' . $this->digits(5);
    }

    // ── 파일 ──

    /** 파일명 */
    public function fileName(?string $extension = null): string
    {
        $ext = $extension ?? $this->fileExtension();
        return $this->alphaNum(random_int(5, 12)) . '.' . $ext;
    }

    /** 파일 확장자 */
    public function fileExtension(): string
    {
        $exts = ['jpg', 'png', 'gif', 'pdf', 'doc', 'xlsx', 'txt', 'csv', 'zip', 'mp4', 'mp3', 'html', 'json', 'xml'];
        return $exts[array_rand($exts)];
    }

    /** MIME 타입 */
    public function mimeType(): string
    {
        $types = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'application/json', 'application/xml', 'application/zip',
            'text/plain', 'text/html', 'text/csv',
            'audio/mpeg', 'video/mp4',
        ];
        return $types[array_rand($types)];
    }

    // ── 지리 ──

    /** 국가명 */
    public function country(): string
    {
        if ($this->locale === 'ko') {
            $countries = [
                '대한민국', '미국', '일본', '중국', '영국', '프랑스', '독일', '캐나다',
                '호주', '싱가폴', '베트남', '태국', '인도', '브라질', '멕시코',
                '스페인', '이탈리아', '네덜란드', '스위스', '스웨덴',
            ];
            return $countries[array_rand($countries)];
        }

        $countries = [
            'South Korea', 'United States', 'Japan', 'China', 'United Kingdom', 'France',
            'Germany', 'Canada', 'Australia', 'Singapore', 'Vietnam', 'Thailand',
            'India', 'Brazil', 'Mexico', 'Spain', 'Italy', 'Netherlands', 'Switzerland', 'Sweden',
        ];
        return $countries[array_rand($countries)];
    }

    /** 위도 (-90 ~ 90) */
    public function latitude(): float
    {
        return $this->float(-90.0, 90.0, 6);
    }

    /** 경도 (-180 ~ 180) */
    public function longitude(): float
    {
        return $this->float(-180.0, 180.0, 6);
    }

    /** 좌표 ([위도, 경도]) */
    public function coordinates(): array
    {
        return ['lat' => $this->latitude(), 'lng' => $this->longitude()];
    }

    /** 한국 좌표 (서울 근처) */
    public function koreanCoordinates(): array
    {
        return [
            'lat' => $this->float(33.0, 38.6, 6),
            'lng' => $this->float(124.5, 132.0, 6),
        ];
    }

    // ── 텍스트 확장 ──

    /** 복수 문장 배열 */
    public function sentences(int $count = 3): array
    {
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = $this->sentence();
        }
        return $result;
    }

    /** 복수 문단 배열 */
    public function paragraphs(int $count = 3): array
    {
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = $this->paragraph();
        }
        return $result;
    }

    /** 지정 글자수 텍스트 */
    public function text(int $maxChars = 200): string
    {
        $text = '';
        while (mb_strlen($text) < $maxChars) {
            $text .= $this->sentence() . ' ';
        }
        return mb_substr(trim($text), 0, $maxChars);
    }

    /** Lorem Ipsum */
    public function lorem(int $sentences = 3): string
    {
        $lipsum = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. '
            . 'Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. '
            . 'Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris. '
            . 'Duis aute irure dolor in reprehenderit in voluptate velit esse cillum. '
            . 'Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia. '
            . 'Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit. '
            . 'Neque porro quisquam est qui dolorem ipsum quia dolor sit amet consectetur.';

        $parts = explode('. ', $lipsum);
        $partCount = count($parts);
        $selected = [];
        for ($i = 0; $i < $sentences; $i++) {
            $selected[] = $parts[$i % $partCount];
        }
        return implode('. ', $selected) . '.';
    }

    // ── 색상 확장 ──

    /** RGB 색상 */
    public function rgbColor(): string
    {
        return 'rgb(' . random_int(0, 255) . ', ' . random_int(0, 255) . ', ' . random_int(0, 255) . ')';
    }

    /** RGBA 색상 */
    public function rgbaColor(): string
    {
        $alpha = round(random_int(0, 100) / 100, 2);
        return 'rgba(' . random_int(0, 255) . ', ' . random_int(0, 255) . ', ' . random_int(0, 255) . ', ' . $alpha . ')';
    }

    /** 색상명 */
    public function colorName(): string
    {
        $colors = [
            'red', 'blue', 'green', 'yellow', 'orange', 'purple', 'pink', 'cyan',
            'magenta', 'lime', 'teal', 'indigo', 'violet', 'coral', 'salmon',
            'navy', 'olive', 'maroon', 'aqua', 'gold', 'silver', 'crimson',
        ];
        return $colors[array_rand($colors)];
    }

    // ── 패턴 기반 생성 ──

    /** 숫자 패턴 (‘#’ → 랜덤 숫자) : numerify('###-####') → '123-4567' */
    public function numerify(string $pattern = '###'): string
    {
        return preg_replace_callback('/#/', fn() => (string) random_int(0, 9), $pattern) ?? $pattern;
    }

    /** 문자 패턴 (‘?’ → 랜덤 소문자) : lexify('????') → 'abcd' */
    public function lexify(string $pattern = '????'): string
    {
        return preg_replace_callback('/\?/', fn() => chr(random_int(97, 122)), $pattern) ?? $pattern;
    }

    /** 혼합 패턴 (‘#’ → 숫자, ‘?’ → 문자) : bothify('??-###') → 'ab-123' */
    public function bothify(string $pattern = '??-###'): string
    {
        return $this->lexify($this->numerify($pattern));
    }

    // ── 구조화 데이터 ──

    /** 랜덤 JSON 문자열 */
    public function json(int $fields = 5): string
    {
        $data = [];
        $generators = [
            fn() => ['name' => $this->name()],
            fn() => ['email' => $this->email()],
            fn() => ['phone' => $this->phone()],
            fn() => ['city' => $this->city()],
            fn() => ['company' => $this->company()],
            fn() => ['job' => $this->jobTitle()],
            fn() => ['date' => $this->date()],
            fn() => ['amount' => $this->number(100, 99999)],
            fn() => ['active' => $this->boolean()],
            fn() => ['id' => $this->uuid()],
        ];
        for ($i = 0; $i < $fields; $i++) {
            $gen = $generators[$i % count($generators)];
            $data = array_merge($data, $gen());
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';
    }

    /** 유저 프로필 배열 (일반적인 유저 테이블 구조) */
    public function userProfile(): array
    {
        return [
            'name'       => $this->name(),
            'email'      => $this->safeEmail(),
            'phone'      => $this->phone(),
            'address'    => $this->address(),
            'company'    => $this->company(),
            'job_title'  => $this->jobTitle(),
            'department' => $this->department(),
            'avatar'     => $this->imageUrl(100, 100, 'avatar'),
            'created_at' => $this->pastDate(365),
        ];
    }

    // ── 기타 ──

    /** 배열에서 랜덤 선택 */
    public function randomElement(array $array): mixed
    {
        return $array[array_rand($array)];
    }

    /** 배열에서 N개 랜덤 선택 */
    public function randomElements(array $array, int $count): array
    {
        $keys = (array) array_rand($array, min($count, count($array)));
        return array_map(fn($k) => $array[$k], $keys);
    }

    /**
     * 대량 데이터 생성
     *
     * @param callable(self, int): array $callback
     * @return list<array>
     */
    public function make(int $count, callable $callback): array
    {
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = $callback($this, $i);
        }
        return $result;
    }

    /**
     * 유니크 값 생성
     *
     * @param callable(): mixed $generator
     */
    public function unique(callable $generator, string $group = 'default', int $maxAttempts = 1000): mixed
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $value = $generator();
            $hash = md5(serialize($value));
            if (!isset($this->uniqueSets[$group][$hash])) {
                $this->uniqueSets[$group][$hash] = true;
                return $value;
            }
        }

        throw new \RuntimeException('유니크 값 생성 실패 (최대 시도 횟수 초과)');
    }

    /** 유니크 저장소 초기화 */
    public function resetUnique(?string $group = null): self
    {
        if ($group !== null) {
            unset($this->uniqueSets[$group]);
        } else {
            $this->uniqueSets = [];
        }
        return $this;
    }

    // ── 내부 ──

    /** Luhn 쳋크섬 계산 */
    private function luhnCheckDigit(string $number): string
    {
        $sum = 0;
        $len = strlen($number);
        for ($i = 0; $i < $len; $i++) {
            $digit = (int) $number[$len - 1 - $i];
            if ($i % 2 === 0) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }
        return (string) ((10 - ($sum % 10)) % 10);
    }

    /** 데이터셋에서 랜덤 선택 */
    private function pick(string $set): string
    {
        $items = $this->data[$set] ?? [];
        return $items !== [] ? $items[array_rand($items)] : '';
    }

    /** 랜덤 숫자 문자열 */
    private function digits(int $length): string
    {
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= (string) random_int(0, 9);
        }
        return $str;
    }

    /** 랜덤 영숫자 */
    private function alphaNum(int $length): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, 35)];
        }
        return $str;
    }

    /** 데이터 로드 */
    private function loadData(): void
    {
        $this->data['enWords'] = [
            'time', 'year', 'people', 'way', 'day', 'man', 'woman', 'child', 'world', 'life',
            'hand', 'part', 'place', 'case', 'week', 'company', 'system', 'program', 'question',
            'work', 'government', 'number', 'night', 'point', 'home', 'water', 'room', 'mother',
            'area', 'money', 'story', 'fact', 'month', 'lot', 'right', 'study', 'book', 'eye',
            'job', 'word', 'business', 'issue', 'side', 'kind', 'head', 'house', 'service',
            'friend', 'father', 'power', 'hour', 'game', 'line', 'end', 'member', 'law', 'car',
            'city', 'community', 'name', 'president', 'team', 'minute', 'idea', 'body', 'back',
        ];

        if ($this->locale === 'ko') {
            $this->data['lastNames'] = [
                '김', '이', '박', '최', '정', '강', '조', '윤', '장', '임',
                '한', '오', '서', '신', '권', '황', '안', '송', '류', '홍',
            ];
            $this->data['firstNames'] = [
                '민준', '서준', '도윤', '예준', '시우', '하준', '지호', '지후', '준서', '현우',
                '서연', '서윤', '지우', '서현', '민서', '하은', '하윤', '윤서', '지민', '채원',
                '수아', '지안', '지윤', '다은', '은서', '수빈', '예린', '하린', '소율', '유진',
            ];
            $this->data['cities'] = [
                '서울특별시', '부산광역시', '대구광역시', '인천광역시', '광주광역시',
                '대전광역시', '울산광역시', '세종특별자치시', '경기도 수원시', '경기도 성남시',
                '경기도 고양시', '경기도 용인시', '충남 천안시', '전북 전주시', '경남 창원시',
            ];
            $this->data['districts'] = [
                '강남구', '서초구', '송파구', '마포구', '용산구', '성동구', '종로구',
                '중구', '영등포구', '관악구', '동작구', '광진구', '동대문구', '은평구',
            ];
            $this->data['streets'] = [
                '테헤란로', '강남대로', '삼성로', '도곡로', '역삼로', '논현로', '봉은사로',
                '선릉로', '학동로', '압구정로', '청담로', '영동대로', '언주로', '도산대로',
            ];
            $this->data['words'] = [
                '사람', '시간', '나라', '사회', '교육', '문화', '경제', '기술', '환경',
                '건강', '생활', '정보', '세계', '역사', '미래', '발전', '변화', '노력',
                '관계', '시작', '결과', '의미', '가능', '중요', '필요', '다양', '새로운',
                '아름다운', '행복한', '즐거운', '따뜻한', '밝은', '깊은', '넓은', '높은',
            ];
        } else {
            $this->data['lastNames'] = [
                'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller',
                'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson',
            ];
            $this->data['firstNames'] = [
                'James', 'Mary', 'Robert', 'Patricia', 'John', 'Jennifer', 'Michael',
                'Linda', 'David', 'Elizabeth', 'William', 'Barbara', 'Richard', 'Susan',
                'Joseph', 'Jessica', 'Thomas', 'Sarah', 'Christopher', 'Karen',
            ];
            $this->data['cities'] = [
                'New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia',
                'San Antonio', 'San Diego', 'Dallas', 'San Jose', 'Austin', 'Jacksonville',
            ];
            $this->data['districts'] = [];
            $this->data['streets'] = [
                'Main', 'Oak', 'Maple', 'Cedar', 'Elm', 'Pine', 'Washington', 'Lake',
                'Hill', 'Park', 'Spring', 'Church', 'River', 'Market', 'Union',
            ];
            $this->data['streetSuffixes'] = [
                'St', 'Ave', 'Blvd', 'Dr', 'Ln', 'Rd', 'Way', 'Ct', 'Pl',
            ];
            $this->data['words'] = $this->data['enWords'];
        }
    }
}
