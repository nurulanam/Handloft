<?php

namespace App\Support;

class Html
{
    /**
     * Allowed tags for rich-text content (task descriptions), matching Quill's default toolbar output.
     */
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><s><ul><ol><li><a><blockquote><code><pre><h1><h2><h3>';

    /**
     * Reduce untrusted rich-text HTML to a safe allow-listed subset.
     *
     * This is a lightweight, dependency-free sanitizer (strip_tags + attribute
     * stripping) suitable for an internal tool. It is not a substitute for a
     * full HTML sanitizer library if this content is ever exposed publicly.
     */
    public static function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $html = strip_tags($html, self::ALLOWED_TAGS);

        // Strip any event-handler attributes (onclick=, onerror=, etc.).
        $html = preg_replace('/\son\w+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/is', '', $html) ?? $html;

        // Strip href/src attributes that use a dangerous scheme.
        $html = preg_replace_callback(
            '/\s(href|src)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/is',
            function (array $matches): string {
                $value = $matches[3] !== '' ? $matches[3] : ($matches[4] !== '' ? $matches[4] : $matches[5]);
                $normalized = strtolower(preg_replace('/[\x00-\x20]+/', '', $value) ?? $value);

                if (str_starts_with($normalized, 'javascript:') || str_starts_with($normalized, 'vbscript:') || str_starts_with($normalized, 'data:text/html')) {
                    return '';
                }

                return $matches[0];
            },
            $html
        ) ?? $html;

        $html = trim($html);

        return $html === '' ? null : $html;
    }
}
