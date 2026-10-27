<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

/**
 * A theme of a deck's or a document's own, in a directory of its own.
 *
 * The named text styles live in a theme, so a claim about them needs a theme that has
 * them — and the themes the packages ship carry none.
 */
final class Theme
{
    /**
     * A directory holding a theme of the slides half's shape, and the directory it is in.
     *
     * `$textStyles` is the theme's `text-styles.yml`, written as YAML because the file is
     * what the library reads, and null for a theme with no named styles at all.
     *
     * A `layouts.yml` as well, or the theme maps no layout and the deck is refused before
     * it is drawn.
     */
    public static function slides(?string $textStyles = null): string
    {
        $root = self::directory();

        \file_put_contents($root . '/themes/mine/theme.yml', "name: Mine\n");
        \file_put_contents(
            $root . '/themes/mine/layouts.yml',
            "layouts:\n  content: Title and Content\n",
        );

        if ($textStyles !== null) {
            \file_put_contents($root . '/themes/mine/text-styles.yml', $textStyles);
        }

        return $root;
    }

    /**
     * A directory holding a theme of the Word half's shape, and the directory it is in.
     *
     * `$styles` is the theme's `styles.yml`, written as YAML because the file is what the
     * library reads.
     */
    public static function word(?string $styles = null): string
    {
        $root = self::directory();

        \file_put_contents($root . '/themes/mine/theme.yml', "name: Mine\n");

        if ($styles !== null) {
            \file_put_contents($root . '/themes/mine/styles.yml', $styles);
        }

        return $root;
    }

    /**
     * A directory of a theme, removed again.
     */
    public static function remove(string $root): void
    {
        \exec(\sprintf('rm -rf %s', \escapeshellarg($root)));
    }

    /**
     * An empty directory of themes, and the directory it is in.
     */
    private static function directory(): string
    {
        $root = \sys_get_temp_dir() . '/markdown-office-theme-' . \bin2hex(\random_bytes(6));

        \mkdir($root . '/themes/mine', 0o777, true);

        return $root;
    }
}
