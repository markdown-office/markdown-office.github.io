#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Renders a document to a picture of every page, through WordCraft or DeckCraft.
 *
 *   php render.php <document.docx> --port <port> [--out <directory>]
 *
 * **A document that has not been looked at has not been checked.** This is the tool that
 * looks at one: it opens the document in an application that lays it out the way Word does
 * and writes a PNG of every page, so that the pages can be read.
 *
 * The application is driven through its control protocol: one JSON object per line over a
 * loopback port, each request carrying the key the application wrote when it bound the
 * port. The protocol is documented upstream, at
 * `github.com/storytold/wordcraft/docs/control-protocol.md` and
 * `github.com/storytold/deckcraft/docs/control-protocol.md`.
 *
 * **The two applications do not speak the same protocol.** They speak the same *shape* —
 * one JSON object per line, a key with every request, `{"id": …, "ok": true, "result": …}`
 * or `{"ok": false, "error": …}` in reply — and they differ in their method names:
 * WordCraft opens a document with `file.open` and renders a page with `ui.render` and a
 * `page`, while DeckCraft opens one with `app.open` and renders a slide with `ui.render`
 * and a `slide`. So this tries one and falls back to the other, and says which it settled
 * on.
 *
 * The port is the application's own: start it with `--control <port>` and read the key out
 * of the file it writes. Nothing here starts an application, because an application that
 * answers a question is a question somebody asked on purpose.
 */

const HOST = '127.0.0.1';

/**
 * A render that did not happen, and why.
 *
 * A dedicated class rather than the generic one, because the message is the whole of what a
 * caller gets: a script that says "something went wrong" has said nothing.
 */
final class RenderFailed extends RuntimeException
{
}

/**
 * The settings folder each application keeps its control key in.
 *
 * The file is named after the port it belongs to, so two applications on two ports do not
 * share one — and one application restarted on the same port writes a new key, so the key
 * is read at the moment it is used rather than once at the start.
 */
const KEY_FILES = [
    'word' => [
        'Darwin' => 'Library/Application Support/WordCraft/control-key.local-%d',
        'Linux' => '.config/wordcraft/control-key.local-%d',
    ],
    'deck' => [
        'Darwin' => 'Library/Application Support/DeckCraft/control-key.local-%d',
        'Linux' => '.config/deckcraft/control-key.local-%d',
    ],
];

/**
 * The application that answers on a port.
 *
 * @return array{app: string, key: string}
 */
function application(int $port): array
{
    $home = (string) (\getenv('HOME') ?: \getenv('USERPROFILE') ?: '');

    if ($home === '') {
        throw new RenderFailed('The home folder could not be found, so the control key could not be.');
    }

    $system = \PHP_OS_FAMILY;

    foreach (['word', 'deck'] as $app) {
        $pattern = KEY_FILES[$app][$system] ?? null;

        if ($pattern === null) {
            continue;
        }

        $path = $home . '/' . \sprintf($pattern, $port);

        if (! \is_file($path)) {
            continue;
        }

        $key = \trim((string) \file_get_contents($path));

        if ($key === '') {
            continue;
        }

        return ['app' => $app, 'key' => $key];
    }

    throw new RenderFailed(\sprintf(
        'No control key was found for port %d. Start the application with `--control %d` and try again.',
        $port,
        $port,
    ));
}

/**
 * A connection to an application, and the key it wants.
 */
function connect(int $port): mixed
{
    $socket = @\stream_socket_client(\sprintf('tcp://%s:%d', HOST, $port), $errno, $error, 10);

    if ($socket === false) {
        throw new RenderFailed(\sprintf(
            'Nothing is listening on port %d (%s). Start the application with `--control %d` first.',
            $port,
            $error,
            $port,
        ));
    }

    return $socket;
}

/**
 * One request, and the reply.
 *
 * @param array<string, mixed> $params
 *
 * @return array<string, mixed>
 */
function ask(mixed $socket, string $key, string $method, array $params = []): array
{
    $request = ['id' => 1, 'key' => $key, 'method' => $method];

    if ($params !== []) {
        $request['params'] = $params;
    }

    \fwrite($socket, \json_encode($request) . "\n");

    $line = \fgets($socket);

    if ($line === false) {
        throw new RenderFailed(\sprintf('The application closed the connection during %s.', $method));
    }

    $reply = \json_decode(\line, true);

    if (! \is_array($reply)) {
        throw new RenderFailed(\sprintf('The reply to %s was not JSON.', $method));
    }

    if (($reply['ok'] ?? false) !== true) {
        throw new RenderFailed(\sprintf(
            '%s: %s',
            $method,
            (string) ($reply['error'] ?? 'the application said nothing about why'),
        ));
    }

    return (array) ($reply['result'] ?? []);
}

