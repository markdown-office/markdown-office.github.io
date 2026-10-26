#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Checks that the command-line pages say what the command line does.
 *
 *   php tools/check-command-line.php [path-to-mdoffice]
 *
 * The pages under `content/command-line/` are the only place the suite documents
 * `mdoffice`, and they are written by hand: nothing checks them against the
 * program. A command that is implemented and not documented, an option that is
 * renamed, a summary that is a word out of date — each of those is found by a
 * reader, at a terminal, at the worst possible moment.
 *
 * So this runs the program and reads its own answer. The parts and what they
 * say they do, the commands under each part, and the options under each command
 * are what `mdoffice help` prints; the pages are read for the same three things,
 * and the two are compared. The pages quote the program's own words in four
 * places, and those quotes are compared character for character.
 *
 * The program is read from a checkout, which is why the path is an argument: the
 * pages are in this repository and the program is in the one beside it. A
 * checkout that is not there is a failure rather than a skip — a check that
 * passes because it found nothing to check is worse than no check at all.
 *
 * What it checks:
 *
 *  - the parts table lists every part, and says what the part says it does
 *  - the commands table lists every command of every part, and no others
 *  - every option a page names is an option the command takes, and every option
 *    the command takes is named
 *  - the quoted error is the error the program prints
 *
 * and what it refuses to let the pages say, which is in {@see FORBIDDEN} below.
 */

\error_reporting(\E_ALL & ~\E_DEPRECATED & ~\E_USER_DEPRECATED);
\ini_set('display_errors', 'stderr');

if (\PHP_SAPI !== 'cli') {
    \fwrite(\STDERR, "The command-line pages are checked from the command line.\n");
    exit(1);
}

$root = \dirname(__DIR__);
$checkout = $argv[1] ?? \dirname($root) . '/mdoffice';
$binary = $checkout . '/bin/mdoffice';

if (!\is_file($binary)) {
    \fwrite(\STDERR, \sprintf("There is no command line at %s.\n", $binary));
    \fwrite(\STDERR, "The first argument is a checkout of markdown-office/mdoffice.\n");
    exit(1);
}

/**
 * The claims the pages must not make, and the page each was made on.
 *
 * These are claims that were made, and were wrong, and were found. A claim that
 * is corrected once tends to come back, because the code moved on and the
 * sentence did not — so the phrase is here, and a page that says it again is a
 * failure.
 *
 * @var array<string, string>
 */
const FORBIDDEN = [
    'the slides part has one command' => 'The slides part has one command',
    'the presentation half has no reverse direction' => 'PowerPoint is one direction',
    'the reader class does not exist' => 'There is no reader, no `.pptx` back to Markdown',
    'the release workflow does not exist' => 'there is no release job',
    'the package is on Packagist' => 'It is on Packagist as `markdown-office/mdoffice`',
];

/**
 * Runs the command line and returns what it printed.
 *
 * @param list<string> $arguments
 */
function runCommandLine(string $binary, array $arguments): string
{
    $command = \escapeshellarg(\PHP_BINARY) . ' ' . \escapeshellarg($binary);

    foreach ($arguments as $argument) {
        $command .= ' ' . \escapeshellarg($argument);
    }

    $output = \shell_exec($command . ' 2>&1');

    if (!\is_string($output)) {
        \fwrite(\STDERR, \sprintf("The command line could not be run: %s\n", $command));
        exit(1);
    }

    return $output;
}

/**
 * The parts and what each says it does, out of `mdoffice help`.
 *
 * @return array<string, string>
 */
function partsOf(string $help): array
{
    $parts = [];
    $inParts = false;

    foreach (\explode("\n", $help) as $line) {
        if (\preg_match('/^PARTS\s*$/', $line) === 1) {
            $inParts = true;

            continue;
        }

        if (!$inParts) {
            continue;
        }

        // A blank line ends the section, and so does anything that is not a
        // part: the list is padded to a fixed width, so the name and the
        // summary are separated by more than one space.
        if (\preg_match('/^ {2}(\S+)\s{2,}(\S.*)$/', $line, $match) !== 1) {
            if (\trim($line) === '') {
                continue;
            }

            break;
        }

        $parts[$match[1]] = $match[2];
    }

    return $parts;
}

