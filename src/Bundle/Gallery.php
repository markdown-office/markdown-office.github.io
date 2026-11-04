<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Bundle;

/**
 * The galleries the bundle carries, and the libraries they come from.
 *
 * The two halves of the suite each ship a gallery of examples: the catalogue of what that
 * half can do, one file each. The bundle carries both of them, copied from the libraries
 * rather than written again here, so a gallery that has fallen behind the library is a
 * gallery this can name.
 *
 * The manifest is read rather than written down here, because the build reads it too: two
 * copies of one list are two answers to one question, and the two drift.
 */
final class Gallery
{
    /**
     * The manifest: the galleries, and the parts of a gallery the bundle does not carry.
     *
     * @return array{exclude: list<string>, galleries: array<string, array{source: string, path: string}>}
     */
    private static function manifest(string $root): array
    {
        $file = $root . '/skill/mdword/galleries.json';

        if (! \is_file($file)) {
            throw new \RuntimeException('skill/mdword/galleries.json could not be read.');
        }

        $parsed = \json_decode((string) \file_get_contents($file), true);

        if (! \is_array($parsed)) {
            throw new \RuntimeException('skill/mdword/galleries.json could not be read as JSON.');
        }

        return [
            'exclude' => \array_values((array) ($parsed['exclude'] ?? [])),
            'galleries' => (array) ($parsed['galleries'] ?? []),
        ];
    }

    /**
     * Where each half's gallery is carried, as the path the prose names.
     *
     * @return array<string, string>
     */
    public static function paths(string $root): array
    {
        $paths = [];

        foreach (self::manifest($root)['galleries'] as $half => $gallery) {
            $paths[$half] = (string) ($gallery['path'] ?? '');
        }

        return $paths;
    }

    /**
     * How the galleries in the bundle differ from the ones the libraries ship.
     *
     * Both directions, because both are wrong in a way a reader cannot act on: a file the
     * library has and the bundle does not is a construct the agent cannot see, and a file
     * the bundle has and the library does not is a construct the library never had.
     *
     * @return list<string>
     */
    public static function differences(string $root, string $bundle): array
    {
        $manifest = self::manifest($root);
        $problems = [];

        foreach ($manifest['galleries'] as $half => $gallery) {
            $library = $root . '/../' . $gallery['source'];
            $carried = $bundle . '/' . $gallery['path'];

            if (! \is_dir($library)) {
                $problems[] = \sprintf(
                    'The %s half\'s gallery is not there. The library is checked out beside this one.',
                    $half,
                );

                continue;
            }

            if (! \is_dir($carried)) {
                $problems[] = \sprintf('The bundle does not carry the %s half\'s gallery.', $half);

                continue;
            }

            $theirs = self::files($library, $manifest['exclude']);
            $ours = self::files($carried, []);

            foreach ($theirs as $file) {
                if (! \in_array($file, $ours, true)) {
                    $problems[] = \sprintf(
                        'The bundle does not carry "%s" of the %s half\'s gallery.',
                        $file,
                        $half,
                    );

                    continue;
                }

                if (\md5_file($library . '/' . $file) !== \md5_file($carried . '/' . $file)) {
                    $problems[] = \sprintf(
                        'The bundle\'s copy of "%s" is not the one the %s half ships.',
                        $file,
                        $half,
                    );
                }
            }

            foreach ($ours as $file) {
                if (! \in_array($file, $theirs, true)) {
                    $problems[] = \sprintf(
                        'The bundle carries "%s", which the %s half does not ship.',
                        $file,
                        $half,
                    );
                }
            }
        }

        return $problems;
    }

    /**
     * Every file of a gallery, as the path it has inside it.
     *
     * @param list<string> $excluded The names that are not carried: a directory of that name
     *                               takes its whole subtree with it.
     *
     * @return list<string>
     */
    private static function files(string $directory, array $excluded): array
    {
        if (! \is_dir($directory)) {
            return [];
        }

        $filter = new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            static fn (\SplFileInfo $item): bool => ! \in_array($item->getFilename(), $excluded, true),
        );

        $found = [];

        foreach (new \RecursiveIteratorIterator($filter, \RecursiveIteratorIterator::SELF_FIRST) as $item) {
            if ($item->isFile()) {
                $found[] = \substr($item->getPathname(), \strlen($directory) + 1);
            }
        }

        \sort($found);

        return $found;
    }
}
