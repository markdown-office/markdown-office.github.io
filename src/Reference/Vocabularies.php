<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Reference;

use MarkdownPresentation\Exception\Exception as PresentationException;
use MarkdownPresentation\Render\CommonMark\BlockTranslator;
use MarkdownPresentation\Render\Free\FreeShapes;
use MarkdownPresentation\Render\ImageConverter;
use MarkdownPresentation\Render\PresentationRenderer;
use MarkdownPresentation\Theme\LayoutRole;
use MarkdownPresentation\Theme\StyleMapping;
use MarkdownPresentation\Theme\StyleSlots;
use MarkdownPresentation\Theme\TextStyle;
use MarkdownPresentation\Theme\Theme;
use MarkdownPresentation\Theme\ThemeMapping;
use MarkdownWord\Configuration\Options;
use MarkdownWord\Configuration\Styles;
use MarkdownWord\Configuration\TextStyles;
use MarkdownWord\Exception\Exception as WordException;
use MarkdownWord\Format;
use MarkdownWord\MarkdownToWord;
use MarkdownWord\Render\Directives;
use MarkdownWord\Reverse\Options as ReverseOptions;

/**
 * The vocabularies the site lists, and where the code keeps each one.
 *
 * Every list is asked of the code rather than written down here, because a list written
 * down here is a second copy of the thing it is meant to check — and a second copy is
 * what this whole mechanism exists to remove.
 */
final class Vocabularies
{
    /**
     * Every vocabulary, keyed by the name a page marks its table with.
     *
     * @return array<string, Vocabulary>
     */
    public static function all(): array
    {
        $attributes = Code::wordDirectiveAttributes();

        return [
            'presentation.frontmatter' => new Vocabulary([
                'PresentationRenderer::DIRECTIVES' => PresentationRenderer::DIRECTIVES,
            ]),
            'presentation.layout-roles' => new Vocabulary([
                'LayoutRole::names()' => LayoutRole::names(),
            ]),
            'presentation.free-shapes' => new Vocabulary([
                'FreeShapes::names()' => FreeShapes::names(),
            ]),
            'presentation.theme-colours' => new Vocabulary([
                'Theme::COLOURS' => Theme::COLOURS,
            ]),
            'presentation.theme-keys' => new Vocabulary([
                'ThemeMapping::KEYS' => ThemeMapping::KEYS,
            ]),
            'presentation.theme-aliases' => new Vocabulary([
                'ThemeMapping::ALIASES' => self::themeAliases(),
            ]),
            'presentation.style-keys' => new Vocabulary([
                'StyleMapping::KEYS' => StyleMapping::KEYS,
            ]),
            'presentation.exceptions' => new Vocabulary([
                'MarkdownPresentation\\Exception' => self::exceptions(PresentationException::class),
            ]),
            'presentation.themes' => new Vocabulary([
                'themes/' => self::themes(PresentationRenderer::class),
            ]),
            'presentation.ceilings' => new Vocabulary(
                [
                    'PresentationRenderer::MAX_SLIDES, BlockTranslator::MAX_STEP, ImageConverter::MAX_PIXELS'
                        => ['MAX_SLIDES', 'MAX_STEP', 'MAX_PIXELS'],
                ],
                [
                    'MAX_SLIDES' => PresentationRenderer::MAX_SLIDES,
                    'MAX_STEP' => BlockTranslator::MAX_STEP,
                    'MAX_PIXELS' => ImageConverter::MAX_PIXELS,
                ],
            ),
            'word.directives' => new Vocabulary([
                'MarkdownToWord' => self::wordDirectives(),
            ]),
            'word.attributes.toc' => new Vocabulary([
                'Toc::__invoke()' => $attributes['toc'] ?? [],
            ]),
            'word.attributes.image' => new Vocabulary([
                'Image::__invoke()' => $attributes['image'] ?? [],
            ]),
            'word.attributes.textbox' => new Vocabulary([
                'DocxOnly::drawTextBox()' => $attributes['textbox'] ?? [],
            ]),
            'word.attributes.shape' => new Vocabulary([
                'DocxOnly::drawShape()' => $attributes['shape'] ?? [],
            ]),
            'word.attributes.field' => new Vocabulary([
                'DocxOnly::drawField()' => $attributes['field'] ?? [],
            ]),
            'word.frontmatter' => new Vocabulary([
                'MarkdownWord\\Document\\Frontmatter' => Code::wordFrontmatterKeys(),
            ]),
            'word.reverse-options' => new Vocabulary([
                'Reverse\\Options::toArray()' => \array_keys((new ReverseOptions())->toArray()),
            ]),
            'word.object-features' => new Vocabulary([
                // The four features the objects in a document cost a writer that cannot carry
                // them: a text box, a shape, a form field and the columns of a section. They
                // are named by the format itself, so a fifth one is a constant the class
                // grows and this list has to grow with it.
                'Format::TEXT_BOX, Format::SHAPE, Format::FORM_FIELD, Format::COLUMNS' => [
                    Format::TEXT_BOX,
                    Format::SHAPE,
                    Format::FORM_FIELD,
                    Format::COLUMNS,
                ],
            ]),
            'word.exceptions' => new Vocabulary([
                'MarkdownWord\\Exception' => self::exceptions(WordException::class),
            ]),
            'word.options' => new Vocabulary([
                'Options::toArray()' => \array_keys((new Options())->toArray()),
            ]),
            'word.themes' => new Vocabulary([
                'themes/' => self::themes(MarkdownToWord::class),
            ]),
            'style-slots' => new Vocabulary([
                'Styles::defaults()' => \array_keys(Styles::defaults()),
                'StyleSlots::names()' => StyleSlots::names(),
            ]),
            'text-style-attributes' => new Vocabulary([
                'MarkdownPresentation\\Theme\\TextStyle::ATTRIBUTES' => TextStyle::ATTRIBUTES,
                'MarkdownWord\\Configuration\\TextStyles::ATTRIBUTES' => \array_keys(TextStyles::ATTRIBUTES),
            ]),
        ];
    }