/**
 * The commands of a part, out of `mdoffice <part> help`.
 *
 * The usage section is what lists them. The examples further down name files
 * rather than commands, and a file is not a command.
 *
 * @return list<string>
 */
function commandsOf(string $help, string $part): array
{
    $commands = [];
    $inUsage = false;

    foreach (\explode("\n", $help) as $line) {
        if (\preg_match('/^USAGE\s*$/', $line) === 1) {
            $inUsage = true;

            continue;
        }

        if (!$inUsage) {
            continue;
        }

        // The usage block ends at the first blank line.
        if (\trim($line) === '') {
            break;
        }

        if (\preg_match('/^  mdoffice ' . \preg_quote($part, '/') . ' (\S+)/', $line, $match) !== 1) {
            continue;
        }

        $token = $match[1];

        // The usage lines name the default, the help and the version as well,
        // and the default is written as a placeholder rather than a command.
        if (\str_starts_with($token, '<') || \in_array($token, ['help', '--version'], true)) {
            continue;
        }

        $commands[] = $token;
    }

    return \array_values(\array_unique($commands));
}

/**
 * The options of every command of a part, out of `mdoffice <part> help`.
 *
 * A command's options are under a heading of its own — `TO DOCX`, `TO MARKDOWN`
 * — and the heading is the command's name in capitals. A heading is a heading
 * because a rule of dashes is under it, which is what tells the sections of
 * options apart from the other capitals in the help.
 *
 * @return array<string, list<string>>
 */
function optionsOf(string $help): array
{
    $options = [];
    $lines = \explode("\n", $help);

    foreach ($lines as $number => $line) {
        if (\preg_match('/^([A-Z][A-Z ]+)\s*$/', $line, $match) !== 1) {
            continue;
        }

        if (\preg_match('/^-+\s*$/', $lines[$number + 1] ?? '') !== 1) {
            continue;
        }

        $command = \strtolower(\str_replace(' ', '-', \trim($match[1])));
        $options[$command] = [...($options[$command] ?? []), ...optionsUnder($lines, $number)];
    }

    return $options;
}

/**
 * The options under one heading of the help.
 *
 * The heading is at `$number` and the rule under it at `$number + 1`, so the options
 * begin after both. The section ends at the first blank line.
 *
 * @param list<string> $lines
 *
 * @return list<string>
 */
function optionsUnder(array $lines, int $number): array
{
    $options = [];

    for ($next = $number + 2; $next < \count($lines); $next++) {
        $entry = $lines[$next];

        if (\trim($entry) === '') {
            break;
        }

        if (\preg_match('/^ {2}(-{1,2}[^\s,]+)(?:, (-{1,2}[^\s]+))?/', $entry, $match) !== 1) {
            continue;
        }

        $options[] = $match[1];

        if (($match[2] ?? '') !== '') {
            $options[] = $match[2];
        }
    }

    return $options;
}

/**
 * A page of the site.
 */
function page(string $root, string $name): string
{
    $path = $root . '/content/command-line/' . $name;

    if (!\is_file($path)) {
        \fwrite(\STDERR, \sprintf("There is no page at %s.\n", $path));
        exit(1);
    }

    return (string) \file_get_contents($path);
}

/**
 * The rows of a Markdown table, as their cells.
 *
 * @return list<list<string>>
 */
function rowsOf(string $markdown, string $heading): array
{
    $rows = [];
    $inTable = false;

    foreach (\explode("\n", $markdown) as $line) {
        if (\preg_match('/^## ' . \preg_quote($heading, '/') . '\s*$/', $line) === 1) {
            $inTable = true;

            continue;
        }

        if ($inTable && \preg_match('/^#{1,2} /', $line) === 1) {
            break;
        }

        if (!$inTable || !\str_starts_with(\trim($line), '|')) {
            continue;
        }

        $cells = \array_map('trim', \explode('|', \trim($line, " \t|")));
        $rows[] = $cells;
    }

    return $rows;
}

