<?php


namespace App\Helpers;

use App\Constants\Path;


class FileHelper
{

	public static function convertSizeToBytes($size)
    {
        if ($size === 'dir' || $size === 'Unknown') return 0;
        $value = floatval($size);
        if (strpos($size, 'KB') !== false) return $value * 1024;
        if (strpos($size, 'MB') !== false) return $value * 1048576;
        if (strpos($size, 'GB') !== false) return $value * 1073741824;
        return $value; 
    }

    public static function formatPermissions($path)
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // For Windows, simplified: read-only or full access
            return is_writable($path) ? '-rwxr-xr-x' : '-r--r--r--';
        } else {
            // For Linux/macOS: use fileperms
            $perms = fileperms($path);

            $info = '';
            $info .= ($perms & 0x4000) ? 'd' : '-';
            $info .= ($perms & 0x0100) ? 'r' : '-';
            $info .= ($perms & 0x0080) ? 'w' : '-';
            $info .= ($perms & 0x0040) ? 'x' : '-';
            $info .= ($perms & 0x0020) ? 'r' : '-';
            $info .= ($perms & 0x0010) ? 'w' : '-';
            $info .= ($perms & 0x0008) ? 'x' : '-';
            $info .= ($perms & 0x0004) ? 'r' : '-';
            $info .= ($perms & 0x0002) ? 'w' : '-';
            $info .= ($perms & 0x0001) ? 'x' : '-';
            return $info;
        }
    }

    public static function isDirectoryWritable($dirPath)
    {

        try {
            if (!is_dir($dirPath)) {
                return false;
            }

            $testFile = rtrim($dirPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . uniqid('wtest_', true) . '.tmp';

            $handle = @fopen($testFile, 'w');
            if ($handle === false) {
                return false;
            }

            fclose($handle);
            @unlink($testFile);

            return true;
        } catch (\Throwable $e) {
            logger()->error("Writable check failed for {$dirPath}: " . $e->getMessage());
            return false;
        }
    }

    public static function calculateTotalFiles(array $files): int
    {
        $totalItems = 0;

        foreach ($files as $file) {
            $sourcePath = $file['path'];

            if (!file_exists($sourcePath)) {
                continue;
            }

            if (is_file($sourcePath)) {
                $totalItems += 1;
            } elseif (is_dir($sourcePath)) {
                $totalItems += 1;
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $item) {
                    $totalItems += 1; 
                }
            }
        }

        return $totalItems;
    }

    public static function calculateTotalBytes(array $validFiles): int
    {
        $totalBytes = 0;

        foreach ($validFiles as $file) {
            $sourcePath = $file['path'];

            if (!file_exists($sourcePath)) {
                logger()->error("Source does not exist in calculateTotalBytes: {$sourcePath}");
                continue;
            }

            if (is_file($sourcePath)) {
                $totalBytes += filesize($sourcePath);
            } elseif (is_dir($sourcePath)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($iterator as $item) {
                    if ($item->isFile()) {
                        $totalBytes += $item->getSize();
                    }
                }
            }
        }

        return $totalBytes;
    }

    public static function scanDirectory($path)
    {
        $fileList = [];
        $realPath = realpath($path);

        if ($realPath === false || !is_dir($realPath)) {
            return $fileList;
        }

        $items = @scandir($realPath);
        if ($items === false) {
            return $fileList;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $realPath . DIRECTORY_SEPARATOR . $item;

            $isDir = @is_dir($fullPath);

            if ($isDir === false && !file_exists($fullPath)) {
                continue;
            }

            $size = 'Unknown';
            if ($isDir) {
                $size = '';
            } else {
                $fileSize = @filesize($fullPath);
                $size = $fileSize !== false ? StringHelper::formatSize($fileSize) : 'Unknown';
            }

            $modified = @filemtime($fullPath);
            $modified = StringHelper::formatLastModified($modified);

            $permissions = self::formatPermissions($fullPath);

            $fileList[] = [
                'name' => $item,
                'path' => $fullPath,
                'type' => $isDir ? 'dir' : 'file',
                'size' => $size,
                'modified' => $modified,
                'permissions' => $permissions,
                'icon' => !$isDir ? app(\App\Services\FileIconService::class)->get($fullPath) : 'folder-icon'
            ];
        }

        return $fileList;
    }

    public static function getFilesFromPath($path)
    {
        $fileList = [];
    
        // If the path is a root, show disks (Windows) or the system root (Linux/macOS)
        if ($path === Path::ROOT) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                for ($i = 'A'; $i <= 'Z'; $i++) {
                    $drive = $i . ':';
                    if (is_dir($drive . '\\')) {
                        $fileList[] = [
                            'name' => $drive,
                            'path' => $drive,
                            'type' => 'dir',
                            'size' => config('app.drive_label'),
                            'modified' => date('Y-m-d H:i', filemtime($drive)),
                            'permissions' => self::formatPermissions($drive),
                            'icon' => 'windows-icon light-blue'
                        ];
                    }
                }
            } else {
                // For Linux/macOS: show root contents
                $fileList = self::scanDirectory('/');
            }
        } else {
            // For all other paths, scan the directory
            $fileList = self::scanDirectory($path);
        }
    
        return $fileList;
    }
}