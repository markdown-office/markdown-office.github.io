<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Finder;

use MarkdownPresentation\Files\FileFinder as SlidesFileFinder;
use MarkdownWord\Files\FileFinder as WordFileFinder;

/**
 * A finder with its files in memory rather than on disk.
 *
 * It implements the two finders of the suite at once, which is the whole reason there
 * is one shape rather than two: the two interfaces have the same methods, so a program
 * that keeps its themes in a database writes the adapter once and hands it to whichever
 * half it is rendering with.
 *
 * The source this stands for is the plugin that keeps its themes, its templates and its
 * pictures in a database and never had a directory to hand over. It answers the contents
 * and no path, so the library stages what it cannot be handed as bytes — a `.dotx`, a
 * theme's master — into a file of its own.
 */
final class MemoryFinder implements WordFileFinder, SlidesFileFinder
{
    /** @var array<string, int> */
    private array $reads = [];

    /** @var array<string, string|null> */
    private array $roles = [];

    /**
     * @param array<string, string> $files The files it holds, by name.
     */
    public function __construct(private readonly array $files = [])
    {
    }

    /**
     * A finder holding the site's own theme, which is in no directory at all.
     *
     * The theme is here rather than under `themes/` on purpose: an example that named a
     * theme beside it would be an example of the disk default, and this one is about the
     * other kind.
     */
    public static function house(): self
    {
        return new self([
            'house/theme.yml' => <<<'YAML'
                name: House
                description: The site's own theme, which lives in a finder rather than in a directory.
                author: markdown-office
                YAML,
            'house/styles.yml' => <<<'YAML'
                heading.1:
                  name: Georgia
                  bold: true
                  size: 20
                  color: "1F3864"
                paragraph:
                  name: Calibri
                  size: 11
                  space: { after: 160 }
                YAML,
            'house/layouts.yml' => <<<'YAML'
                layouts:
                  title: Title Slide
                  content: Title and Content
                  section: Section Header
                  blank: Blank
                YAML,
        ]);
    }

    public function read(string $name, ?string $role = null): ?string
    {
        $this->roles[$name] = $role;
        $this->reads[$name] = ($this->reads[$name] ?? 0) + 1;

        return $this->files[$name] ?? null;
    }

    /**
     * The path of a file a name identifies, when this finder holds it as a file.
     *
     * Null, always: this finder holds bytes and not files, so the library stages what it
     * cannot be handed as bytes.
     */
    public function path(string $name, ?string $role = null): ?string
    {
        $this->roles[$name] = $role;

        return null;
    }

    public function themes(): array
    {
        $names = [];

        foreach (\array_keys($this->files) as $name) {
            $names[\explode('/', $name, 2)[0]] = true;
        }

        $found = \array_keys($names);

        \sort($found);

        return $found;
    }

    public function files(string $theme): array
    {
        $names = [];

        foreach (\array_keys($this->files) as $name) {
            if (\str_starts_with($name, $theme . '/')) {
                $names[\substr($name, \strlen($theme) + 1)] = true;
            }
        }

        $found = \array_keys($names);

        \sort($found);

        return $found;
    }

    /**
     * The files it holds, by name.
     *
     * @return array<string, string>
     */
    public function held(): array
    {
        return $this->files;
    }

    /**
     * How many times the contents of a name were asked for.
     *
     * The count is what says a name was resolved twice rather than once: a theme named
     * in code is resolved when the name is given, and a theme named in a document's own
     * block is resolved when the document is drawn.
     */
    public function reads(string $name): int
    {
        return $this->reads[$name] ?? 0;
    }

    /**
     * The role a name was asked for in.
     *
     * The role says which world the name is resolved in, so it is what tells a picture
     * asked for as a picture from a theme asked for as a theme.
     */
    public function askedFor(string $name): ?string
    {
        return $this->roles[$name] ?? null;
    }
}
