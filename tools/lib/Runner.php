<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tool;

use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use MarkdownOffice\CommonMark\Directive\GenericDirectiveExtension;
use MarkdownOffice\Documentation\Finder\MemoryFinder;
use MarkdownWord\MarkdownToWord;
use MarkdownWord\Parser\CommonMarkParser;
use MarkdownWord\WordToMarkdown;
use MarkdownPresentation\Document\DeckParser;
use MarkdownPresentation\Render\PresentationRenderer;
use MarkdownPresentation\Theme\ThemeResolver;

/**
 * Runs a page's example through the library it documents, and keeps what came out.
 *
 * An example names a direction rather than a renderer: `md word` builds a document from
 * Markdown, `word md` reads a document back into Markdown, and the document in the second
 * case is the example's input rather than its output. A third word on the fence — `finder` —
 * says that the example's files come from a finder rather than from the disk. The direction
 * decides what is built, which library draws it, and what the download on the page says
 * about it.
 *
 * Caching is by content hash rather than by page or by position, so the second page to show
 * the same example pays nothing, a build that changes one paragraph re-renders one example, and
 * a build that changes none re-renders none. The cost that matters is the drawing: laying a
 * document out and writing its PDF is the slowest thing a build does, and doing it again for an
 * example nobody changed is time nobody asked for.
 *
 * Artifacts are written under the site's own root, which is why the URLs handed back are
 * relative to the site: the build cleans its output by deleting one directory, and there is no
 * second copy of a preview anywhere that could survive that.
 */
final class Runner
{
    /** Markdown into a Word document. */
    public const MD_WORD = 'md-word';

    /** Markdown into a PowerPoint deck. */
    public const MD_PRESENTATION = 'md-presentation';

    /** Markdown into a Word document, its files from a finder. */
    public const MD_WORD_FINDER = 'md-word-finder';

    /** Markdown into a PowerPoint deck, its files from a finder. */
    public const MD_PRESENTATION_FINDER = 'md-presentation-finder';

    /** A Word document back into Markdown. */
    public const WORD_MD = 'word-md';

    /**
     * The directions the site builds, and the library each belongs to.
     *
     * The key is the pair a fence tag names, with the two formats separated by a dash: the
     * format the example starts in, and the format it ends in. A third word — `finder` —
     * says where the example's files come from: a finder rather than the disk.
     */
    public const DIRECTIONS = [
        self::MD_WORD => 'word',
        self::MD_PRESENTATION => 'presentation',
        self::MD_WORD_FINDER => 'word',
        self::MD_PRESENTATION_FINDER => 'presentation',
        self::WORD_MD => 'word',
    ];

    /** The document an example offers is what it produced. */
    public const ROLE_OUTPUT = 'output';

    /** The document an example offers is what it read. */
    public const ROLE_SOURCE = 'source';

    /** The file the cache is described by. */
    private const MANIFEST = '/manifest.json';

    /** The Markdown the page shows for an example: what it ran on, or what it read back. */
    private const MARKDOWN = '/example.md';

    private int $rendered = 0;
    private int $reused = 0;

    public function __construct(
        private readonly string $root,
        private readonly string $prefix,
        private readonly Preview $preview,
        private readonly string $site,
        private readonly Outline $outline = new Outline(),
    ) {}

    /**
     * @return array{rendered: int, reused: int}
     */
    public function counts(): array
    {
        return ['rendered' => $this->rendered, 'reused' => $this->reused];
    }