/**
 * Whether a method is one the application knows.
 */
function knows(mixed $socket, string $key, string $method): bool
{
    $request = ['id' => 1, 'key' => $key, 'method' => $method];

    \fwrite($socket, \json_encode($request) . "\n");

    $line = \fgets($socket);
    $reply = \is_string($line) ? \json_decode($line, true) : null;

    return \is_array($reply) && \str_contains((string) ($reply['error'] ?? ''), 'unknown command') === false;
}

/**
 * Opens a document, in whichever of the two applications answers.
 *
 * @return array{app: string, opened: string}
 */
function open(mixed $socket, string $key, string $document): array
{
    foreach (['file.open' => 'word', 'app.open' => 'deck'] as $method => $app) {
        if (! knows($socket, $key, $method)) {
            continue;
        }

        $reply = ask($socket, $key, $method, ['path' => $document]);

        return ['app' => $app, 'opened' => (string) ($reply['path'] ?? $document)];
    }

    throw new RenderFailed('The application answered, but it knows neither `file.open` nor `app.open`.');
}

/**
 * How many pages a document has.
 *
 * @return int
 */
function pages(mixed $socket, string $key): int
{
    $reply = ask($socket, $key, 'document.inspect');

    foreach (['pages', 'slides'] as $field) {
        if (isset($reply[$field]) && \is_numeric($reply[$field])) {
            return (int) $reply[$field];
        }
    }

    // A document of one page is the answer when the application will not say, and it is
    // the answer that is right more often than not.
    return 1;
}

/**
 * Renders a document to a picture of every page.
 *
 * @param list<string> $written
 */
function render(mixed $socket, string $key, string $document, string $directory, string $app): array
{
    if (! \is_dir($directory) && ! @\mkdir($directory, 0o777, true) && ! \is_dir($directory)) {
        throw new RenderFailed(\sprintf('The directory "%s" could not be made.', $directory));
    }

    open($socket, $key, $document);

    $count = pages($socket, $key);
    $written = [];
    $stem = \pathinfo($document, PATHINFO_FILENAME);

    for ($page = 1; $page <= $count; $page++) {
        $path = \sprintf('%s/%s-page-%02d.png', \rtrim($directory, '/'), $stem, $page);

        // WordCraft counts pages from one and calls them pages; DeckCraft counts slides
        // from one and calls them slides. The parameter is the only difference.
        $params = $app === 'deck'
            ? ['path' => $path, 'slide' => $page]
            : ['path' => $path, 'page' => $page];

        ask($socket, $key, 'ui.render', $params);

        if (! \is_file($path)) {
            throw new RenderFailed(\sprintf('The application wrote no picture for page %d.', $page));
        }

        $written[] = $path;
    }

    return $written;
}

//
// The run.
//

$arguments = \array_slice($argv ?? [], 1);
$document = null;
$directory = \sys_get_temp_dir();
$port = null;

// The arguments are read with a cursor rather than by moving the loop's own counter: a
// counter that moves twice is a counter that is wrong once.
$cursor = 0;

while ($cursor < \count($arguments)) {
    $argument = $arguments[$cursor];

    if ($argument === '--out') {
        $directory = $arguments[$cursor + 1] ?? $directory;
        $cursor += 2;

        continue;
    }

    if ($argument === '--port') {
        $port = (int) ($arguments[$cursor + 1] ?? 0);
        $cursor += 2;

        continue;
    }

    $document ??= $argument;
    $cursor++;
}

if ($document === null) {
    \fwrite(STDERR, "Usage: php render.php <document> --port <port> [--out <directory>]\n");
    exit(1);
}

if ($port === null || $port === 0) {
    \fwrite(STDERR, "The port is missing. Start the application with `--control <port>` and pass it.\n");
    exit(1);
}

try {
    $application = application($port);
    $socket = connect($port);

    try {
        $written = render($socket, $application['key'], $document, $directory, $application['app']);
    } finally {
        \fclose($socket);
    }
} catch (Throwable $problem) {
    \fwrite(STDERR, $problem->getMessage() . "\n");
    exit(1);
}

foreach ($written as $path) {
    \printf("%s\n", $path);
}
