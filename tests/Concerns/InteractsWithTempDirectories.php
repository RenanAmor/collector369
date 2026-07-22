<?php

declare(strict_types=1);

namespace Tests\Concerns;

trait InteractsWithTempDirectories
{
    protected function makeTempDirectory(string $prefix): string
    {
        $path = str_replace('\\', '/', sys_get_temp_dir()) . '/' . $prefix . '-' . uniqid();
        mkdir($path, 0775, true);

        return $path;
    }

    protected function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $directory . '/' . $item;

            is_dir($itemPath) ? $this->removeDirectory($itemPath) : unlink($itemPath);
        }

        rmdir($directory);
    }
}
