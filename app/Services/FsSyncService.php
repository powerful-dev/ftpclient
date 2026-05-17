<?php

namespace App\Services;

class FsSyncService
{
    /**
     * Базовый механизм ожидания
     */
    public function wait(
        callable $condition,
        int $timeoutMs = 1000,
        int $intervalMs = 50
    ): bool {
        $elapsed = 0;

        do {
            clearstatcache(true);

            try {
                if ($condition()) {
                    return true;
                }
            } catch (\Throwable $e) {
                // если ФС временно глючит — просто ждём дальше
            }

            usleep($intervalMs * 1000);
            $elapsed += $intervalMs;

        } while ($elapsed < $timeoutMs);

        return false;
    }

    /**
     * Ожидание готовности файла
     * (существует и имеет ненулевой размер)
     */
    public function waitForFile(string $path, int $timeoutMs = 1000): bool
    {
        return $this->wait(
            fn () => is_file($path) && file_exists($path) && filesize($path) > 0,
            $timeoutMs
        );
    }

    /**
     * Ожидание готовности директории
     * (папка существует и содержит хотя бы 1 файл)
     */
    public function waitForDir(string $dir, int $timeoutMs = 1000): bool
    {
        return $this->wait(
            fn () => is_dir($dir) && count(@scandir($dir)) > 2,
            $timeoutMs
        );
    }

    /**
     * Универсальный вариант:
     * файл или директория
     */
    public function waitForPathReady(string $path, int $timeoutMs = 1000): bool
    {
        return $this->wait(
            function () use ($path) {

                if (is_file($path)) {
                    return filesize($path) > 0;
                }

                if (is_dir($path)) {
                    return count(@scandir($path)) > 2;
                }

                return false;
            },
            $timeoutMs
        );
    }

    /**
     * Ожидание увеличения количества файлов в директории
     * (удобно для batch-операций)
     */
    public function waitForFilesCount(
        string $dir,
        int $expectedCount,
        int $timeoutMs = 1000
    ): bool {
        return $this->wait(
            fn () => is_dir($dir) && count(scandir($dir)) >= $expectedCount,
            $timeoutMs
        );
    }

    /**
     * Ожидание удаления файла или директории
     */
    public function waitUntilGone(string $path, int $timeoutMs = 1000): bool
    {
        return $this->wait(
            fn () => !file_exists($path),
            $timeoutMs
        );
    }
}
