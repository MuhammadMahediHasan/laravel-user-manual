<?php

namespace MuhammadMahediHasan\UserManual\Services;

use Illuminate\Support\Facades\File;

class NavigationParser
{
    /**
     * @return list<array{title: string, url: string, external: bool, depth: int}>
     */
    public function parse(string $navPath): array
    {
        if (! File::exists($navPath)) {
            return [];
        }

        $items = [];

        foreach (File::lines($navPath) as $line) {
            if (! preg_match('/^(\s*)- \[([^\]]+)\]\(([^)]+)\)/', $line, $matches)) {
                continue;
            }

            $items[] = [
                'title' => $matches[2],
                'url' => $matches[3],
                'external' => str_starts_with($matches[3], 'http'),
                'depth' => (int) (strlen($matches[1]) / 2),
            ];
        }

        return $items;
    }

    /**
     * @param  list<array{title: string, url: string, external: bool, depth: int}>  $items
     * @return list<array{title: string, url: string, external: bool, children: list<mixed>}>
     */
    public function buildTree(array $items): array
    {
        $tree = [];
        $references = [0 => &$tree];

        foreach ($items as $item) {
            $depth = $item['depth'];
            $node = [
                'title' => $item['title'],
                'url' => $item['url'],
                'external' => $item['external'],
                'children' => [],
            ];

            $references[$depth][] = $node;
            $lastIndex = array_key_last($references[$depth]);
            $references[$depth + 1] = &$references[$depth][$lastIndex]['children'];

            foreach (array_keys($references) as $level) {
                if ($level > $depth + 1) {
                    unset($references[$level]);
                }
            }
        }

        return $tree;
    }
}
