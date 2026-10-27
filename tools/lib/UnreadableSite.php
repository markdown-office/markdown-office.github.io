<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tool;

/**
 * A file of the site that could not be read.
 *
 * Four things can go wrong and they are one thing to whoever reads the check: the page that
 * holds the command-line page's address is not there, it has no permalink, the site's
 * configuration is not there, or it does not say what the canonical host is. The message says
 * which.
 */
final class UnreadableSite extends \RuntimeException
{
}
