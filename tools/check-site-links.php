#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Checks that the built site's own links point at pages it has.
 *
 *   php tools/check-site-links.php [directory]
 *
 * A site whose links 404 is worse than no site: it looks finished, and every link in it is
 * a dead end. Nothing else says so — the build succeeds, the pages are there, and the reader
 * is the one who finds out.
 *
 * The address the site is served from is read out of the site itself — the canonical link on
 * the home page — rather than out of the settings that produced it, so the check is about the
 * artefact: a site built for the wrong address, or with links that were not rewritten for
 * the path it is mounted under, is caught by the same walk.
 *
 * `MARKDOWN_OFFICE_CANONICAL`, when it is set, is what the site is *meant* to be served
 * from, and the built site has to agree with it.
 */

require_once __DIR__ . '/lib/Autoload.php';

use MarkdownOffice\Documentation\Tool\Cli;

Cli::guard("The site's links are checked from the command line.");

$root = Cli::root();
$site = $argv[1] ?? $root . '/_site';

/**
 * The canonical link of a page, or null when the page has none.
 */
function canonicalOf(string $html): ?string
{
    if (\preg_match('/<link[^>]+rel="canonical"[^>]+href="([^"]+)"/i', $html, $match) !== 1) {
        return null;
    }

    return $match[1];
}

/**
 * Every address a page points at, as the `href` and `src` attributes carry them.
 *
 * @return list<string>
 */
function linksOf(string $html): array
{
    $found = [];

    if (\preg_match_all('/<(?:a|link|img|script|source|iframe)\b[^>]*\b(?:href|src)="([^"]*)"/i', $html, $matches)) {
        foreach ($matches[1] as $match) {
            $found[] = $match;
        }
    }

    return $found;
}

/**
 * The addresses a file names, as links and as bare addresses.
 *
 * @return list<string>
 */
function addressesOf(string $file): array
{
    $found = [];
    $read = (string) \file_get_contents($file);

    if (\preg_match_all('/\]\(([^)\s]+)\)/', $read, $matches)) {
        foreach ($matches[1] as $match) {
            $found[] = $match;
        }
    }

    if (\preg_match_all('#https?://[^\s"\'<>]+#', $read, $matches)) {
        foreach ($matches[0] as $match) {
            $found[] = \rtrim($match, '.,;:)]');
        }
    }

    return $found;
}

/**
 * The file that serves a directory.
 *
 * A directory is served by a file of this name and everything else by itself, so the
 * address of a page depends on it.
 */
const DIRECTORY_INDEX = 'index.html';

/**
 * The address a page is served at, from where it sits in the built site.
 *
 * `guides/command-line/index.html` is served at `/guides/command-line/`, and the file itself
 * is never at that address — which is why the check has to know both.
 */
function addressOf(string $relative, string $mount): string
{
    $path = \str_ends_with($relative, DIRECTORY_INDEX)
        ? \substr($relative, 0, -\strlen(DIRECTORY_INDEX))
        : $relative;

    return $mount . $path;
}

/**
 * Where an address points, in terms of the built site.
 *
 * A link to another site is not this site's business. A link to this host that is not under
 * the mount point is: it lands on a path that does not exist, which is the failure this
 * check exists for.
 *
 * @return array{path: ?string, elsewhere: bool}
 */
function resolve(string $address, string $host, string $mount, string $directory): array
{
    $parts = \parse_url($address);

    if ($parts === false) {
        return ['path' => null, 'elsewhere' => false];
    }

    $other = $parts['host'] ?? '';

    if ($other !== '' && \strtolower($other) !== \strtolower($host)) {
        return ['path' => null, 'elsewhere' => true];
    }

    $path = $parts['path'] ?? '';

    if ($path === '') {
        $path = $mount;
    }

    if (! \str_starts_with($path, '/')) {
        $path = $directory . $path;
    }

    if (! \str_starts_with($path, $mount)) {
        return ['path' => null, 'elsewhere' => false];
    }

    return ['path' => \ltrim(\substr($path, \strlen($mount)), '/'), 'elsewhere' => false];
}

/**
 * The file an address under the mount point is served from.
 *
 * A directory is served by its `index.html`, and everything else by itself.
 */
function fileFor(string $site, string $relative): string
{
    if ($relative === '' || \str_ends_with($relative, '/')) {
        $relative .= DIRECTORY_INDEX;
    }

    return $site . '/' . $relative;
}

/**
 * The pages of the built site, as their paths in it.
 *
 * @return list<string>
 */
