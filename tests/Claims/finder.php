<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Finder\MemoryFinder;
use MarkdownOffice\Documentation\Reference\Code;
use MarkdownOffice\Documentation\Reference\Pages;
use MarkdownOffice\Documentation\Tests\Support\Deck;
use MarkdownOffice\Documentation\Tests\Support\Directory;
use MarkdownOffice\Documentation\Tests\Support\Document;
use MarkdownOffice\Documentation\Tests\Support\Package;
use MarkdownOffice\Documentation\Tests\Support\Refusal;
use MarkdownOffice\Documentation\Tests\Support\Site;
use MarkdownPresentation\Exception\UnservedTemplate;
use MarkdownPresentation\Files\FileFinder as SlidesFileFinder;
use MarkdownPresentation\Theme\ThemeResolver as SlidesThemeResolver;
use MarkdownWord\Exception\ThemeNotFound;
use MarkdownWord\Files\DiskFinder as WordDiskFinder;
use MarkdownWord\Files\FileFinder as WordFileFinder;
use MarkdownWord\MarkdownToWord;
use MarkdownWord\Parser\CommonMarkParser;
use MarkdownWord\Template\TemplateFile;
use MarkdownWord\Theme\ThemeResolver as WordThemeResolver;

/**
 * The claims the pages make about the finder.
 *
 * A finder is where a document's files come from, and what the pages say about it is a set of
 * rules: a finder answers and does not refuse, the scope check stays with the library, and
 * the two halves' finders have the same shape. A rule is a sentence rather than a name, so
 * each claim here is the sentence and the behaviour it is about — and the second half is
 * what fails when the library stops behaving that way.
 *
 * The two interfaces having the same shape is the claim that matters most: it is what lets
 * one adapter serve both packages, and it is the one a change to either half could break
 * without anybody noticing. So it is checked here, in the suite that already reads the code
 * of both.
 */

/**
 * The house theme's file, as a finder names it.
 */
const HOUSE_THEME_FILE = 'house/theme.yml';

/**
 * What that file holds: a theme with a name and nothing else.
 */
const HOUSE_THEME_YAML = "name: House\n";

/**
 * The names a marked block of a vocabulary lists.
 *
 * @return list<string>
 */
function finderNames(string $vocabulary): array
{
    $marked = Pages::of(Site::root() . '/content')['marked'][$vocabulary] ?? [];

    return $marked === [] ? [] : $marked[0]->names;
}

it('gives the two finders the same shape', function () {
    expect(Code::shape(SlidesFileFinder::class))->toBe(Code::shape(WordFileFinder::class));
});

it('lists every method of a finder', function () {
    expect(finderNames('finder-methods'))->toBe(Code::finderMethods(WordFileFinder::class));
});

it('lists every role a finder resolves a name in', function () {
    expect(finderNames('finder-roles'))->toBe(Code::finderRoles(WordDiskFinder::class));
});

it('counts the methods of a finder as the code does', function () {
    says(Site::FILES, words(\count(Code::finderMethods(WordFileFinder::class))) . ' methods');
});

it('counts the roles as the code does', function () {
    says(Site::FILES, words(\count(Code::finderRoles(WordDiskFinder::class))) . ' roles');
});

it('says that a finder answers and does not refuse', function () {
    says(Site::FILES, 'A finder answers; it does not refuse');
});

it('answers null for a file that is not there, and refuses nothing', function () {
    $finder = new WordDiskFinder();

    expect($finder->read(HOUSE_THEME_FILE, 'theme'))->toBeNull();
    expect($finder->path(HOUSE_THEME_FILE, 'theme'))->toBeNull();
    expect($finder->files('house'))->toBe([]);
    expect($finder->themes())->toBe([]);
});

it('says that the scope check stays with the library', function () {
    says(Site::FILES, 'The scope check therefore stays with the library');
});

it('refuses a path the finder answered, in the library\'s own words', function () {
    $root = Directory::make('finder');
    $elsewhere = Directory::make('finder');

    \mkdir($elsewhere . '/house', 0o777, true);
    \file_put_contents($elsewhere . '/' . HOUSE_THEME_FILE, HOUSE_THEME_YAML);

    // The finder answers the path of a theme outside the directories this render may
    // read, and the library is the one that refuses it.
    $resolver = new WordThemeResolver(
        workingDirectory: $root,
        files: new WordDiskFinder(theme: [$elsewhere]),
    );

    expect(static fn (): mixed => $resolver->resolve('house'))
        ->toThrow(ThemeNotFound::class, 'is outside the directories a document may name');

    Directory::remove($root);
    Directory::remove($elsewhere);
});

