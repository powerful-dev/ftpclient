<?php

namespace App\Helpers;

class PowershellHelper
{
    public static function buildDeleteScript(array $files): string
    {
        $pathsList = collect($files)
            ->pluck('path')
            ->map(fn($path) => '    "' . str_replace('"', '""', $path) . '"')
            ->implode(",\n    ");

        $psContent = <<<'PS1'
            # PowerShell script with self-elevation to delete multiple files
            $ErrorActionPreference = 'Stop'
            $paths = @(
            PS1;

        $psContent .= $pathsList . "\n";

        $psContent .= <<<'PS1'
            )
            Write-Host "Checking the paths (elevated):"
            $existingPaths = @()
            foreach ($path in $paths) {
                if (Test-Path -LiteralPath $path) {
                    Write-Host "✓ Path exists: $path"
                    $attrs = (Get-Item $path).Attributes
                    Write-Host " Attr: $attrs"
                    $existingPaths += $path
                } else {
                    Write-Error "Path not exists: $path"
                }
            }
            if ($existingPaths.Count -eq 0) {
                Write-Error "No existing paths to remove!"
                exit 1
            }

            try {
                Remove-Item `
                    -LiteralPath ($existingPaths | ForEach-Object { [string]$_ }) `
                    -Recurse `
                    -Force
                Write-Host "Success: $($existingPaths.Count) files deleted"
            } catch {
                Write-Error "Delete error: $($_.Exception.Message)"
                Write-Host "Possible reasons: files are blocked by the process, antivirus or no rights."
            }
            PS1;

        return $psContent;
    }

    public static function buildCopyScript(array $operations): string
    {
        $operationsJson = json_encode($operations, JSON_UNESCAPED_UNICODE);

        return <<<PS1
            \$ErrorActionPreference = 'Stop'

            \$operations = ConvertFrom-Json @'
            {$operationsJson}
            '@

            foreach (\$op in \$operations) {

                \$from = \$op.from
                \$to   = \$op.to

                Copy-Item `
                    -LiteralPath \$from `
                    -Destination \$to `
                    -Recurse `
                    -Force
            }
            PS1;
    }

    public static function buildMoveScript(array $operations): string
    {
        $operationsJson = json_encode($operations, JSON_UNESCAPED_UNICODE);

        return <<<PS1
            \$ErrorActionPreference = 'Stop'

            \$operations = ConvertFrom-Json @'
            {$operationsJson}
            '@

            foreach (\$op in \$operations) {

                \$from = \$op.from
                \$to   = \$op.to

                Move-Item `
                    -LiteralPath \$from `
                    -Destination \$to `
                    -Force
            }
            PS1;
    }

    public static function runScript(string $content): array
    {
        $filename = 'protected-' . md5(uniqid((string)microtime(true), true)) . '.ps1';
        $filepath = storage_path('app/' . $filename);

        file_put_contents($filepath, "\xEF\xBB\xBF" . $content, LOCK_EX);

        $command = 'powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process powershell -Verb RunAs -WindowStyle Hidden -ArgumentList \'-ExecutionPolicy Bypass -File ' . $filepath . '\' -Wait"';

        exec($command . " 2>&1", $output, $returnCode);

        usleep(300000);

        if (file_exists($filepath)) {
            @unlink($filepath);
        }

        return [
            'output' => $output,
            'code' => $returnCode,
        ];
    }
}
