<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

/**
 * The bundle the build makes, built again for a test that needs it.
 *
 * The build's own script is run rather than the site being built, because a test that
 * reads a built site reads whatever the last build left behind: a bundle that had fallen
 * behind would pass a test that looked at it. The script is the one the build runs, so
 * what the test looks at is what the build makes.
 *
 * The script is Node's, because the site is built by Eleventy and this is the part it
 * cannot do. It writes the bundle into a directory of its own and says which.
 */
final class Bundle
{
    /**
     * The directory the bundle was written in, for the run of the tests.
     */
    private static ?string $built = null;

    /**
     * The bundle the build makes, at the path it sits at in the site.
     */
    public static function built(): string
    {
        return self::output() . '/skills/mdword';
    }

    /**
     * The directory the bundle was written in.
     */
    private static function output(): string
    {
        if (self::$built !== null) {
            return self::$built;
        }

        $root = Site::root();

        $script = <<<'JS'
            import { writeSkill } from "%s/tools/write-skill.js";
            import { mkdtempSync } from "node:fs";
            import { tmpdir } from "node:os";

            const dir = { output: mkdtempSync(tmpdir() + "/mdword-bundle-") };

            await writeSkill({ dir });

            process.stdout.write(dir.output);
            JS;

        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = \proc_open(
            ['node', '--input-type=module', '-e', \sprintf($script, $root)],
            $descriptors,
            $pipes,
            $root,
        );

        if (! \is_resource($process)) {
            throw TheMachineryFailed::because('The bundle could not be built.');
        }

        $out = (string) \stream_get_contents($pipes[1]);
        $err = (string) \stream_get_contents($pipes[2]);

        \fclose($pipes[1]);
        \fclose($pipes[2]);

        $status = \proc_close($process);

        if ($status !== 0) {
            throw TheMachineryFailed::because(
                \trim($err) === '' ? 'The bundle could not be built.' : \trim($err),
            );
        }

        $directory = \trim($out);

        // The bundle is taken away with the run, because a directory of a megabyte and a
        // half left in the temporary directory is a side effect nobody asked for.
        \register_shutdown_function(static function () use ($directory): void {
            Directory::remove($directory);
        });

        return self::$built = $directory;
    }
}
