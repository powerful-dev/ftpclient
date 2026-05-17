<?php

namespace App\Helpers;

class PathHelper
{
    /**
     * Encodes the path for the FTPS server, converting UTF-8 to CP1251 and replacing \ with /.
     */
    public static function encode(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        if (self::canConvertToCp1251($path)) {
            return mb_convert_encoding($path, 'Windows-1251', 'UTF-8');
        }

        return $path; // UTF-8
    }

    /**
     * Decodes the path from the FTPS server, converting CP1251 to UTF-8.
     */
    public static function decode(string $path): string
    {
        $test = @mb_convert_encoding($path, 'UTF-8', 'Windows-1251');
        $restored = @mb_convert_encoding($test, 'Windows-1251', 'UTF-8');

        if ($restored === $path) {
            return $test;
        }

        return $path; // UTF-8
    }

    /**
     * Checks if a string can be converted to CP1251 and back without loss.
     */
    private static function canConvertToCp1251(string $string): bool
    {
        $converted = @mb_convert_encoding($string, 'Windows-1251', 'UTF-8');
        $restored = @mb_convert_encoding($converted, 'UTF-8', 'Windows-1251');

        return $restored === $string;
    }

    public static function normalize(string $path): string
    {

        $path = preg_replace('#/+#', '/', str_replace('\\', '/', $path));

        // Check if the path starts with "X:" (drive on Windows)
        if (preg_match('#^[A-Za-z]:#', $path)) {
            $path = rtrim($path, '/');
        } else {
            $path = '/' . trim($path, '/');
        }

        return $path;
    }

    public static function toUnixPath(string $windowsPath): string
    {

        $normalizedPath = str_replace('\\', '/', $windowsPath);

        $cleanPath = preg_replace('/^[A-Za-z]:\//', '', $normalizedPath);

        return '/' . ltrim($cleanPath, '/');
    }

    public static function normalizeSlashes(string $string): string
    {
        return str_replace('\\', '/', $string);
    }


    public static function splitPath(string $path): array
    {
        $path = str_replace('\\', '/', $path);

        $dir = dirname($path);

        $dir = str_replace('\\', '/', $dir);

        if ($dir === '.' || $dir === '') {
            $dir = '/';
        }

        $dir = rtrim($dir, '/') . '/';

        return [
            'dir'  => $dir,
            'base' => basename($path),
        ];
    }

    public static function diffBase(string $a, string $b): array
    {
        $aBase = basename(str_replace('\\', '/', $a));
        $bBase = basename(str_replace('\\', '/', $b));

        $len = min(strlen($aBase), strlen($bBase));
        $i = 0;

        while ($i < $len && $aBase[$i] === $bBase[$i]) {
            $i++;
        }

        return [
            'a_common' => substr($aBase, 0, $i),
            'a_diff'   => substr($aBase, $i),

            'b_common' => substr($bBase, 0, $i),
            'b_diff'   => substr($bBase, $i),
        ];
    }
}
