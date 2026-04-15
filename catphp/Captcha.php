<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Captcha — 캡차 생성 + 검증
 *
 * 이미지 캡차 (GD) 또는 수학 캡차 지원.
 *
 * 사용법:
 *   captcha()->image();       // GD 이미지 캡차 출력 (세션에 정답 저장)
 *   captcha()->math();        // 수학 캡차 (HTML 반환)
 *   captcha()->verify($input);// 사용자 입력 검증
 *   captcha()->src();         // base64 이미지 data URI
 */
final class Captcha
{
    private static ?self $instance = null;

    private int $width;
    private int $height;
    private int $length;
    private string $charset;
    private string $sessionKey;

    private function __construct()
    {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('캡차 사용을 위해 GD 확장이 필요합니다. php.ini에서 extension=gd를 활성화하세요.');
        }
        $this->width      = (int) \config('captcha.width', 150);
        $this->height     = (int) \config('captcha.height', 50);
        $this->length     = (int) \config('captcha.length', 5);
        $this->charset    = (string) \config('captcha.charset', '23456789ABCDEFGHJKLMNPQRSTUVWXYZ');
        $this->sessionKey = (string) \config('captcha.session_key', '_captcha');
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    // ── 이미지 캡차 (GD) ──

    /** 이미지 캡차 직접 출력 */
    public function image(): never
    {
        $code = $this->generateCode();
        $this->store($code);

        $img = $this->createImage($code);

        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache');
        imagepng($img);
        imagedestroy($img);
        exit;
    }

    /** base64 data URI 반환 (img src에 삽입용) */
    public function src(): string
    {
        $code = $this->generateCode();
        $this->store($code);

        $img = $this->createImage($code);

        ob_start();
        imagepng($img);
        $data = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode((string) $data);
    }

    /**
     * HTML img 태그 반환
     *
     * 리프레시: base64 data URI이므로 onclick 자동 갱신 불가.
     * 새 캡차가 필요하면 captcha()->src() API 호출 후 img.src 교체 필요.
     *
     * @param string $refreshUrl 캡차 갱신 API 엔드포인트 (비어있으면 리프레시 비활성)
     */
    public function html(string $id = 'captcha', string $refreshUrl = ''): string
    {
        $src = $this->src();
        $escapedId = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $onclick = '';
        if ($refreshUrl !== '') {
            // JS 컨텍스트 + HTML 속성 이중 이스케이프: json_encode → HTML 엔티티 안전 출력
            $jsUrl = json_encode($refreshUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            $onclick = " onclick=\"fetch({$jsUrl}).then(r=>r.text()).then(s=>this.src=s)\"";
        }
        return '<img id="' . $escapedId
             . '" src="' . $src . '" alt="captcha" style="cursor:pointer;"'
             . $onclick . '>';
    }

    // ── 수학 캡차 ──

    /**
     * 수학 캡차 생성
     *
     * @return array{question:string, html:string}
     */
    public function math(): array
    {
        $ops = ['+', '-', '×'];
        $op = $ops[array_rand($ops)];

        $a = random_int(1, 20);
        $b = random_int(1, 20);

        // 빼기일 때 음수 방지
        if ($op === '-' && $a < $b) {
            [$a, $b] = [$b, $a];
        }

        $answer = match ($op) {
            '+'  => $a + $b,
            '-'  => $a - $b,
            '×'  => $a * $b,
        };

        $this->store((string) $answer);

        $question = "{$a} {$op} {$b} = ?";
        $html = '<span style="font-family:monospace;font-size:18px;letter-spacing:2px;">'
              . htmlspecialchars($question, ENT_QUOTES, 'UTF-8') . '</span>';

        return [
            'question' => $question,
            'html'     => $html,
        ];
    }

    // ── 검증 ──

    /** 사용자 입력 검증 */
    public function verify(string $input): bool
    {
        $stored = $this->retrieve();

        if ($stored === null || $stored === '') {
            return false;
        }

        // 타이밍 공격 방어: hash_equals 사용
        $result = hash_equals(strtolower($stored), strtolower(trim($input)));

        // 1회용: 검증 후 삭제
        $this->clear();

        return $result;
    }

    // ── 설정 ──

    /** 이미지 크기 설정 */
    public function size(int $width, int $height): self
    {
        $c = clone $this;
        $c->width = $width;
        $c->height = $height;
        return $c;
    }

    /** 코드 길이 설정 */
    public function length(int $length): self
    {
        $c = clone $this;
        $c->length = max(1, $length);
        return $c;
    }

    // ── 내부 ──

    /** 랜덤 코드 생성 */
    private function generateCode(): string
    {
        $code = '';
        $max = strlen($this->charset) - 1;
        for ($i = 0; $i < $this->length; $i++) {
            $code .= $this->charset[random_int(0, $max)];
        }
        return $code;
    }

    /** GD 이미지 생성 */
    private function createImage(string $code): \GdImage
    {
        $img = imagecreatetruecolor($this->width, $this->height);
        if ($img === false) {
            throw new \RuntimeException('GD imagecreatetruecolor 실패');
        }

        // 배경
        $bg = imagecolorallocate($img, random_int(220, 255), random_int(220, 255), random_int(220, 255));
        imagefill($img, 0, 0, $bg);

        // 노이즈 라인
        for ($i = 0; $i < 6; $i++) {
            $lineColor = imagecolorallocate($img, random_int(100, 200), random_int(100, 200), random_int(100, 200));
            imageline(
                $img,
                random_int(0, $this->width),
                random_int(0, $this->height),
                random_int(0, $this->width),
                random_int(0, $this->height),
                $lineColor
            );
        }

        // 노이즈 점
        for ($i = 0; $i < 100; $i++) {
            $dotColor = imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255));
            imagesetpixel($img, random_int(0, $this->width), random_int(0, $this->height), $dotColor);
        }

        // 텍스트 출력
        $len = strlen($code);
        $charWidth = (int) ($this->width / ($len + 1));
        $fontSize = 5; // GD 내장 폰트 (1-5)

        for ($i = 0; $i < $len; $i++) {
            $textColor = imagecolorallocate($img, random_int(0, 100), random_int(0, 100), random_int(0, 100));
            $x = $charWidth * ($i + 1) - 10 + random_int(-3, 3);
            $y = (int) ($this->height / 2) - 8 + random_int(-5, 5);
            imagestring($img, $fontSize, max(0, $x), max(0, $y), $code[$i], $textColor);
        }

        return $img;
    }

    /** 세션에 정답 저장 (Session 도구 통합) */
    private function store(string $code): void
    {
        \session()->set($this->sessionKey, $code);
    }

    /** 세션에서 정답 꺼내기 */
    private function retrieve(): ?string
    {
        $val = \session()->get($this->sessionKey);
        return is_string($val) ? $val : null;
    }

    /** 세션에서 정답 삭제 */
    private function clear(): void
    {
        \session()->forget($this->sessionKey);
    }
}
