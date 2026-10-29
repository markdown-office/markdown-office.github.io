<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

use RuntimeException;

/**
 * An application that speaks the control protocol, for the client to talk to.
 *
 * The bundle's client is a program that asks an application a question over a socket,
 * and the only way to know what it asks is to have something on the other end that
 * answers. This is that something: a process that binds a port of its own, answers the
 * protocol, and writes down every request it was sent.
 *
 * **The mode says which application it is.** `word` knows `file.open` and not `app.open`,
 * `deck` the other way round, which is the one way the two differ. `bad-id` answers with
 * the id of a request that was never sent, which is the failure the client is there to
 * catch.
 */
final class ControlServer
{
    /**
     * The code the server runs.
     *
     * In the class rather than in a file of its own, because a file in the tests'
     * autoload path that holds no class is a file the autoloader complains about.
     */
    private const CODE = <<<'PHP'
        $mode = (string) getenv('MDWORD_MODE');
        $portFile = (string) getenv('MDWORD_PORT_FILE');
        $logFile = (string) getenv('MDWORD_LOG_FILE');

        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);

        if ($server === false) {
            fwrite(STDERR, 'The server could not bind: ' . $error . "\n");
            exit(1);
        }

        $name = (string) stream_socket_get_name($server, false);
        $port = (int) substr($name, strrpos($name, ':') + 1);

        file_put_contents($portFile, (string) $port);

        // A picture of one pixel, so that a rendered page is a picture and not an empty
        // file that happens to be there.
        $picture = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        while (true) {
            $client = stream_socket_accept($server, -1);

            if ($client === false) {
                continue;
            }

            while (($line = fgets($client)) !== false) {
                $request = json_decode($line, true);

                if (! is_array($request)) {
                    continue;
                }

                $method = (string) ($request['method'] ?? '');
                $id = (int) ($request['id'] ?? 0);

                file_put_contents(
                    $logFile,
                    $method . ' ' . json_encode($request['params'] ?? null, JSON_UNESCAPED_SLASHES) . "\n",
                    FILE_APPEND,
                );

                if ($mode === 'bad-id') {
                    $id = $id + 1000;
                }

                $known = match ($method) {
                    'file.open' => $mode !== 'deck',
                    'app.open' => $mode === 'deck',
                    'document.inspect', 'ui.render' => true,
                    default => false,
                };

                if (! $known) {
                    fwrite($client, json_encode([
                        'id' => $id,
                        'ok' => false,
                        'error' => 'unknown command: ' . $method,
                    ]) . "\n");

                    continue;
                }

                $result = [];

                if ($method === 'file.open' || $method === 'app.open') {
                    $result = ['path' => $request['params']['path'] ?? ''];
                }

                if ($method === 'document.inspect') {
                    $result = ['pages' => 1];
                }

                if ($method === 'ui.render') {
                    file_put_contents((string) ($request['params']['path'] ?? ''), $picture);
                }

                fwrite($client, json_encode(['id' => $id, 'ok' => true, 'result' => $result]) . "\n");
            }

            fclose($client);
        }
        PHP;

    /**
     * The port the server answers on.
     */
    public int $port = 0;

    /**
     * Where every request it was sent is written down.
     */
    public string $log;

    /**
     * @var resource|null
     */
    private $process = null;

    /**
     * @var array<int, resource>
     */
    private array $pipes = [];

    private function __construct()
    {
        $log = \tempnam(\sys_get_temp_dir(), 'mdword-control-');

        if ($log === false) {
            throw new RuntimeException('The control server could not be given a file to write to.');
        }

        $this->log = $log;
    }

    /**
     * A server on a port of its own, answering the protocol.
     */
    public static function speaking(string $mode): self
    {
        $server = new self();

        $portFile = \tempnam(\sys_get_temp_dir(), 'mdword-port-');

        if ($portFile === false) {
            throw new RuntimeException('The control server could not be given a file to write its port to.');
        }

        $server->process = \proc_open(
            [\PHP_BINARY, '-r', self::CODE],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $server->pipes,
            null,
            \array_merge(\getenv(), [
                'MDWORD_MODE' => $mode,
                'MDWORD_PORT_FILE' => $portFile,
                'MDWORD_LOG_FILE' => $server->log,
            ]),
        );

        if (! \is_resource($server->process)) {
            throw new RuntimeException('The control server could not be started.');
        }

        $server->port = $server->waitForPort($portFile);

        return $server;
    }

    /**
     * Every request the server was sent, in the order they arrived.
     *
     * @return list<string>
     */
    public function requests(): array
    {
        $written = \file_get_contents($this->log);

        if ($written === false) {
            return [];
        }

        return \array_values(\array_filter(\explode("\n", $written)));
    }

    /**
     * Stops the server.
     */
    public function stop(): void
    {
        if ($this->process === null) {
            return;
        }

        foreach ($this->pipes as $pipe) {
            if (\is_resource($pipe)) {
                \fclose($pipe);
            }
        }

        \proc_terminate($this->process);
        \proc_close($this->process);

        $this->process = null;
    }

    public function __destruct()
    {
        $this->stop();
    }

    /**
     * The port the server bound, which it writes down because it chose it itself.
     *
     * The server binds to port zero and takes what it is given, rather than being told
     * a port to bind: a port chosen here and handed over is a port another process may
     * have taken in between.
     */
    private function waitForPort(string $portFile): int
    {
        for ($tries = 0; $tries < 100; $tries++) {
            $written = \trim((string) \file_get_contents($portFile));

            if ($written !== '') {
                return (int) $written;
            }

            \usleep(50000);
        }

        throw new RuntimeException('The control server did not say which port it is on.');
    }
}
