<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;

/**
 * A theme's master, built rather than carried.
 *
 * A master is the `template.pptx` beside a theme's file, and its layouts are what say where
 * the boxes of a role are put. The layouts written here carry no placeholders, which is what
 * a layout the library cannot draw in looks like: Office's own *Blank* has none either, and
 * a role mapped onto one keeps the library's own geometry.
 */
final class Master
{
    /**
     * A master with a layout of each of the given names.
     *
     * @param list<string> $layouts The names the layouts carry.
     */
    public static function of(array $layouts): string
    {
        $presentation = new PhpPresentation();
        $master = $presentation->getAllMasterSlides()[0];

        foreach ($layouts as $name) {
            $master->createSlideLayout()->setLayoutName($name);
        }

        $path = \sys_get_temp_dir() . '/markdown-office-master-' . \bin2hex(\random_bytes(6)) . '.pptx';

        IOFactory::createWriter($presentation, 'PowerPoint2007')->save($path);

        return $path;
    }
}
