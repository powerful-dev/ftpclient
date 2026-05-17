<?php

namespace App\Helpers;

class FormatHelper
{
    public static function speed(float $bytesPerSecond): string
    {
        if ($bytesPerSecond <= 0) {
            return '';
        }

        if ($bytesPerSecond < 1024 * 1024) {
            return round($bytesPerSecond / 1024, 1) . ' KB/s';
        }

        if ($bytesPerSecond < 1024 * 1024 * 1024) {
            return round($bytesPerSecond / 1024 / 1024, 1) . ' MB/s';
        }

        return round($bytesPerSecond / 1024 / 1024 / 1024, 2) . ' GB/s';
    }

    public static function eta(?float $seconds): string
    {

        if ($seconds === null) {
            return '';
        }

        $seconds = (int) round($seconds);

        if ($seconds < 4) {
            return __('eta.completing');
        }

        if ($seconds < 60) {
            return __('eta.remaining') . " ~{$seconds} сек";
        }

        if ($seconds < 3600) {
            return __('eta.remaining') . ' ~' . ceil($seconds / 60) . ' ' . __('min');
        }

        return __('eta.remaining') . ' ~' . ceil($seconds / 3600) . ' ' . __('hours');
    }
}