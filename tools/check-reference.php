#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Checks that the reference tables of the site list what the libraries have.
 *
 *   php tools/check-reference.php
 *
 * The tables under `content/` that carry a vocabulary — the frontmatter keys, the layout
 * roles, the exceptions, the options — are written by hand, and a hand-written list is
 * correct only until the code moves. A key added to the library is not on the page, an
 * option renamed is still on it, and nothing says so: the page is a claim, and a claim
 * nobody checks is a claim nobody made.
 *
 * So this asks the code. Every vocabulary is read out of the library that owns it, and
 * the page that carries it is read for the names it lists. The two are compared in both
 * directions, because both directions are wrong in a way a reader cannot act on: a name
 * the code does not have is a page offering something that does not work, and a name the
 * code has and the page does not is a feature nobody can find.
 *
 * The pages say which of their blocks is the list of what by marking it:
 *
 *     <!-- reference: presentation.layout-roles -->
 *
 * A marker rather than a shape, because the pages are hand-written and a table whose
 * first column holds names is not something the code can be checked against: the
 * frontmatter keys and the exceptions are both two-column tables, and only the page
 * knows which is which. A marker also gives every vocabulary an address, so a
 * vocabulary no page carries is a failure of its own rather than a silence.
 *
 * A second copy of a list is a failure as well, and the reason the Word options table
 * was on two pages: two copies drift apart, and the copy nobody reads is the one that is
 * wrong. One list, one home.
 *
 * The lists are read out of the code rather than written down here, because a list
 * written down here would be a second copy of the thing this checks. The tables
 * themselves are not generated, because their other columns are prose that no code
 * holds: what a key does, what an exception means. Prose is checked by the claims in
 * `tests/`.
 *
 * What it checks:
 *
 *  - every vocabulary has exactly one page that carries it
 *  - every marker names a vocabulary that exists
 *  - the page lists every name the code has, and no name the code does not have
 *  - no other page carries a second copy of the same list
 *  - where the code keeps the same list in two places, the two agree
 *  - the numbers in the table of ceilings are the numbers in the code
 */

\error_reporting(\E_ALL & ~\E_DEPRECATED & ~\E_USER_DEPRECATED);
\ini_set('display_errors', 'stderr');

if (\PHP_SAPI !== 'cli') {
    \fwrite(\STDERR, "The reference tables are checked from the command line.\n");
    exit(1);
}

$root = \dirname(__DIR__);

if (!\is_file($root . '/vendor/autoload.php')) {
    \fwrite(\STDERR, "There is no vendor/autoload.php. Run `composer install` in documentation/.\n");
    exit(1);
}

require_once $root . '/vendor/autoload.php';

use MarkdownOffice\Documentation\Reference\Block;
use MarkdownOffice\Documentation\Reference\Pages;
use MarkdownOffice\Documentation\Reference\Vocabulary;
use MarkdownOffice\Documentation\Reference\Vocabularies;

$content = $root . '/content';

if (!\is_dir($content)) {
    \fwrite(\STDERR, \sprintf("There is no content directory at %s.\n", $content));
    exit(1);
}

$failures = [];

/**
 * A thing that is wrong, said once.
 */
function fail(string $message): void
{
    global $failures;

    $failures[] = $message;
}

/**
 * The page a block is on, as the site's own path.
 */
function where(Block $block): string
{
    return \str_replace(\dirname(__DIR__) . '/', '', $block->file) . ':' . $block->line;
}

$vocabularies = Vocabularies::all();
$marked = Pages::marked($content);
$blocks = Pages::blocks($content);

foreach ($marked as $name => $carried) {
    if (!isset($vocabularies[$name])) {
        fail(\sprintf(
            "%s marks a vocabulary \"%s\", which no code holds.",
            where($carried[0]),
            $name,
        ));
    }
}

$checked = 0;
$counted = 0;

foreach ($vocabularies as $name => $vocabulary) {
    $carried = $marked[$name] ?? [];

    if ($carried === []) {
        fail(\sprintf(
            "No page carries the vocabulary \"%s\". The code has %d name(s) for it and the site lists none.",
            $name,
            \count($vocabulary->names()),
        ));

        continue;
    }

    if (\count($carried) > 1) {
        foreach (\array_slice($carried, 1) as $block) {
            fail(\sprintf(
                "The vocabulary \"%s\" is carried by %s as well as by %s. One list, one home.",
                $name,
                where($block),
                where($carried[0]),
            ));
        }

        continue;
    }

    $block = $carried[0];
    $expected = $vocabulary->names();
    $listed = $block->names;

    if ($listed === []) {
        fail(\sprintf(
            "%s marks the vocabulary \"%s\" and there is no table or list under it.",
            where($block),
            $name,
        ));

        continue;
    }

    foreach ($expected as $one) {
        if (!\in_array($one, $listed, true)) {
            fail(\sprintf(
                "%s does not list \"%s\", which the code has.",
                where($block),
                $one,
            ));
        }
    }

    foreach ($listed as $one) {
        if (!\in_array($one, $expected, true)) {
            fail(\sprintf(
                "%s lists \"%s\", which the code does not have.",
                where($block),
                $one,
            ));
        }
    }

    foreach ($vocabulary->disagreeing() as $source => $other) {
        fail(\sprintf(
            "The code keeps two lists of \"%s\" and they disagree: %s has %d name(s), the first list has %d.",
            $name,
            $source,
            \count($other),
            \count($expected),
        ));
    }

    foreach ($blocks as $other) {
        if ($other->file === $block->file && $other->line === $block->line) {
            continue;
        }

        if (\array_diff($expected, $other->names) !== []) {
            continue;
        }

        fail(\sprintf(
            "%s carries a second copy of \"%s\", which %s is the home of. One list, one home.",
            where($other),
            $name,
            where($block),
        ));
    }

    foreach ($vocabulary->values as $one => $value) {
        $cell = $block->values[$one] ?? null;

        if ($cell === null) {
            fail(\sprintf("%s does not give \"%s\" a value, which the code has.", where($block), $one));

            continue;
        }

        if (!\str_contains($cell, (string) $value) && !\str_contains($cell, \number_format($value, 0, '.', ','))) {
            fail(\sprintf(
                "%s says \"%s\" is \"%s\"; the code says %d.",
                where($block),
                $one,
                $cell,
                $value,
            ));
        }
    }

    $checked++;
    $counted += \count($expected);
}

if ($checked === 0) {
    fail('No vocabulary was checked, so there is nothing to say about the reference tables.');
}

if ($failures !== []) {
    \fwrite(\STDERR, \sprintf("%d problem(s) with the reference tables:\n\n", \count($failures)));

    foreach ($failures as $failure) {
        \fwrite(\STDERR, '  ' . \str_replace("\n", "\n  ", $failure) . "\n\n");
    }

    exit(1);
}

\printf(
    "%d vocabulary(ies) and %d name(s) checked against the pages.\n",
    $checked,
    $counted,
);

foreach ($vocabularies as $name => $vocabulary) {
    \printf(
        "  %-28s %2d name(s)  %s\n",
        $name,
        \count($vocabulary->names()),
        \str_replace(\dirname(__DIR__) . '/', '', ($marked[$name][0] ?? null)?->file ?? ''),
    );
}