    /**
     * The spellings a theme file may use instead of DrawingML's own.
     *
     * Asked of the mapper, which owns them, rather than read out of its internals: a
     * private constant read by reflection breaks the day it is renamed, and a copy
     * written down here is the thing that drifts.
     *
     * @return list<string>
     */
    public static function themeAliases(): array
    {
        return \array_keys(ThemeMapping::aliases());
    }

    /**
     * The concrete exceptions a package throws.
     *
     * The classes in the package's own `Exception` directory, minus the base every one of
     * them extends. The directory is found through the base class rather than written
     * down, so the list is the package's own and not this class's.
     *
     * @param class-string $base
     *
     * @return list<string>
     */
    public static function exceptions(string $base): array
    {
        $directory = \dirname((string) (new \ReflectionClass($base))->getFileName());
        $namespace = \substr($base, 0, (int) \strrpos($base, '\\'));
        $names = [];

        foreach (\glob($directory . '/*.php') ?: [] as $path) {
            $short = \basename($path, '.php');

            if ($short === 'Exception') {
                continue;
            }

            $class = $namespace . '\\' . $short;

            // The directory is the package's, but a file in it that is not a class of the
            // package's own would be counted as one, so each is asked of the autoloader.
            if (!\class_exists($class) || !\is_subclass_of($class, $base)) {
                continue;
            }

            $names[] = $short;
        }

        \sort($names);

        return $names;
    }

    /**
     * The themes a package ships: the directories under its own `themes/`.
     *
     * A directory without a `theme.yml` is not a theme, which is the rule the library's
     * own resolver follows when it lists the names that resolve.
     *
     * @param class-string $class A class of the package.
     *
     * @return list<string>
     */
    public static function themes(string $class): array
    {
        $directory = self::packageRoot($class) . '/themes';
        $names = [];

        foreach (\scandir($directory) ?: [] as $entry) {
            if (\is_dir($directory . '/' . $entry) && \is_file($directory . '/' . $entry . '/theme.yml')) {
                $names[] = $entry;
            }
        }

        \sort($names);

        return $names;
    }

    /**
     * The directives the Word half draws.
     *
     * Asked of the registry, which owns the names. The registry used to be read by
     * tokenising `MarkdownToWord.php` and looking for the literals its two calls were
     * given — which finds nothing, and passes, the day the registration moves to
     * another file. A check that checks nothing is worse than no check.
     *
     * @return list<string>
     */
    public static function wordDirectives(): array
    {
        return Directives::names();
    }

    /**
     * The root of the package a class belongs to: the directory its `composer.json` is in.
     *
     * @param class-string $class
     */
    private static function packageRoot(string $class): string
    {
        $directory = \dirname((string) (new \ReflectionClass($class))->getFileName());

        while (!\is_file($directory . '/composer.json')) {
            $parent = \dirname($directory);

            if ($parent === $directory) {
                throw PackageNotFound::for($class);
            }

            $directory = $parent;
        }

        return $directory;
    }
}
