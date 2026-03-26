<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Upload — 파일 업로드
 *
 * @config array{
 *     max_size?: string,        // 최대 파일 크기 (기본 '10M')
 *     allowed?: array<string>,  // 허용 확장자
 * } upload  → config('upload.max_size')
 */
final class Upload
{
    private static ?self $instance = null;

    private ?string $fieldName = null;
    private ?int $maxSizeBytes = null;
    private ?array $allowedTypes = null;

    private function __construct(
        private readonly int $defaultMaxSize,
        private readonly array $defaultAllowed,
    ) {}

    public static function getInstance(): self
    {
        $sizeStr = \config('upload.max_size') ?? '10M';
        return self::$instance ??= new self(
            defaultMaxSize: \parse_size($sizeStr),
            defaultAllowed: \config('upload.allowed') ?? ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'zip'],
        );
    }

    /** 업로드 필드 지정 */
    public function file(string $fieldName): self
    {
        $c = clone $this;
        $c->fieldName = $fieldName;
        return $c;
    }

    /** 최대 파일 크기 설정 */
    public function maxSize(string $size): self
    {
        $c = clone $this;
        $c->maxSizeBytes = \parse_size($size);
        return $c;
    }

    /** 허용 확장자 설정 */
    public function allowTypes(array $types): self
    {
        $c = clone $this;
        $c->allowedTypes = $types;
        return $c;
    }

    /** 파일 저장 */
    public function save(string $directory, ?string $filename = null): ?string
    {
        if ($this->fieldName === null) {
            throw new \RuntimeException('업로드 필드를 지정하세요: upload()->file("fieldname")');
        }

        $file = $_FILES[$this->fieldName] ?? null;
        if ($file === null || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        // 크기 검증
        $maxSize = $this->maxSizeBytes ?? $this->defaultMaxSize;
        if ($file['size'] > $maxSize) {
            throw new \RuntimeException('파일 크기가 제한을 초과했습니다');
        }

        // 파일명 살균 (Guard::filename() 연동 — null 바이트 + 이중 확장자 방어 포함)
        $safeName = \guard()->filename($file['name']);

        // 확장자 검증
        $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
        $allowed = $this->allowedTypes ?? $this->defaultAllowed;
        if (!in_array($ext, $allowed, true)) {
            throw new \RuntimeException("허용되지 않는 파일 형식입니다: {$ext}");
        }

        // MIME 타입 교차 검증 (finfo)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if ($mime !== false && !$this->isMimeAllowed($mime, $ext)) {
            throw new \RuntimeException("파일 MIME 타입이 확장자와 일치하지 않습니다: {$mime}");
        }

        // 최종 파일명 생성 (암호학적 랜덤으로 예측 불가능)
        if ($filename === null) {
            $filename = bin2hex(random_bytes(8)) . '_' . $safeName;
        }

        // 디렉토리 보장
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $destination = rtrim($directory, '/') . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \RuntimeException('파일 저장에 실패했습니다');
        }

        return $filename;
    }

    /** 확장자-MIME 매핑 교차 검증 */
    private function isMimeAllowed(string $mime, string $ext): bool
    {
        $map = [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'gif'  => ['image/gif'],
            'webp' => ['image/webp'],
            'svg'  => ['image/svg+xml'],
            'pdf'  => ['application/pdf'],
            'zip'  => ['application/zip', 'application/x-zip-compressed'],
            'csv'  => ['text/csv', 'text/plain'],
            'txt'  => ['text/plain'],
            'json' => ['application/json', 'text/plain'],
            'xml'  => ['application/xml', 'text/xml'],
            'mp4'  => ['video/mp4'],
            'mp3'  => ['audio/mpeg'],
            'doc'  => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls'  => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ];

        // 매핑에 없는 확장자는 통과 (커스텀 확장자 허용)
        if (!isset($map[$ext])) {
            return true;
        }

        return in_array($mime, $map[$ext], true);
    }

    /** 파일 이동 */
    public function move(string $from, string $to): bool
    {
        $dir = dirname($to);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return rename($from, $to);
    }

}
