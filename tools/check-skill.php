#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Checks that the skill is this site's documentation, and that it has not fallen behind it.
 *
 *   php tools/check-skill.php
 *
 * The skill is a copy of this site's documentation made by the same build that makes the
 * site, and the whole point of making it that way is that a copy cannot drift from what it
 * was made from. That is a property of the build rather than of the copy, and a property
 * the build does not enforce is a property nothing enforces: a hand-edited reference file
 * is still a hand-edited reference file, and a page added to the site and not carried is
 * still missing.
 *
 * So this asks the built tree what it should have said:
 *
 *  - every reference file is the page it carries, with its frontmatter off and each
 *    example replaced by exactly one expansion — so a reference file is the documentation
 *    and not a paraphrase of it
 *  - every page of the sections the bundle claims to cover is carried, so a page added to
 *    the site and not carried fails the build
 *  - every file in the bundle is named in the catalogue, and every file the catalogue names
 *    is in the bundle, so nothing is downloaded that is not there and nothing there is
 *    never downloaded
 *  - every path a reference file names exists, so an agent is not sent to a file that is
 *    not there
 *  - the skill's frontmatter names it and says when to use it, because a skill without a
 *    description is not advertised to the model
 *  - the scripts are the ones the library ships, byte for byte, because a copy that has
 *    drifted is a copy that is wrong
 *  - every path the install page names exists in the built tree, because a URL that does
 *    not resolve is the failure a reader meets first
 *  - the library's README points at the skill page
 *
 * The reference files are checked against the *mirror* the site build wrote rather than
 * against the page's source, because the mirror is what the generator had to work from: a
 * difference between them is a difference the copy cannot carry.
 */

\error_reporting(\E_ALL & ~\E_DEPRECATED & ~\E_USER_DEPRECATED);
\ini_set('display_errors', 'stderr');

