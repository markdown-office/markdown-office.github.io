#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Checks that each sibling repository's README links to this site's command-line page.
 *
 *   php tools/check-readme-links.php ../markdown-word ../markdown-presentation ../mdoffice
 *
 * The command line left the READMEs and moved into `content/guides/command-line.md`, so a
 * README that does not point at it leaves a reader with no way to find it. This is the
 * guard against that: it names the file and the link that is missing, and exits non-zero.
 *
 * The URL is read out of the site's own page rather than written here, so a page that
 * moves does not leave this script pointing at a page that is no longer there. The host
 * comes from `MARKDOWN_OFFICE_CANONICAL` — the variable Eleventy reads — so a preview
 * build is checked against its own address.
 *
 * A repository that is not checked out is a failure, not a skip. A link check that passes
 * because it found nothing to check is worse than no link check at all.
 */

require_once __DIR__ . '/lib/Autoload.php';

use MarkdownOffice\Documentation\Tool\Cli;

Cli::guard('The README links are checked from the command line.');

$root = Cli::root();
$paths = \array_slice($argv, 1);

if ($paths === []) {
    \fwrite(\STDERR, "Usage: php tools/check-readme-links.php <repository> [<repository> …]\n");
    \fwrite(\STDERR, "Each argument is a checkout of a repository whose README should link to the command-line page.\n");
    exit(1);
}

/**
 * The command-line page's own address, from the page and the site's canonical host.
 *
 * Both halves are read rather than written, because a copy of either can go stale: the
 * page's permalink changes when the page moves, and the host changes with the build.
 */
function commandLineUrl(string $root): string
{
    $page = $root . '/content/command-line/basic.md';

    if (! \is_file($page)) {
        throw new RuntimeException(\sprintf('The page "%s" is not there.', $page));
    }

    $read = (string) \file_get_contents($page);

    if (! \preg_match('/^permalink:\s*(\S+)\s*$/m', $read, $permalink)) {
        throw new RuntimeException(\sprintf('The page "%s" has no permalink.', $page));
    }

    $host = \getenv('MARKDOWN_OFFICE_CANONICAL');

    if (! \is_string($host) || $host === '') {
        $config = $root . '/eleventy.config.js';

        if (! \is_file($config)) {
            throw new RuntimeException(\sprintf('The file "%s" is not there.', $config));
        }

        $source = (string) \file_get_contents($config);

        // The default the site falls back to, which is the one the site publishes under.
        if (! \preg_match('/MARKDOWN_OFFICE_CANONICAL\s*\?\?\s*"([^"]+)"/', $source, $published)) {
            throw new RuntimeException(\sprintf('The file "%s" does not say what the canonical host is.', $config));
        }

        $host = $published[1];
    }

    return \rtrim($host, '/') . $permalink[1];
}

/**
 * An address as a comparison sees it: the host in lower case without its `www.`, and the
 * path without a trailing slash, a query or a fragment.
 *
 * The query and the fragment are dropped because a link to a section of the page is a
 * link to the page, and the trailing slash is dropped because `…/command-line` and
 * `…/command-line/` are the same page and only one of them is written.
 */
function normalise(string $url): string
{
    $parts = \parse_url(\trim($url));

    if ($parts === false || ! isset($parts['host'])) {
        return \rtrim(\trim($url), '/');
    }

    $host = \strtolower($parts['host']);
    $host = \str_starts_with($host, 'www.') ? \substr($host, 4) : $host;

    return $host . \rtrim($parts['path'] ?? '', '/');
}

/**
 * Every address a Markdown file points at.
 *
 * The three Markdown forms are read as what they are, and a bare address in the prose is
 * read as one, because a README may link to the page in any of the four.
 *
 * @return list<string>
 */
function links(string $markdown): array
{
    $found = [];

    $patterns = [
        // [text](target)
        '/\[[^\]]*\]\(\s*<?([^)>\s]+)>?/',
        // [label]: target
        '/^\s*\[[^\]]+\]:\s*<?([^\s>]+)>?/m',
        // <https://example.com>
        '/<(https?:\/\/[^>\s]+)>/',
    ];

    foreach ($patterns as $pattern) {
        if (\preg_match_all($pattern, $markdown, $matches)) {
            foreach ($matches[1] as $match) {
                $found[] = $match;
            }
        }
    }

    // A bare address in the prose. The trailing punctuation of a sentence is not part of
    // the address, and a link at the end of a line brings its closing bracket with it.
    if (\preg_match_all('~https?://[^\s<>"\'`]+~', $markdown, $matches)) {
        foreach ($matches[0] as $match) {
            $found[] = \rtrim($match, '.,;:!?)]');
        }
    }

    return $found;
}

$target = null;

try {
    $target = commandLineUrl($root);
} catch (RuntimeException $e) {
    \fwrite(\STDERR, '  fail  ' . $e->getMessage() . "\n");
    exit(1);
}

$wanted = normalise($target);
$problems = [];
$checked = 0;

foreach ($paths as $path) {
    $readme = \rtrim($path, '/') . '/README.md';

    if (! \is_dir($path)) {
        $problems[] = \sprintf('The repository "%s" is not there.', $path);
        continue;
    }

    if (! \is_file($readme)) {
        $problems[] = \sprintf('The repository "%s" has no README.md.', $path);
        continue;
    }

    $checked++;
    $found = false;

    foreach (links((string) \file_get_contents($readme)) as $link) {
        if (normalise($link) === $wanted) {
            $found = true;
            break;
        }
    }

    if ($found) {
        \printf("  ok    %s links to %s\n", $readme, $target);
        continue;
    }

    $problems[] = \sprintf('The file "%s" has no link to %s.', $readme, $target);
}

foreach ($problems as $problem) {
    \fwrite(\STDERR, '  fail  ' . $problem . "\n");
}

\printf("%d README(s) checked, %d without the link.\n", $checked, \count($problems));

if ($problems !== []) {
    exit(1);
}
