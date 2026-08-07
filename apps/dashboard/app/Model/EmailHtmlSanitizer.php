<?php

declare(strict_types=1);

namespace ThreadMesh\Dashboard\Model;

use HTMLPurifier;
use HTMLPurifier_Config;

final class EmailHtmlSanitizer
{
    private readonly HTMLPurifier $purifier;

    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.DefinitionImpl', null);
        $config->set('Core.RemoveInvalidImg', true);
        $config->set('HTML.Allowed', implode(',', [
            'p', 'br', 'div', 'span[style]', 'strong', 'b', 'em', 'i', 'u', 's',
            'blockquote', 'pre', 'code', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tfoot', 'tr',
            'th[colspan|rowspan]', 'td[colspan|rowspan]', 'hr',
        ]));
        $config->set('CSS.AllowedProperties', [
            'background-color', 'border', 'border-collapse', 'color', 'font-family',
            'font-size', 'font-style', 'font-weight', 'line-height', 'margin',
            'margin-bottom', 'margin-left', 'margin-right', 'margin-top', 'padding',
            'padding-bottom', 'padding-left', 'padding-right', 'padding-top',
            'text-align', 'text-decoration', 'white-space', 'width',
        ]);

        $this->purifier = new HTMLPurifier($config);
    }

    public function sanitize(string $html): string
    {
        return $this->purifier->purify($html);
    }
}
