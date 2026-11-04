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
     * A theme's named styles, as the section of a theme's file that carries them.
     *
     * The style is the one the deck above writes in, and it is red and bold, so that a
     * claim can look for a colour and a weight and find both.
     */
    public const BRAND = "textStyles:\n  brand:\n    color: FF0000\n    bold: true\n";

    /**
     * A theme's slots, as the section of a theme's file that carries them.
     */
    public const SLOTS = "styles:\n  heading.1:\n    name: Georgia\n    bold: true\n    size: 20\n    color: \"1F3864\"\n";

    /**
     * A directory holding a theme of the slides half's shape, and the directory it is in.
     *
     * `$textStyles` is the theme's `textStyles:` section, written as YAML because the
     * section is what the library reads, and null for a theme with no named styles at all.
     *
     * A theme with no `layouts:` section is drawn with the library's own mapping, so a
     * theme of this shape needs none.
     */
    public static function slides(?string $textStyles = null): string
    {
        $root = self::directory();

        \file_put_contents($root . '/themes/mine/theme.yml', "name: Mine\n" . ($textStyles ?? ''));

        return $root;
    }

    /**
     * A directory holding a theme of the slides half's shape, with a master beside it.
     *
     * `$sections` is what the theme's own file carries beside its name.
     *
     * @param list<string>|null $layouts The names the master's layouts carry, or null for a
     *                                    theme with no master at all.
     */
    public static function slidesWithMaster(string $sections, ?array $layouts): string
    {
        $root = self::directory();

        \file_put_contents($root . '/themes/mine/theme.yml', "name: Mine\n" . $sections);

        if ($layouts !== null) {
            \copy(Master::of($layouts), $root . '/themes/mine/template.pptx');
        }

        return $root;
    }

    /**
     * The slide a deck is drawn as, against a theme with a master beside it.
     *
     * `$markdown` is the deck and `$sections` is what the theme's own file carries beside
     * its name.
     *
     * @param list<string>|null $layouts The names the master's layouts carry, or null for a
     *                                    theme with no master at all.
     */
    public static function slidesMasterSlide(string $markdown, string $sections, ?array $layouts): string
    {
        return Directory::during(
            self::slidesWithMaster($sections, $layouts),
            static fn (string $root): string => Deck::slide($markdown, $root),
        );
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
     * `$styles` is the theme's `styles:` section, written as YAML because the section is
     * what the library reads.
     */
    public static function word(?string $styles = null): string
    {
        $root = self::directory();

        \file_put_contents($root . '/themes/mine/theme.yml', "name: Mine\n" . ($styles ?? ''));

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
     * A message for a theme with a file of the old format beside it.
     *
     * The file is written after the theme, because the theme's own file is what the
     * library reads first: a refusal about the file beside it is only reached when the
     * theme itself is one the library can read.
     */
    public static function slidesRetired(string $markdown, string $file, string $contents): string
    {
        return Directory::during(
            self::slides(),
            static function (string $root) use ($markdown, $file, $contents): string {
                \file_put_contents($root . '/themes/mine/' . $file, $contents);

                return Deck::refusal($markdown, $root);
            },
        );
    }

    /**
     * A message for a Word theme with a file of the old format beside it.
     */
    public static function wordRetired(string $markdown, string $file, string $contents): string
    {
        return Directory::during(
            self::word(),
            static function (string $root) use ($markdown, $file, $contents): string {
                \file_put_contents($root . '/themes/mine/' . $file, $contents);

                return Document::refusal($markdown, $root);
            },
        );
    }

    /**
     * A directory holding a theme of the slides half's shape.
     */
    private static function directory(): string
    {
        $root = Directory::make('theme');

        \mkdir($root . '/themes/mine', 0o777, true);

        return $root;
    }
}
