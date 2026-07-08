<?php

namespace POTOGH\Tests;

use PHPUnit\Framework\TestCase;
use POTOGH\Converter;

class ConverterTest extends TestCase
{
    public function test_converts_headings_and_bold(): void
    {
        $converter = new Converter();

        $result = $converter->convert('<h1>Titolo</h1><p>Ciao <strong>mondo</strong></p>');

        $this->assertSame("# Titolo\n\nCiao **mondo**", $result);
    }

    public function test_preserves_absolute_image_links(): void
    {
        $converter = new Converter();

        $result = $converter->convert('<img src="https://example.com/a.png" alt="Alt text">');

        $this->assertSame('![Alt text](https://example.com/a.png)', $result);
    }

    public function test_converts_lists(): void
    {
        $converter = new Converter();

        $result = $converter->convert('<ul><li>Uno</li><li>Due</li></ul>');

        $this->assertSame("- Uno\n- Due", $result);
    }
}