it('draws the same document from a theme in a finder as from a theme in a directory', function () {
    $theme = HOUSE_THEME_YAML;
    $styles = "heading.1:\n  name: Georgia\n  bold: true\n  size: 20\n  color: \"1F3864\"\n";
    $markdown = "---\ntheme: house\n---\n\n# The house style\n\nA paragraph.\n";

    $root = Directory::make('finder');

    \mkdir($root . '/themes/house', 0o777, true);
    \file_put_contents($root . '/themes/house/theme.yml', $theme);
    \file_put_contents($root . '/themes/house/styles.yml', $styles);

    $fromDirectory = Document::path($markdown, $root);
    $fromFinder = Document::path($markdown, null, new MemoryFinder([
        HOUSE_THEME_FILE => $theme,
        'house/styles.yml' => $styles,
    ]));

    // The parts rather than the bytes: a zip stores timestamps, and two correct
    // conversions of the same input differ most of the time.
    expect(Package::part($fromFinder, 'word/document.xml'))
        ->toBe(Package::part($fromDirectory, 'word/document.xml'));

    Directory::remove($root);
});

it('draws the same deck from a theme in a finder as from a theme in a directory', function () {
    $theme = HOUSE_THEME_YAML;
    $layouts = "layouts:\n  title: Title Slide\n  content: Title and Content\n";
    $markdown = "---\ntheme: house\n---\n\n# The house style\n\n## A deck\n\nWords.\n";

    $root = Directory::make('finder');
    $elsewhere = Directory::make('finder');

    \mkdir($root . '/themes/house', 0o777, true);
    \file_put_contents($root . '/themes/house/theme.yml', $theme);
    \file_put_contents($root . '/themes/house/layouts.yml', $layouts);

    $fromDirectory = Deck::slides($markdown, $root);
    $fromFinder = Deck::slides($markdown, $elsewhere, new MemoryFinder([
        HOUSE_THEME_FILE => $theme,
        'house/layouts.yml' => $layouts,
    ]));

    expect($fromFinder)->toBe($fromDirectory);

    Directory::remove($root);
    Directory::remove($elsewhere);
});

it('resolves a theme named in code once', function () {
    $finder = MemoryFinder::house();

    $converter = new MarkdownToWord(
        "# The house style\n",
        parser: CommonMarkParser::withFrontMatter(),
        files: $finder,
    );

    $converter->withTheme('house')->save(\sys_get_temp_dir() . '/markdown-office-finder-' . \bin2hex(\random_bytes(6)) . '.docx');

    expect($finder->reads(HOUSE_THEME_FILE))->toBe(1);
});

it('names the themes that resolve when a theme does not', function () {
    $resolver = new WordThemeResolver(files: MemoryFinder::house());

    $message = Refusal::of(static fn (): string => $resolver->resolve('nowhere')->name());

    expect($message)->toContain('There is no theme called "nowhere"');
    expect($message)->toContain('house');
});

it('says that a master the source cannot serve is refused by name', function () {
    says(Site::FILES, 'A master the source cannot serve is refused by name');
});

it('refuses a master the source cannot serve, rather than drawing without it', function () {
    // A source that lists the master and cannot hand it over: the listing is what tells
    // a theme that carries no master from a source that cannot serve one.
    $finder = new class implements SlidesFileFinder {
        public function read(string $name, ?string $role = null): ?string
        {
            return $name === HOUSE_THEME_FILE ? HOUSE_THEME_YAML : null;
        }

        public function path(string $name, ?string $role = null): ?string
        {
            return null;
        }

        public function themes(): array
        {
            return ['house'];
        }

        public function files(string $theme): array
        {
            return ['template.pptx', 'theme.yml'];
        }
    };

    $resolver = new SlidesThemeResolver(files: $finder);

    expect(static fn (): mixed => $resolver->resolve('house'))
        ->toThrow(UnservedTemplate::class, 'cannot serve');
});

it('resolves a theme as a theme, a picture as a picture and a document as a document', function () {
    $finder = new MemoryFinder([
        HOUSE_THEME_FILE => HOUSE_THEME_YAML,
        'logo.png' => (string) \base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        ),
    ]);

    $document = Document::path(
        "---\ntheme: house\n---\n\n::image[Logo]{src=\"logo.png\"}\n",
        null,
        $finder,
    );

    expect($finder->askedFor(HOUSE_THEME_FILE))->toBe('theme');
    expect($finder->askedFor('logo.png'))->toBe('picture');

    // The picture the finder answered is the picture in the document.
    expect(Package::part($document, 'word/media/section_image1.png'))->not->toBe('');

    $template = new TemplateFile(files: $finder);

    $message = Refusal::of(static fn (): string => $template->resolve('report.dotx'));

    expect($message)->toContain('report.dotx');
    expect($finder->askedFor('report.dotx'))->toBe('document');
});

it('serves both halves with one adapter', function () {
    $finder = MemoryFinder::house();

    expect($finder)->toBeInstanceOf(WordFileFinder::class);
    expect($finder)->toBeInstanceOf(SlidesFileFinder::class);

    $document = "---\ntheme: house\n---\n\n# The house style\n";
    $deck = "---\ntheme: house\n---\n\n# The house style\n\n---\n\n## A deck\n";

    expect(Document::body($document, null, $finder))->toContain('The house style');
    expect(Deck::slides($deck, '.', $finder))->toHaveCount(2);
});
