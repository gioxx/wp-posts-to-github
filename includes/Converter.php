<?php

namespace POTOGH;

use League\HTMLToMarkdown\HtmlConverter;

class Converter
{
    private HtmlConverter $htmlConverter;

    public function __construct(?HtmlConverter $htmlConverter = null)
    {
        $this->htmlConverter = $htmlConverter ?? new HtmlConverter([
            'strip_tags' => true,
            'header_style' => 'atx',
        ]);
    }

    public function convert(string $html): string
    {
        return trim($this->htmlConverter->convert($html));
    }
}
