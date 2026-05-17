<?php
namespace App\Services;

use Websemantics\FileIcons\FileIcons;
use Illuminate\Support\Str;

class FileIconService
{
    protected array $override = [
        'svg'   => 'icon icon-file-svg medium-blue',
        'jpeg'  => 'icon image-icon medium-green',
    ];

    public function get(string $filename): string
    {

        $ext = Str::of($filename)->afterLast('.')->lower();

        $icons = new FileIcons();

        return $this->override[$ext->value]
            ?? $icons->getClassWithColor(
                !is_null($ext->value) ? '.' . $ext->value : $filename
            );
    }
}
