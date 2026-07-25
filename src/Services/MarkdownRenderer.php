<?php

namespace MuhammadMahediHasan\UserManual\Services;

use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use MuhammadMahediHasan\UserManual\Support\Config;

class MarkdownRenderer
{
    private GithubFlavoredMarkdownConverter $converter;

    public function __construct()
    {
        $defaults = [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ];

        $this->converter = new GithubFlavoredMarkdownConverter(
            array_merge($defaults, Config::array('user-manual.commonmark', []))
        );
    }

    /**
     * Convert markdown to HTML using the package CommonMark configuration (HTML escaped and unsafe links blocked by default).
     *
     * @throws CommonMarkException
     */
    public function render(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }

    public function extractTitle(string $markdown): ?string
    {
        if (preg_match('/^#\s+(.+)$/m', $markdown, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
