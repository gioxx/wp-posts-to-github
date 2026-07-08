<?php

namespace POTOGH;

class FrontMatter
{
    public static function build(array $data): string
    {
        $lines = ['---'];
        $lines[] = 'title: "' . self::escape($data['title']) . '"';
        $lines[] = 'slug: ' . $data['slug'];
        $lines[] = 'date: ' . $data['date'];
        $lines[] = 'modified: ' . $data['modified'];
        $lines[] = 'wp_id: ' . (int) $data['wp_id'];
        $lines[] = 'categories: ' . self::yamlList($data['categories']);
        $lines[] = 'tags: ' . self::yamlList($data['tags']);
        $lines[] = 'permalink: ' . $data['permalink'];
        $lines[] = '---';

        return implode("\n", $lines) . "\n";
    }

    private static function escape(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    private static function yamlList(array $items): string
    {
        if (empty($items)) {
            return '[]';
        }

        $quoted = array_map(function (string $item): string {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $item) . '"';
        }, $items);

        return '[' . implode(', ', $quoted) . ']';
    }
}
