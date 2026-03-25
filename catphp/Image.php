<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Image — 이미지 처리 (GD)
 *
 * @config array{
 *     driver?: string,   // 'gd' (기본)
 *     quality?: int,     // JPEG 품질 (기본 85)
 * } image  → config('image.quality')
 */
final class Image
{
    private static ?self $instance = null;

    private ?\GdImage $resource = null;
    private int $quality;

    private function __construct(int $quality)
    {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('이미지 처리를 위해 GD 확장이 필요합니다. php.ini에서 extension=gd를 활성화하세요.');
        }
        $this->quality = $quality;
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self(
            quality: (int) (\config('image.quality') ?? 85),
        );
    }

    /** 이미지 열기 */
    public function open(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException("이미지 파일이 존재하지 않습니다: {$path}");
        }

        $c = clone $this;
        $info = getimagesize($path);
        if ($info === false) {
            throw new \RuntimeException("이미지를 열 수 없습니다: {$path}");
        }

        $c->resource = match ($info[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => imagecreatefrompng($path),
            IMAGETYPE_GIF  => imagecreatefromgif($path),
            IMAGETYPE_WEBP => imagecreatefromwebp($path),
            default        => throw new \RuntimeException("지원하지 않는 이미지 형식입니다"),
        };

        if ($c->resource === false) {
            throw new \RuntimeException("이미지 로드 실패: {$path}");
        }

        return $c;
    }

    /** 리사이즈 */
    public function resize(int $width, int $height): self
    {
        $this->ensureResource();
        $c = clone $this;
        $resized = imagecreatetruecolor($width, $height);
        if ($resized === false) {
            throw new \RuntimeException("이미지 리사이즈 실패");
        }
        $this->preserveTransparency($resized);
        imagecopyresampled($resized, $c->resource, 0, 0, 0, 0, $width, $height, imagesx($c->resource), imagesy($c->resource));
        $c->resource = $resized;
        return $c;
    }

    /** 크롭 */
    public function crop(int $x, int $y, int $width, int $height): self
    {
        $this->ensureResource();
        $c = clone $this;
        $cropped = imagecrop($c->resource, ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height]);
        if ($cropped === false) {
            throw new \RuntimeException("이미지 크롭 실패");
        }
        $c->resource = $cropped;
        return $c;
    }

    /** 썸네일 (비율 유지) */
    public function thumbnail(int $maxWidth, int $maxHeight): self
    {
        $this->ensureResource();
        $origW = imagesx($this->resource);
        $origH = imagesy($this->resource);
        $ratio = min($maxWidth / $origW, $maxHeight / $origH);
        $newW = (int) ($origW * $ratio);
        $newH = (int) ($origH * $ratio);
        return $this->resize($newW, $newH);
    }

    /** 텍스트 워터마크 (TrueType 폰트 지원, 한글 포함) */
    public function watermark(string $text, int $x = 10, int $y = 10, int $fontSize = 16, ?string $fontPath = null): self
    {
        $this->ensureResource();
        $c = clone $this;
        $color = imagecolorallocatealpha($c->resource, 255, 255, 255, 60);

        if ($fontPath !== null && is_file($fontPath) && function_exists('imagettftext')) {
            imagettftext($c->resource, $fontSize, 0, $x, $y + $fontSize, $color, $fontPath, $text);
        } else {
            imagestring($c->resource, min($fontSize, 5), $x, $y, $text, $color);
        }

        return $c;
    }

    /** 이미지 저장 */
    public function save(string $path): bool
    {
        $this->ensureResource();
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return match ($ext) {
            'jpg', 'jpeg' => imagejpeg($this->resource, $path, $this->quality),
            'png'         => imagepng($this->resource, $path),
            'gif'         => imagegif($this->resource, $path),
            'webp'        => imagewebp($this->resource, $path, $this->quality),
            default       => throw new \RuntimeException("지원하지 않는 출력 형식: {$ext}"),
        };
    }

    /** 이미지 너비 */
    public function width(): int
    {
        $this->ensureResource();
        return imagesx($this->resource);
    }

    /** 이미지 높이 */
    public function height(): int
    {
        $this->ensureResource();
        return imagesy($this->resource);
    }

    /** 포맷 변환 (저장 경로 확장자로 자동 변환) */
    public function convert(string $outputPath): bool
    {
        return $this->save($outputPath);
    }

    /** clone 시 GdImage 깊은 복사 (원본 리소스 공유 방지) */
    public function __clone()
    {
        if ($this->resource !== null) {
            $w = imagesx($this->resource);
            $h = imagesy($this->resource);
            $copy = imagecreatetruecolor($w, $h);
            if ($copy !== false) {
                $this->preserveTransparency($copy);
                imagecopy($copy, $this->resource, 0, 0, 0, 0, $w, $h);
                $this->resource = $copy;
            }
        }
    }

    private function ensureResource(): void
    {
        if ($this->resource === null) {
            throw new \RuntimeException("이미지를 먼저 열어주세요: image()->open(\$path)");
        }
    }

    private function preserveTransparency(\GdImage $image): void
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
    }
}