/**
 * The option spellings a cell names, both the long ones and the short ones.
 *
 * @return list<string>
 */
function optionsIn(string $cell): array
{
    $found = [];

    if (\preg_match_all('/(?<![\w-])--[a-z][a-z-]*/', $cell, $long) > 0) {
        $found = \array_merge($found, $long[0]);
    }

    if (\preg_match_all('/(?<![\w-])-[a-zA-Z](?![\w-])/', $cell, $short) > 0) {
        $found = \array_merge($found, $short[0]);
    }

    return \array_values(\array_unique($found));
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

$parts = partsOf(runCommandLine($binary, ['help']));

if ($parts === []) {
    \fwrite(\STDERR, "The command line printed no parts, so there is nothing to check against.\n");
    exit(1);
}

$basic = page($root, 'basic.md');
$word = page($root, 'word.md');
$presentations = page($root, 'presentations.md');

/**
 * The parts table, as the part's name and what the page says it does.
 *
 * @return array<string, string>
 */
$documentedParts = [];

foreach (\explode("\n", $basic) as $line) {
    if (\preg_match('/^\| `mdoffice (\w+) …` \| (.*) \|$/', \trim($line), $match) === 1) {
        $documentedParts[$match[1]] = \str_replace('`', '', $match[2]);
    }
}

foreach ($parts as $name => $summary) {
    if (!isset($documentedParts[$name])) {
        fail(\sprintf("The parts table does not list the part \"%s\".", $name));

        continue;
    }

    if ($documentedParts[$name] !== $summary) {
        fail(\sprintf(
            "The parts table says the part \"%s\" does \"%s\"; the program says \"%s\".",
            $name,
            $documentedParts[$name],
            $summary,
        ));
    }
}

foreach (\array_keys($documentedParts) as $name) {
    if (!isset($parts[$name])) {
        fail(\sprintf("The parts table lists the part \"%s\", which the program does not have.", $name));
    }
}

/**
 * The commands table, as the part's name and the command's name.
 *
 * @return array<string, list<string>>
 */
$documentedCommands = [];

foreach (\explode("\n", $basic) as $line) {
    if (\preg_match('/^\| `(\w+)` \| `([\w-]+)` \|/', \trim($line), $match) === 1) {
        $documentedCommands[$match[1]][] = $match[2];
    }
}

foreach ($parts as $part => $summary) {
    $commands = commandsOf(runCommandLine($binary, [$part, 'help']), $part);
    $documented = $documentedCommands[$part] ?? [];

    foreach ($commands as $command) {
        if (!\in_array($command, $documented, true)) {
            fail(\sprintf(
                "The commands table does not list the command \"%s %s\", which the program has.",
                $part,
                $command,
            ));
        }
    }

    foreach ($documented as $command) {
        if (!\in_array($command, $commands, true)) {
            fail(\sprintf(
                "The commands table lists the command \"%s %s\", which the program does not have.",
                $part,
                $command,
            ));
        }
    }
}

/**
 * The option tables, and the command each is for.
 *
 * The heading of a table names the commands it is for; `to-docx`, `to-odt` and
 * `to-rtf` in one heading are three commands, and the options have to be ones
 * all three take.
 *
 * @var array<string, list<string>>
 */
$tables = [
    'word.md' => ['word', $word],
    'presentations.md' => ['slides', $presentations],
];

foreach ($tables as $file => [$part, $markdown]) {
    $options = optionsOf(runCommandLine($binary, [$part, 'help']));
    $headings = [];

    foreach (\explode("\n", $markdown) as $line) {
        if (\preg_match('/^## Options for (.+)$/', $line, $match) === 1) {
            $headings[] = $match[1];
        }
    }

    foreach ($headings as $heading) {
        $commands = [];

        foreach (\explode(',', \str_replace('`', '', $heading)) as $name) {
            $name = \trim($name);

            if ($name !== '') {
                $commands[] = $name;
            }
        }

        $taken = [];

        foreach (\array_merge(...\array_map(
            static fn (string $command): array => $options[$command] ?? [],
            $commands,
        )) as $option) {
            $taken[] = $option;
        }

        $taken = \array_values(\array_unique($taken));

        foreach (rowsOf($markdown, 'Options for ' . $heading) as $row) {
            if (\count($row) < 2 || \str_starts_with($row[0], '---')) {
                continue;
            }

            foreach (optionsIn($row[0]) as $option) {
                if (!\in_array($option, $taken, true)) {
                    fail(\sprintf(
                        "%s documents the option \"%s\" for \"%s\", which the command does not take.",
                        $file,
                        $option,
                        $heading,
                    ));
                }
            }
        }

        // Every option the command takes is a thing the page should name, or
        // the reader cannot find it.
        foreach ($taken as $option) {
            if ($option === '-h' || $option === '--help') {
                continue;
            }

            $named = false;

            foreach (rowsOf($markdown, 'Options for ' . $heading) as $row) {
                if (\count($row) < 2 || \str_starts_with($row[0], '---')) {
                    continue;
                }

                if (\in_array($option, optionsIn($row[0]), true)) {
                    $named = true;

                    break;
                }
            }

            if (!$named) {
                fail(\sprintf(
                    "%s does not document the option \"%s\", which \"%s\" takes.",
                    $file,
                    $option,
                    $heading,
                ));
            }
        }
    }
}

/**
 * The quoted error the pages carry, and the error the program prints.
 */
$quoted = null;

if (\preg_match('/```text\n(mdoffice: Unknown part .*?)```/s', $basic, $match) === 1) {
    $quoted = $match[1];
}

$printed = runCommandLine($binary, ['notes.md']);

if ($quoted === null) {
    fail("The basic page does not quote the error for a file that is not a part.");
} elseif (\rtrim($quoted) !== \rtrim($printed)) {
    fail(\sprintf(
        "The basic page quotes an error the program does not print.\n  the page says: %s\n  the program says: %s",
        \str_replace("\n", "\n    ", \rtrim($quoted)),
        \str_replace("\n", "\n    ", \rtrim($printed)),
    ));
}

/**
 * The claims the pages must not make.
 */
$pages = [
    'basic.md' => $basic,
    'word.md' => $word,
    'presentations.md' => $presentations,
    'index.md' => (string) \file_get_contents($root . '/content/index.md'),
    'presentation/index.md' => (string) \file_get_contents($root . '/content/presentation/index.md'),
    'word/round-trip.md' => (string) \file_get_contents($root . '/content/word/round-trip.md'),
    'suite/index.md' => (string) \file_get_contents($root . '/content/suite/index.md'),
    'llms/llms.txt.njk' => (string) \file_get_contents($root . '/content/llms/llms.txt.njk'),
];

foreach (FORBIDDEN as $claim => $phrase) {
    foreach ($pages as $name => $markdown) {
        if (\stripos($markdown, $phrase) !== false) {
            fail(\sprintf("The page \"%s\" says again what was corrected: \"%s\".", $name, $claim));
        }
    }
}

if ($failures !== []) {
    \fwrite(\STDERR, \sprintf("%d problem(s) with the command-line pages:\n\n", \count($failures)));

    foreach ($failures as $failure) {
        \fwrite(\STDERR, '  ' . \str_replace("\n", "\n  ", $failure) . "\n\n");
    }

    exit(1);
}

\fwrite(\STDOUT, \sprintf(
    "%d part(s), %d command(s) and their options checked against the pages.\n",
    \count($parts),
    \array_sum(\array_map('count', $documentedCommands)),
));
