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
            throw UnreadableGallery::unreadable('skill/mdword/galleries.json');
        }

        $parsed = \json_decode((string) \file_get_contents($file), true);

        if (! \is_array($parsed)) {
            throw UnreadableGallery::notJson('skill/mdword/galleries.json');
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
            foreach (self::differencesIn($half, $gallery, $manifest['exclude'], $root, $bundle) as $problem) {
                $problems[] = $problem;
            }
        }

        return $problems;
    }

    /**
     * How one half's gallery differs from the one the library ships.
     *
     * @param array{source: string, path: string} $gallery
     * @param list<string> $exclude
     *
     * @return list<string>
     */
    private static function differencesIn(string $half, array $gallery, array $exclude, string $root, string $bundle): array
    {
        $library = $root . '/../' . $gallery['source'];
        $carried = $bundle . '/' . $gallery['path'];

        if (! \is_dir($library)) {
            return [\sprintf(
                'The %s half\'s gallery is not there. The library is checked out beside this one.',
                $half,
            )];
        }

        if (! \is_dir($carried)) {
            return [\sprintf('The bundle does not carry the %s half\'s gallery.', $half)];
        }

        $theirs = self::files($library, $exclude);
        $ours = self::files($carried, []);

        return [
            ...self::missing($half, $theirs, $ours),
            ...self::changed($half, $theirs, $ours, $library, $carried),
            ...self::extra($half, $ours, $theirs),
        ];
    }

    /**
     * The files of the library's gallery the bundle does not carry.
     *
     * @param list<string> $theirs
     * @param list<string> $ours
     *
     * @return list<string>
     */
    private static function missing(string $half, array $theirs, array $ours): array
    {
        $problems = [];

        foreach ($theirs as $file) {
            if (! \in_array($file, $ours, true)) {
                $problems[] = \sprintf('The bundle does not carry "%s" of the %s half\'s gallery.', $file, $half);
            }
        }

        return $problems;
    }

    /**
     * The files the bundle carries that are not the ones the library ships.
     *
     * @param list<string> $theirs
     * @param list<string> $ours
     *
     * @return list<string>
     */
    private static function extra(string $half, array $ours, array $theirs): array
    {
        $problems = [];

        foreach ($ours as $file) {
            if (! \in_array($file, $theirs, true)) {
                $problems[] = \sprintf('The bundle carries "%s", which the %s half does not ship.', $file, $half);
            }
        }

        return $problems;
    }

    /**
     * The files the bundle carries that are not the library's own.
     *
     * @param list<string> $theirs
     * @param list<string> $ours
     *
     * @return list<string>
     */
    private static function changed(string $half, array $theirs, array $ours, string $library, string $carried): array
    {
        $problems = [];

        foreach ($theirs as $file) {
            if (! \in_array($file, $ours, true)) {
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
