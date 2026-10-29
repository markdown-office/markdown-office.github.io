<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tool;

/**
 * A file of the skill that could not be read.
 *
 * The bundle is made by the build and checked after it, and the things that can be wrong
 * with it are one thing to whoever reads the check: the manifest is not there, a page the
 * manifest names is not there, a page has no permalink, or the configuration does not say
 * what the canonical host is. The message says which.
 */
final class UnreadableSkill extends \RuntimeException
{
}
