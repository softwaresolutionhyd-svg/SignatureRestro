<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Passport-size staff photo (35×45 mm aspect ≈ 7:9), stored as JPEG.
 */
final class EmployeePhotoService
{
    private const WIDTH = 350;

    private const HEIGHT = 450;

    public function storePassport(UploadedFile $file): string
    {
        $contents = $this->makePassportJpeg($file);

        $name = 'employees/'.Str::uuid()->toString().'.jpg';
        Storage::disk('public')->put($name, $contents);
        PublicStorageMirror::publish($name);

        return $name;
    }

    public function delete(?string $path): void
    {
        if ($path === null || trim($path) === '') {
            return;
        }

        PublicStorageMirror::unpublish($path);
        Storage::disk('public')->delete($path);
    }

    private function makePassportJpeg(UploadedFile $file): string
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('GD extension is required to process employee photos.');
        }

        $source = $this->loadImage($file);
        if ($source === false) {
            throw new RuntimeException('Unable to read the uploaded image.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $targetRatio = self::WIDTH / self::HEIGHT;
        $srcRatio = $width / max(1, $height);

        if ($srcRatio > $targetRatio) {
            $cropH = $height;
            $cropW = (int) max(1, round($height * $targetRatio));
        } else {
            $cropW = $width;
            $cropH = (int) max(1, round($width / $targetRatio));
        }

        $srcX = (int) max(0, floor(($width - $cropW) / 2));
        $srcY = (int) max(0, floor(($height - $cropH) / 2));

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if ($canvas === false) {
            imagedestroy($source);
            throw new RuntimeException('Unable to prepare photo canvas.');
        }

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            $srcX,
            $srcY,
            self::WIDTH,
            self::HEIGHT,
            $cropW,
            $cropH
        );

        imagedestroy($source);

        ob_start();
        imagejpeg($canvas, null, 90);
        imagedestroy($canvas);
        $binary = ob_get_clean();

        if ($binary === false || $binary === '') {
            throw new RuntimeException('Unable to encode employee photo.');
        }

        return $binary;
    }

    /** @return resource|\GdImage|false */
    private function loadImage(UploadedFile $file)
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return false;
        }

        $mime = strtolower((string) $file->getMimeType());

        return match ($mime) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false,
            default => false,
        };
    }
}
