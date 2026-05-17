<?php

namespace App\Services;

/**
 * Responsible only for streaming data from source to destination.
 */
class StreamCopier
{
    /**
     * @param resource $from
     * @param resource $to
     * @param int $offset
     * @param callable $onProgress Receives updated offset
     * @param callable $shouldStop Called to check pause/cancel
     */
    public function copy($from, $to, int $offset, callable $onProgress, callable $shouldStop): int
    {
        $lastWrite = $offset;

        while (!feof($from)) {

            // Allow external logic to interrupt copy
            $shouldStop();

            $chunk = fread($from, 1024 * 1024);
            if ($chunk === false) {
                throw new \RuntimeException("Read error");
            }

            $written = fwrite($to, $chunk);
            if ($written === false) {
                throw new \RuntimeException("Write error");
            }

            $offset += $written;

            // Update progress every ~2MB
            if ($offset - $lastWrite >= 2 * 1024 * 1024) {
                $lastWrite = $offset;
                $onProgress($offset);
            }
        }

        return $offset;
    }
}