    /**
     * Builds one example, or says why it could not be built.
     *
     * The slug is the content hash, so the same source on two pages is one document rather than
     * two, and changing an example leaves the old artifacts behind rather than overwriting an
     * unrelated example that happens to sit at the same path.
     *
     * `$root` is the directory that is published at `$prefix`, so the two are separate: the build
     * keeps its artifacts in `_examples/` and Eleventy copies that to `/examples` in the site,
     * and neither half has to know where the other put its files.
     */
    public function run(string $direction, string $slug, string $name, string $source): Example
    {
        $language = self::DIRECTIONS[$direction] ?? 'word';
        $role = $direction === self::WORD_MD ? self::ROLE_SOURCE : self::ROLE_OUTPUT;
        $cache = \sprintf('%s/%s/%s', $this->root, $language, $slug);
        $url = \sprintf('%s/%s/%s', $this->prefix, $language, $slug);
        $fingerprint = $this->fingerprint($direction, $source);

        if (\is_file($cache . self::MANIFEST) && \is_file($cache . self::MARKDOWN)) {
            $read = (array) \json_decode((string) \file_get_contents($cache . self::MANIFEST), true);

            if (($read['input'] ?? null) === $fingerprint) {
                $this->reused++;

                return $this->read($language, $role, $name, $cache, $read);
            }
        }

        foreach (\glob($cache . '/*') ?: [] as $stale) {
            $this->remove($stale);
        }

        @\mkdir($cache, 0o777, true);

        try {
            $built = $this->build($direction, $source, $cache);
        } catch (\Throwable $error) {
            $message = \sprintf(
                'The %s example "%s" did not build: %s',
                $language === 'word' ? 'Word' : 'PowerPoint',
                $name,
                $this->oneLine($error->getMessage()),
            );

            $this->manifest($cache, input: $fingerprint, failure: $message);
            \file_put_contents($cache . self::MARKDOWN, $source);

            return new Example($language, $role, $source, $name, failure: $message);
        }

        \file_put_contents($cache . self::MARKDOWN, $built['markdown']);

        $rendered = $this->preview->pagesOf($built['document']);
        $previews = [];

        foreach ($rendered['pages'] as $page) {
            $keep = $cache . '/' . \basename($page);

            if (@\copy($page, $keep)) {
                $previews[] = $url . '/' . \basename($keep);
            }
        }

        $pdf = $rendered['pdf'] === '' ? null : $url . '/' . \pathinfo($built['document'], \PATHINFO_FILENAME) . '.pdf';

        // What is in the document, read back out of it. Shown when there is no page image to
        // show, and built either way so a machine that gains a renderer later does not have to
        // rebuild every example to get them.
        $contents = $this->outline->of($built['document']);

        $this->rendered++;
        $this->manifest(
            $cache,
            input: $fingerprint,
            document: $url . '/' . \basename($built['document']),
            pdf: $pdf,
            previews: $previews,
            contents: $contents,
        );

        return new Example(
            $language,
            $role,
            $built['markdown'],
            $name,
            $url . '/' . \basename($built['document']),
            $pdf,
            $previews,
            contents: $contents,
        );
    }

    /**
     * The document an example works with, and the Markdown the page shows for it.
     *
     * A direction that builds a document gets the Markdown it was given back. A direction that
     * reads one gets the Markdown the reader made of it — which is the point of that example,
     * and the reason its document is the input rather than the output.
     *
     * @return array{document: string, markdown: string}
     */
    private function build(string $direction, string $source, string $cache): array
    {
        if ($direction === self::WORD_MD) {
            $document = $cache . '/example.docx';

            \copy($this->document($source), $document);

            try {
                $markdown = (new WordToMarkdown($document))->convert();
            } catch (\Throwable $error) {
                // The library names the file it could not read, and that file is inside the
                // build's own directory. The page says what went wrong in terms the reader can
                // act on, which is the path the fence named.
                throw new UnreadableDocument(
                    \str_replace($document, $source, $error->getMessage()),
                    0,
                    $error,
                );
            }

            return ['document' => $document, 'markdown' => $markdown];
        }

        $document = $cache . '/example.' . (self::DIRECTIONS[$direction] === 'presentation' ? 'pptx' : 'docx');

        $files = \str_ends_with($direction, '-finder') ? $this->finder() : null;

        return [
            'document' => self::DIRECTIONS[$direction] === 'presentation'
                ? $this->presentation($source, $document, $files)
                : $this->word($source, $document, $files),
            'markdown' => $source,
        ];
    }

    /**
     * The source the example's files come from, for an example built through a finder.
     *
     * The same finder for both halves, because the two interfaces have the same shape:
     * one adapter serves both packages, which is what the page about the finder shows.
     */
    private function finder(): MemoryFinder
    {
        return MemoryFinder::house();
    }

    /**
     * The document a fence names, as a path on this machine.
     *
     * The path in the fence is relative to the site's own directory, and it is resolved rather
     * than opened: a fence is a page's own text, and a page that named a path outside the site
     * would have the build copy a file from elsewhere onto the published site.
     */
    private function document(string $source): string
    {
        $site = \realpath($this->site);
        $path = \realpath($this->site . '/' . $source);

        if ($path === false || $site === false) {
            throw new UnreadableDocument(\sprintf('There is no document at "%s".', $source));
        }

        if (! \str_starts_with($path, $site . \DIRECTORY_SEPARATOR)) {
            throw new UnreadableDocument(\sprintf('The document "%s" is not inside the site.', $source));
        }

        return $path;
    }

