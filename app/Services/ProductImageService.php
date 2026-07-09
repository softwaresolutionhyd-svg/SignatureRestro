<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class ProductImageService
{
    private const OUTPUT_SIZE = 512;

    public function storeSquare(UploadedFile $file): string
    {
        $contents = $this->makeSquareJpeg($file);

        $name = 'products/'.Str::uuid()->toString().'.jpg';
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

    private function makeSquareJpeg(UploadedFile $file): string
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('GD extension is required to process product images.');
        }

        $source = $this->loadImage($file);
        if ($source === false) {
            throw new RuntimeException('Unable to read the uploaded image.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $size = min($width, $height);
        $srcX = (int) max(0, floor(($width - $size) / 2));
        $srcY = (int) max(0, floor(($height - $size) / 2));

        $square = imagecreatetruecolor(self::OUTPUT_SIZE, self::OUTPUT_SIZE);
        if ($square === false) {
            imagedestroy($source);
            throw new RuntimeException('Unable to prepare image canvas.');
        }

        imagecopyresampled(
            $square,
            $source,
            0,
            0,
            $srcX,
            $srcY,
            self::OUTPUT_SIZE,
            self::OUTPUT_SIZE,
            $size,
            $size
        );

        imagedestroy($source);

        ob_start();
        imagejpeg($square, null, 88);
        imagedestroy($square);
        $binary = ob_get_clean();

        if ($binary === false || $binary === '') {
            throw new RuntimeException('Unable to encode product image.');
        }

        return $binary;
    }

    /** @return resource|false */
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
