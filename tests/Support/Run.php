<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

/**
 * The run of a document or a slide that holds a given text.
 *
 * The run rather than the whole of it, because a body carries the words around the one a
 * claim is about, and those are the ones that did not change.
 */
final class Run
{
    /**
     * The run that holds the text, as the XML it holds.
     *
     * @param string $xml    The document or the slide, as the XML it holds.
     * @param string $text   The text to find.
     * @param string $letter The namespace the run is written in: `w` or `a`.
     */
    public static function of(string $xml, string $text, string $letter): string
    {
        \preg_match_all('#<' . $letter . ':r(?: [^>]*)?>.*?</' . $letter . ':r>#s', $xml, $matches);

        foreach ($matches[0] as $run) {
            $pattern = '#<' . $letter . ':t(?: [^>]*)?>(?:<!\[CDATA\[)?'
                . \preg_quote($text, '#') . '(?:\]\]>)?</' . $letter . ':t>#';

            if (\preg_match($pattern, $run) === 1) {
                return $run;
            }
        }

        return '';
    }
}