function pagesOf(string $site): array
{
    $pages = [];
    $directory = new \RecursiveDirectoryIterator($site, \FilesystemIterator::SKIP_DOTS);
    $filter = new \RecursiveIteratorIterator($directory);

    foreach ($filter as $file) {
        if ($file->isFile() && \str_ends_with($file->getFilename(), '.html')) {
            $pages[] = \str_replace('\\', '/', \substr($file->getPathname(), \strlen($site) + 1));
        }
    }

    \sort($pages);

    return $pages;
}

if (! \is_dir($site)) {
    \fwrite(\STDERR, \sprintf("  fail  The site \"%s\" is not there. Build it first.\n", $site));
    exit(1);
}

$pages = pagesOf($site);

if ($pages === []) {
    \fwrite(\STDERR, \sprintf("  fail  The site \"%s\" has no pages in it.\n", $site));
    exit(1);
}

$home = (string) \file_get_contents($site . '/index.html');
$canonical = canonicalOf($home);

if ($canonical === null) {
    \fwrite(\STDERR, "  fail  The home page has no canonical link, so the site's address is unknown.\n");
    exit(1);
}

$host = (string) \parse_url($canonical, \PHP_URL_HOST);
$origin = (string) \parse_url($canonical, \PHP_URL_SCHEME) . '://' . $host;
$mount = (string) \parse_url($canonical, \PHP_URL_PATH);
$problems = [];
$checked = 0;

$wanted = \getenv('MARKDOWN_OFFICE_CANONICAL');

if (\is_string($wanted) && $wanted !== '') {
    if (\rtrim($wanted, '/') !== \rtrim($canonical, '/')) {
        $problems[] = \sprintf(
            'The site says it is served from %s, but MARKDOWN_OFFICE_CANONICAL says %s.',
            $canonical,
            \rtrim($wanted, '/') . '/',
        );
    }
}

foreach ($pages as $page) {
    $html = (string) \file_get_contents($site . '/' . $page);
    $address = addressOf($page, $mount);
    $own = canonicalOf($html);
    $directory = \substr($address, 0, (int) \strrpos($address, '/') + 1);

    $checked++;

    if ($own !== $origin . $address) {
        $problems[] = \sprintf(
            'The page "%s" says it is canonical at %s, but it is served at %s.',
            $page,
            $own ?? 'nothing',
            $origin . $address,
        );
    }

    foreach (linksOf($html) as $link) {
        if ($link === '' || \str_starts_with($link, '#') || \str_starts_with($link, '//')) {
            continue;
        }

        if (\preg_match('#^[a-z][a-z0-9+.-]*:#i', $link) === 1 && \parse_url($link, \PHP_URL_HOST) === null) {
            continue;
        }

        $path = resolve($link, $host, $mount, $directory);

        if ($path['elsewhere']) {
            continue;
        }

        if ($path['path'] === null) {
            $problems[] = \sprintf(
                'The page "%s" links to "%s", which is outside the path the site is served under (%s).',
                $page,
                $link,
                $mount,
            );
            continue;
        }

        $checked++;

        if (! \is_file(fileFor($site, $path['path']))) {
            $problems[] = \sprintf('The page "%s" links to "%s", which is not in the site.', $page, $link);
        }
    }
}

foreach (['llms.txt', 'pages.json', 'llms-full.txt'] as $index) {
    $file = $site . '/' . $index;

    if (! \is_file($file)) {
        $problems[] = \sprintf('The site has no "%s".', $index);
        continue;
    }

    foreach (addressesOf($file) as $address) {
        $path = resolve($address, $host, $mount, $mount);

        if ($path['elsewhere']) {
            continue;
        }

        if ($path['path'] === null) {
            $problems[] = \sprintf(
                'The file "%s" names "%s", which is outside the path the site is served under (%s).',
                $index,
                $address,
                $mount,
            );
            continue;
        }

        $checked++;

        if (! \is_file(fileFor($site, $path['path']))) {
            $problems[] = \sprintf('The file "%s" names "%s", which is not in the site.', $index, $address);
        }
    }
}

$shown = \array_slice($problems, 0, 50);

foreach ($shown as $problem) {
    \fwrite(\STDERR, '  fail  ' . $problem . "\n");
}

if (\count($problems) > \count($shown)) {
    \fwrite(\STDERR, \sprintf("  fail  ... and %d more.\n", \count($problems) - \count($shown)));
}

\printf("%d page(s), %d address(es) checked, %d without one.\n", \count($pages), $checked, \count($problems));

if ($problems !== []) {
    exit(1);
}
