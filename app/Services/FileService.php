<?php
namespace App\Services;

class FileService
{
    /**
     * Recursively deletes a directory and its contents.
     *
     * @param string $dirPath
     * @return bool
     */
    public function deleteDirectory($dirPath)
    {
        if (!is_dir($dirPath)) {
            return false;
        }

        try {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dirPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $file) {
                $filePath = $file->getRealPath();
                if (!is_writable($filePath)) {
                    continue;
                }

                if ($file->isDir()) {
                    rmdir($filePath);
                } else {
                    unlink($filePath);
                }
            }

            rmdir($dirPath);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}