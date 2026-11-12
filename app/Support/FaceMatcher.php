<?php

namespace App\Support;

final class FaceMatcher
{
    /**
     * Difference hash (dHash) 64-bit dari sebuah berkas gambar.
     * Dikembalikan sebagai 16 karakter hex, atau null bila gambar tidak valid.
     */
    public static function hashFile(string $path): ?string
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 1 || $height < 1) {
            imagedestroy($image);

            return null;
        }

        $resized = imagecreatetruecolor(9, 8);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, 9, 8, $width, $height);
        imagedestroy($image);

        $bits = '';
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $left = self::grayAt($resized, $x, $y);
                $right = self::grayAt($resized, $x + 1, $y);
                $bits .= $left < $right ? '1' : '0';
            }
        }

        imagedestroy($resized);

        return sprintf('%016x', bindec($bits));
    }

    public static function hashBytes(string $bytes): ?string
    {
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return null;
        }
        imagedestroy($image);

        $temp = tempnam(sys_get_temp_dir(), 'face_');
        if ($temp === false) {
            return null;
        }
        file_put_contents($temp, $bytes);
        $hash = self::hashFile($temp);
        @unlink($temp);

        return $hash;
    }

    /** Jarak Hamming 0-64. Semakin kecil semakin mirip. */
    public static function distance(string $a, string $b): int
    {
        $xor = hexdec($a) ^ hexdec($b);

        return substr_count(decbin($xor), '1');
    }

    private static function grayAt($image, int $x, int $y): int
    {
        $rgb = imagecolorat($image, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        return (int) round(0.299 * $r + 0.587 * $g + 0.114 * $b);
    }
}
