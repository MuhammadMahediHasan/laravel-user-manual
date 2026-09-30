<?php

namespace MuhammadMahediHasan\UserManual\Tests;

use Illuminate\Support\Facades\File;

trait SeedsManualContent
{
    protected function seedManual(string $root, string $prefix, string $english, string $bangla, string $material): void
    {
        foreach (['en' => [$english, 'Introduction'], 'bn' => [$bangla, 'ভূমিকা']] as $locale => [$body, $title]) {
            $directory = "{$root}/1.0/{$locale}";
            File::ensureDirectoryExists($directory);
            File::put("{$directory}/introduction.md", "# {$title}\n\n{$body}\n");
            File::put("{$directory}/material.md", "# Material\n\n{$material}\n");
            File::put(
                "{$directory}/navigation.md",
                "- [{$title}](/{$prefix}/{$locale}/introduction)\n- [Material](/{$prefix}/{$locale}/material)\n",
            );
        }
    }

    protected function deleteManualRoots(string ...$roots): void
    {
        foreach ($roots as $root) {
            if ($root !== '') {
                File::deleteDirectory($root);
            }
        }
    }
}
