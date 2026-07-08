<?php

namespace POTOGH;

class PathBuilder
{
    public static function build(string $baseFolder, string $year, string $slug): string
    {
        $baseFolder = trim($baseFolder, "/ \t\n\r\0\x0B");

        return sprintf('%s/%s/%s.md', $baseFolder, $year, $slug);
    }
}
