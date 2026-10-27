<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

/**
 * The parts of a package, as the XML they hold.
 *
 * A `.docx` and a `.pptx` are both zips, and what a claim about either of them is about is
 * what was written into it: a colour, a weight, a size.
 */
final class Package
{
    /**
     * A part of a package, as the XML it holds.
     */
    public static function part(string $path, string $name): string
    {
        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            throw PackageNotReadable::at($path);
        }

        $part = (string) $zip->getFromName($name);
        $zip->close();

        return $part;
    }
}