if (\PHP_SAPI !== 'cli') {
    \fwrite(\STDERR, "The skill is checked from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/lib/Autoload.php';

use MarkdownOffice\Documentation\Tool\Cli;
use MarkdownOffice\Documentation\Tool\Report;

Cli::guard('The skill is checked from the command line.');
Cli::autoload();

$root = Cli::root();
$site = $root . '/_site';
$bundle = $site . '/skills/mdword';

if (!\is_dir($bundle)) {
    \fwrite(\STDERR, \sprintf("There is no skill at %s. Build the site first.\n", $bundle));
    exit(1);
}

$report = new Report();

/**
 * The manifest: the pages the bundle carries, and the files they are carried in.
 *
 * @return array{sections: list<string>, references: list<array{file: string, page: string}>}
 */
function manifest(string $root): array
{
    $file = $root . '/skill/mdword/pages.json';

    if (!\is_file($file)) {
        throw new RuntimeException('skill/mdword/pages.json could not be read.');
    }

    $parsed = \json_decode((string) \file_get_contents($file), true);

    if (!\is_array($parsed)) {
        throw new RuntimeException('skill/mdword/pages.json could not be read as JSON.');
    }

    return [
        'sections' => (array) ($parsed['sections'] ?? []),
        'references' => (array) ($parsed['references'] ?? []),
    ];
}

/**
 * Every page of the site, with its section.
 *
 * @return array<string, string>
 */
function pages(string $content): array
{
    $found = [];

    foreach (\glob($content . '/*/*.md') ?: [] as $file) {
        $source = (string) \file_get_contents($file);

        if (\preg_match('/^section:\s*(.+)$/m', $source, $section) !== 1) {
            continue;
        }

        $found[\str_replace($content . '/', '', $file)] = \trim($section[1]);
    }

    return $found;
}

/**
 * Every file in a directory, recursively.
 *
 * @return list<string>
 */
function files(string $directory): array
{
    $found = [];

    foreach (\glob($directory . '/*') ?: [] as $path) {
        if (\is_dir($path)) {
            $found = \array_merge($found, files($path));

            continue;
        }

        $found[] = $path;
    }

    return $found;
}

/**
 * The address a page is served at, as its own frontmatter declares it.
 *
 * The mirror of a page is written beside its HTML, so it is at the page's permalink and
 * not at the page's source path — which are not the same thing: a page in `general/` may
 * be served at `/directives/`.
 */
function permalink(string $page): string
{
    $source = (string) \file_get_contents($page);

    if (\preg_match('/^permalink:\s*(\S+)\s*$/m', $source, $found) !== 1) {
        throw new RuntimeException(\sprintf('The page "%s" has no permalink.', $page));
    }

    return $found[1];
}

/**
 * The address the site is served from.
 *
 * Read out of the configuration rather than written down, because the configuration is what
 * Eleventy reads and a check that disagrees with it is a check of a different site.
 */
function canonical(string $root): string
{
    $given = \getenv('MARKDOWN_OFFICE_CANONICAL');

    if (\is_string($given) && $given !== '') {
        return \rtrim($given, '/');
    }

    $source = (string) \file_get_contents($root . '/eleventy.config.js');

    if (\preg_match('/MARKDOWN_OFFICE_CANONICAL\s*\?\?\s*"([^"]+)"/', $source, $found) !== 1) {
        throw new RuntimeException('The configuration does not say what the canonical host is.');
    }

    return \rtrim($found[1], '/');
}

/**
 * The body of a page, with its frontmatter off.
 */
function body(string $source): string
{
    $parts = \preg_match('/^---\r?\n[\s\S]*?\r?\n---\r?\n?([\s\S]*)$/', $source, $found);

    return $parts === 1 ? $found[1] : $source;
}

/**
 * The example blocks of a page, and the prose between them.
 *
 * @return array{examples: list<string>, prose: list<string>}
 */
function blocks(string $source): array
{
    $parts = \preg_split(
        '/\{%\s*example[^%]*%\}\n?([\s\S]*?)\n?\{%\s*endexample\s*%\}/',
        body($source),
        -1,
        \PREG_SPLIT_DELIM_CAPTURE,
    ) ?: [];

    $examples = [];
    $prose = [];

    foreach ($parts as $index => $part) {
        // The odd parts are the fences and the even parts are the prose around them.
        if ($index % 2 === 1) {
            $examples[] = \trim($part);
            continue;
        }

        $prose[] = \trim($part);
    }

    return ['examples' => $examples, 'prose' => $prose];
}

/**
 * The paths a reference file names.
 *
 * @return list<string>
 */
function paths(string $reference): array
{
    \preg_match_all('/`((?:examples|references|scripts)\/[^`]+)`/', $reference, $found);

    return $found[1];
}

$manifest = manifest($root);
$pages = pages($root . '/content');

// 1. Every reference file is the page it carries.
foreach ($manifest['references'] as $reference) {
    $file = $bundle . '/references/' . $reference['file'];

    if (!\is_file($file)) {
        $report->fail(\sprintf('The bundle does not carry "%s".', $reference['file']));

        continue;
    }

    $mirror = $site . '/' . \ltrim(permalink($root . '/content/' . $reference['page']), '/') . 'index.md';

    if (!\is_file($mirror)) {
        $report->fail(\sprintf('The page "%s" has no Markdown mirror to copy.', $reference['page']));

        continue;
    }

    $written = (string) \file_get_contents($file);
    $source = (string) \file_get_contents($mirror);
    $blocks = blocks($source);

    foreach ($blocks['prose'] as $piece) {
        if ($piece === '') {
            continue;
        }

        if (!\str_contains($written, $piece)) {
            $report->fail(\sprintf(
                'The reference file "%s" does not carry the text of "%s". It is a copy of the page, '
                    . 'not a paraphrase of it.',
                $reference['file'],
                $reference['page'],
            ));
        }
    }

    $expansions = \preg_match_all('/^#### Example: /m', $written);

    if ($expansions !== \count($blocks['examples'])) {
        $report->fail(\sprintf(
            'The reference file "%s" expands %d of the %d example(s) of "%s".',
            $reference['file'],
            $expansions,
            \count($blocks['examples']),
            $reference['page'],
        ));
    }
}

// 2. Every page of the sections the bundle claims to cover is carried.
$carried = \array_column($manifest['references'], 'page');

foreach ($pages as $page => $section) {
    if (!\in_array($section, $manifest['sections'], true)) {
        continue;
    }

    if (!\in_array($page, $carried, true)) {
        $report->fail(\sprintf(
            'The page "%s" is in the "%s" section and the bundle does not carry it.',
            $page,
            $section,
        ));
    }
}

// 3. The catalogue and the bundle agree.
$catalogue = \json_decode((string) \file_get_contents($site . '/skills/index.json'), true);

if (!\is_array($catalogue) || !\is_array($catalogue['skills'] ?? null)) {
    $report->fail('The catalogue at /skills/index.json is not a catalogue.');
} else {
    $entry = $catalogue['skills'][0] ?? [];

    if (($entry['name'] ?? null) !== 'mdword') {
        $report->fail('The catalogue does not carry a skill called "mdword".');
    }

    $named = (array) ($entry['files'] ?? []);
    $there = [];

    foreach (files($bundle) as $file) {
        $there[] = \str_replace($bundle . '/', '', $file);
    }

    \sort($there);

    foreach ($there as $file) {
        if (!\in_array($file, $named, true)) {
            $report->fail(\sprintf('The bundle holds "%s" and the catalogue does not name it.', $file));
        }
    }

    foreach ($named as $file) {
        if (!\in_array($file, $there, true)) {
            $report->fail(\sprintf('The catalogue names "%s" and the bundle does not hold it.', $file));
        }
    }
}

// 4. Every path a reference file names exists.
foreach (\glob($bundle . '/references/*.md') ?: [] as $file) {
    foreach (paths((string) \file_get_contents($file)) as $path) {
        if (!\file_exists($bundle . '/' . $path)) {
            $report->fail(\sprintf(
                'The reference file "%s" names "%s" and the bundle does not hold it.',
                \basename($file),
                $path,
            ));
        }
    }
}

// 5. The skill's frontmatter.
$skill = (string) \file_get_contents($bundle . '/SKILL.md');

if (\preg_match('/^---\r?\n(.*?)\r?\n---/s', $skill, $frontmatter) !== 1) {
    $report->fail('The skill has no frontmatter.');
} else {
    $head = $frontmatter[1];

    if (\preg_match('/^name:\s*(\S+)$/m', $head, $name) !== 1) {
        $report->fail('The skill has no name.');
    }

    if (\preg_match('/^description:\s*(.+)$/m', $head, $description) !== 1) {
        $report->fail('The skill has no description, so it is never advertised to the model.');
    } elseif (!\str_contains($description[1], 'Use when')) {
        $report->fail('The skill does not say when to use it.');
    }

    // Every reference the bundle carries is named in the skill, because a file nothing
    // points at is a file nobody reads.
    foreach (\glob($bundle . '/references/*.md') ?: [] as $file) {
        $name = \basename($file);

        if (!\str_contains($skill, 'references/' . $name)) {
            $report->fail(\sprintf('The skill does not point at "references/%s".', $name));
        }
    }
}

// 6. The scripts are the ones the library ships.
$library = $root . '/../markdown-word/tools';

foreach (['render-document.php', 'render-with-word.applescript', 'render-with-word.ps1', 'pdf-is-complete.sh'] as $tool) {
    $from = $library . '/' . $tool;
    $copied = $bundle . '/scripts/' . $tool;

    if (!\is_file($from)) {
        $report->fail(\sprintf('The library does not ship "%s".', $tool));

        continue;
    }

    if (!\is_file($copied)) {
        $report->fail(\sprintf('The bundle does not carry the script "%s".', $tool));

        continue;
    }

    if (\md5_file($from) !== \md5_file($copied)) {
        $report->fail(\sprintf('The bundle\'s copy of "%s" is not the one the library ships.', $tool));
    }
}

// 7. Every path the install page names exists in the built tree.
$install = (string) \file_get_contents($root . '/content/skills/index.md');

\preg_match_all('#https?://[^/\s]+/skills/([^\s`)\]",]+)#', $install, $found);

foreach ($found[1] as $path) {
    if (\str_contains($path, '/')) {
        // A path under the bundle, which is a directory rather than a file.
        if (!\file_exists($site . '/skills/' . $path)) {
            $report->fail(\sprintf('The install page names "/skills/%s" and it is not there.', $path));
        }

        continue;
    }

    if (!\file_exists($site . '/skills/' . $path)) {
        $report->fail(\sprintf('The install page names "/skills/%s" and it is not there.', $path));
    }
}

// 8. The library's README points at the skill page.
//
// The address is read out of the site's own page rather than written down, so a page that
// moves does not leave this pointing at a page that is no longer there — the pattern
// `check-readme-links.php` already uses for the command-line page.
$readme = $root . '/../markdown-word/README.md';

if (\is_file($readme)) {
    $text = (string) \file_get_contents($readme);

    if (!\str_contains($text, canonical($root) . permalink($root . '/content/skills/index.md'))) {
        $report->fail(\sprintf(
            'The library\'s README does not point at the skill page, which is at %s.',
            canonical($root) . permalink($root . '/content/skills/index.md'),
        ));
    }
}

if ($report->failed()) {
    $report->print('skill');

    exit(1);
}

\printf(
    "%d reference file(s), %d page(s) and %d script(s) checked against the bundle.\n",
    \count($manifest['references']),
    \count($pages),
    4,
);