    /**
     * What an example's input was, in a form that changes when the input does.
     *
     * The Markdown is the input for the directions that build a document from it. For the
     * direction that reads one, the document's own bytes are the input: the path is already
     * part of the example's hash, and a document that changed under the same name has to
     * rebuild rather than be served from the cache.
     *
     * The files of a finder are part of the input as well: an example whose theme comes
     * from a finder is built from the theme's own bytes, so a theme that changed under
     * the same name has to rebuild too.
     */
    private function fingerprint(string $direction, string $source): string
    {
        if ($direction === self::WORD_MD) {
            $path = \realpath($this->site . '/' . $source);

            return $path === false ? $source : $source . "\0" . (string) \hash_file('sha256', $path);
        }

        if (\str_ends_with($direction, '-finder')) {
            return $source . "\0" . (string) \hash('sha256', \serialize($this->finder()->held()));
        }

        return $source;
    }

    /**
     * Builds a `.docx` at `$target` and answers with that path.
     *
     * `convertTo()` answers with the document's bytes rather than with where it put them, which
     * is the right answer for a caller holding the result in memory and the wrong one here: the
     * next thing this does is hand a path to the tool that draws it.
     *
     * The parser is passed rather than left to the default, because the default does not read
     * frontmatter. `CommonMarkParser::DEFAULT_FLAVOUR` is GitHub-Flavored Markdown plus directives,
     * and a document opening with `---` and an `options:` block has that block rendered as body text:
     * silently, and with the configuration it was meant to carry ignored. `withAllExtensions()` is
     * not the answer either — it does read frontmatter, but it leaves directives out, so `::toc` and
     * `:footnote` stop being directives and become the text an author wrote.
     *
     * So both, named. That is what a pipeline reading configuration out of a document wants, and the
     * page on Word configuration explains why it is not the default.
     *
     * The finder is the source of the document's files when there is one, and the theme the
     * document names is resolved through it.
     */
    private function word(string $markdown, string $target, ?MemoryFinder $files = null): string
    {
        (new MarkdownToWord($markdown, parser: new CommonMarkParser([
            GithubFlavoredMarkdownExtension::class,
            GenericDirectiveExtension::class,
            FrontMatterExtension::class,
        ]), files: $files))->save($target);

        return $target;
    }

    private function presentation(string $markdown, string $target, ?MemoryFinder $files = null): string
    {
        $deck = (new DeckParser())->parse($markdown);

        $renderer = $files === null
            ? new PresentationRenderer()
            : new PresentationRenderer(themes: new ThemeResolver(files: $files), files: $files);

        $renderer->render($deck, $target);

        return $target;
    }

    /**
     * Re-reads a cached example rather than rendering it again.
     *
     * The manifest is the record the last run wrote, not something re-derived from the files: a
     * cached example has to come back as exactly the set of files it was last time, or the cache
     * is not describing anything and the next build cannot trust it either.
     *
     * Every field the manifest records goes into the constructor, in its order. Adding one and
     * not passing it here is silent: the first build is right and every build after it drops the
     * field, which is how an example loses its document outline without anything failing.
     *
     * @param array{document?: ?string, pdf?: ?string, previews?: list<string>, failure?: ?string, contents?: list<array{part: string, heading: string, lines: list<string>}>} $read
     */
    private function read(
        string $language,
        string $role,
        string $name,
        string $cache,
        array $read,
    ): Example {
        return new Example(
            $language,
            $role,
            (string) \file_get_contents($cache . self::MARKDOWN),
            $name,
            $read['document'] ?? null,
            $read['pdf'] ?? null,
            $read['previews'] ?? [],
            $read['failure'] ?? null,
            $read['contents'] ?? [],
        );
    }

    /**
     * @param list<string> $previews
     * @param list<array{part: string, heading: string, lines: list<string>}> $contents
     */
    private function manifest(
        string $cache,
        ?string $input = null,
        ?string $document = null,
        ?string $pdf = null,
        array $previews = [],
        ?string $failure = null,
        array $contents = [],
    ): void {
        \file_put_contents(
            $cache . self::MANIFEST,
            (string) \json_encode(
                [
                    'input' => $input,
                    'document' => $document,
                    'pdf' => $pdf,
                    'previews' => $previews,
                    'contents' => $contents,
                    'failure' => $failure,
                ],
                \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES,
            ),
        );
    }

    private function remove(string $path): void
    {
        if (\is_dir($path)) {
            foreach (\glob($path . '/*') ?: [] as $child) {
                $this->remove($child);
            }

            @\rmdir($path);

            return;
        }

        @\unlink($path);
    }

    private function oneLine(string $message): string
    {
        return \trim(\preg_replace('/\s+/', ' ', $message) ?? $message);
    }
}
