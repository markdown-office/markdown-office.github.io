<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Tests\Support\ControlServer;
use MarkdownOffice\Documentation\Tests\Support\TheMachineryFailed;

/*
 * The client the skill carries: what it asks, and how it reads the answer.
 *
 * The client talks to WordCraft or DeckCraft over a socket, and the only way to know what
 * it asks is to have something on the other end that answers. So an application that
 * speaks the protocol stands in for the real one.
 *
 * Two things are held to here.
 *
 * **The document is opened with one request that carries the path.** A client that finds
 * out whether a method exists by calling it with no parameters is making a request that
 * may do something, and throwing the answer away — and on WordCraft it fails for the
 * wrong reason, a missing parameter rather than an unknown command, so the answer is
 * "yes" by accident.
 *
 * **The id of the reply is checked.** The protocol says a reply carries the id of the
 * request it answers, and a client is to check it: a reply to an earlier request is not
 * the answer to this one.
 */

/**
 * The flags the client takes.
 *
 * Named once, because the client is asked for them in four places and a flag that is
 * written down four times is a flag that is spelled four ways.
 */
const PORT_FLAG = '--port';

const OUT_FLAG = '--out';

/**
 * The client's answer, with the environment it needs.
 *
 * @param array<string, string> $environment
 * @param list<string> $arguments
 *
 * @return array{out: string, err: string, status: int}
 */
function client(array $environment, array $arguments): array
{
    $root = dirname(__DIR__, 2);

    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open(
        [PHP_BINARY, $root . '/skill/mdword/scripts/render.php', ...$arguments],
        $descriptors,
        $pipes,
        null,
        array_merge(getenv(), $environment),
    );

    if (! is_resource($process)) {
        throw TheMachineryFailed::because('The client could not be started.');
    }

    $out = (string) stream_get_contents($pipes[1]);
    $err = (string) stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['out' => $out, 'err' => $err, 'status' => proc_close($process)];
}

/**
 * The control key the client reads, where the application that wrote it keeps it.
 *
 * The client looks in the same place, and a key that is somewhere else is a client that
 * cannot find it — which is the one thing this test would be measuring rather than the
 * thing it is for.
 */
function writeControlKey(string $home, int $port): void
{
    $relative = match (PHP_OS_FAMILY) {
        'Darwin' => 'Library/Application Support/WordCraft',
        'Linux' => '.config/wordcraft',
        'Windows' => 'AppData/Roaming/WordCraft',
        default => null,
    };

    if ($relative === null) {
        test()->markTestSkipped('This platform keeps the control key somewhere this does not know.');
    }

    $directory = $home . '/' . $relative;

    if (! is_dir($directory) && ! mkdir($directory, 0o777, true) && ! is_dir($directory)) {
        throw TheMachineryFailed::because(sprintf('The directory "%s" could not be made.', $directory));
    }

    file_put_contents($directory . '/control-key.local-' . $port, 'the-key');
}

/**
 * A home folder, a document and a directory to write to, for one run.
 *
 * @return array{home: string, document: string, out: string}
 */
function aRun(): array
{
    $unique = bin2hex(random_bytes(6));

    $home = sys_get_temp_dir() . '/mdword-home-' . $unique;
    $out = sys_get_temp_dir() . '/mdword-out-' . $unique;

    mkdir($home, 0o777, true);
    mkdir($out, 0o777, true);

    $document = $home . '/notes.docx';

    file_put_contents($document, 'not a document');

    return ['home' => $home, 'document' => $document, 'out' => $out];
}

it('opens the document with one request that carries the path', function () {
    $server = ControlServer::speaking('word');
    $run = aRun();

    writeControlKey($run['home'], $server->port);

    $answer = client(
        ['HOME' => $run['home']],
        [$run['document'], PORT_FLAG, (string) $server->port, OUT_FLAG, $run['out']],
    );

    expect($answer['status'])->toBe(0)
        ->and($server->requests()[0])->toBe('file.open {"path":"' . $run['document'] . '"}')
        ->and(glob($run['out'] . '/*.png'))->not->toBeEmpty();
});

it('opens the document with the other method when the first is unknown', function () {
    // DeckCraft opens a document with `app.open` and knows nothing of `file.open`, so
    // the client asks the other one only when the first is refused.
    $server = ControlServer::speaking('deck');
    $run = aRun();

    writeControlKey($run['home'], $server->port);

    $answer = client(
        ['HOME' => $run['home']],
        [$run['document'], PORT_FLAG, (string) $server->port, OUT_FLAG, $run['out']],
    );

    expect($answer['status'])->toBe(0)
        ->and($server->requests()[0])->toBe('file.open {"path":"' . $run['document'] . '"}')
        ->and($server->requests()[1])->toBe('app.open {"path":"' . $run['document'] . '"}');
});

it('refuses a reply that answers another question', function () {
    $server = ControlServer::speaking('bad-id');
    $run = aRun();

    writeControlKey($run['home'], $server->port);

    $answer = client(
        ['HOME' => $run['home']],
        [$run['document'], PORT_FLAG, (string) $server->port, OUT_FLAG, $run['out']],
    );

    expect($answer['status'])->not->toBe(0)
        ->and($answer['err'])->toContain('not the answer');
});

it('says so when there is no application to talk to', function () {
    $run = aRun();

    // A port nothing is listening on.
    $answer = client(
        ['HOME' => $run['home']],
        [$run['document'], PORT_FLAG, '1', OUT_FLAG, $run['out']],
    );

    expect($answer['status'])->not->toBe(0)
        ->and($answer['err'])->not->toBe('');
});
