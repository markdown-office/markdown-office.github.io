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
     * A deck that names a theme of its own and writes a word in a named style.
     *
     * The deck every claim about a named style is drawn from: the theme is named in the
     * deck's own front matter, and the word is written in the style the theme gives.
     */
    public const DECK = "---\ntheme: mine\n---\n\nResilience :brand[Bakery] is open.\n";

    /**
     * A theme's named styles, with one style in them.
     *
     * The style is the one the deck above writes in, and it is red and bold, so that a
     * claim can look for a colour and a weight and find both.
     */
    public const BRAND = "brand:\n  color: FF0000\n  bold: true\n";

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
     * The message the library gives for a deck drawn against a theme of the slides half.
     *
     * The theme is written, the deck is drawn against it and the theme is taken away
     * again, whatever the deck says: a theme left behind is a theme the next test finds.
     */
    public static function slidesRefusal(string $markdown, ?string $textStyles = null): string
    {
        return Directory::during(
            self::slides($textStyles),
            static fn (string $root): string => Deck::refusal($markdown, $root),
        );
    }

    /**
     * The slide a deck is drawn as, against a theme of the slides half.
     */
    public static function slidesSlide(string $markdown, string $textStyles): string
    {
        return Directory::during(
            self::slides($textStyles),
            static fn (string $root): string => Deck::slide($markdown, $root),
        );
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

        if ($styles !== null) {
            \file_put_contents($root . '/themes/mine/styles.yml', $styles);
        }

        return $root;
    }

    /**
     * The message the library gives for a document drawn against a theme of the Word half.
     */
    public static function wordRefusal(string $markdown, string $styles): string
    {
        return Directory::during(
            self::word($styles),
            static fn (string $root): string => Document::refusal($markdown, $root),
        );
    }

    /**
     * A directory holding a theme of the slides half's shape, and the directory it is in.
     */
    private static function directory(): string
    {
        $root = Directory::make('theme');

        \mkdir($root . '/themes/mine', 0o777, true);
        \file_put_contents($root . '/themes/mine/theme.yml', "name: Mine\n");

        return $root;
    }
}
