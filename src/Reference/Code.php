<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Reference;

use MarkdownWord\Document\Frontmatter;
use MarkdownWord\Render\Directives;
use MarkdownWord\Render\Handlers;

/**
 * What the libraries have, read out of their code.
 *
 * The lists the site checks are read out of the library's own code rather than written
 * down here: a name written down here is a second copy of the thing this checks.
 *
 * The code is read rather than asked, because the library holds no list of the names it
 * reads — the name is in the call that reads it. A read that moves to another class
 * leaves the name out of the list here, and the check says so.
 */
final class Code
{
    /**
     * The public methods of an interface, by name.
     *
     * @param class-string $interface
     *
     * @return list<string>
     */
    public static function finderMethods(string $interface): array
    {
        $names = [];

        foreach ((new \ReflectionClass($interface))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $names[] = $method->getName();
        }

        \sort($names);

        return $names;
    }

    /**
     * The roles a finder resolves a name in.
     *
     * Read out of the disk finder's own bases: a finder holds one list of directories per
     * role, and the roles are the names of those lists. The library composes the name and
     * the role, and the finder is what turns a role into a set of places to look.
     *
     * @param class-string $finder
     *
     * @return list<string>
     */
    public static function finderRoles(string $finder): array
    {
        $roles = [];

        foreach ((new \ReflectionClass($finder))->getConstructor()?->getParameters() ?? [] as $parameter) {
            $roles[] = $parameter->getName();
        }

        \sort($roles);

        return $roles;
    }

    /**
     * The shape of an interface: every method's name, its parameters and its return type.
     *
     * A parameter is a type and a default rather than a name: the name is the library's
     * own, and an adapter written for one half serves the other whatever it calls its
     * arguments. The default is part of it, because a method that takes an optional
     * argument and a method that does not are two shapes.
     *
     * @param class-string $interface
     *
     * @return array<string, array{parameters: list<string>, returns: string}>
     */
    public static function shape(string $interface): array
    {
        $shape = [];

        foreach ((new \ReflectionClass($interface))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $parameters = [];

            foreach ($method->getParameters() as $parameter) {
                $type = (string) ($parameter->getType() ?? 'mixed');

                $parameters[] = $parameter->isDefaultValueAvailable()
                    ? $type . ' = ' . \str_replace("\n", '', \var_export($parameter->getDefaultValue(), true))
                    : $type;
            }

            $shape[$method->getName()] = [
                'parameters' => $parameters,
                'returns' => (string) ($method->getReturnType() ?? 'mixed'),
            ];
        }

        \ksort($shape);

        return $shape;
    }

    /**
     * The attributes each of the Word half's directives reads.
     *
     * @return array<string, list<string>>
     */
    public static function wordDirectiveAttributes(): array
    {
        $attributes = [];

        foreach (self::handlers() as $class) {
            $reflection = new \ReflectionClass($class);
            $invokable = $reflection->hasMethod('__invoke');

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($invokable && $method->getName() !== '__invoke') {
                    continue;
                }

                $directive = self::directiveOf($reflection, $method, $invokable);

                if ($directive === null) {
                    continue;
                }

                $attributes[$directive] = self::attributesRead($class, $method->getName());
            }
        }

        \ksort($attributes);

        return $attributes;
    }

    /**
     * The keys the Word half reads out of a document's frontmatter.
     *
     * @return list<string>
     */
    public static function wordFrontmatterKeys(): array
    {
        return self::keysRead(Frontmatter::class);
    }

    /**
     * The classes that draw the Word half's directives.
     *
     * Asked of the registry that holds them: the factory takes the ones that are always
     * drawn, and the handlers hold the rest — the type of a handler's property is the
     * class that draws it.
     *
     * @return list<class-string>
     */
    private static function handlers(): array
    {
        $classes = [];

        foreach ((new \ReflectionMethod(Directives::class, 'standard'))->getParameters() as $parameter) {
            $class = self::namedTypeOf($parameter->getType());

            if ($class !== null) {
                $classes[] = $class;
            }
        }

        foreach ((new \ReflectionClass(Handlers::class))->getProperties() as $property) {
            $class = self::namedTypeOf($property->getType());

            if ($class !== null) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * The class a type names, or null when it names no class.
     *
     * @return class-string|null
     */
    private static function namedTypeOf(?\ReflectionType $type): ?string
    {
        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        return $type->getName();
    }

    /**
     * The directive a method draws, as the code names it.
     *
     * The name is in the docblock of the method that draws it, or — for a class drawn
     * through `__invoke` — in the docblock of the class. A docblock that names no
     * directive draws none: the inline styles read a style's attributes, not those of
     * a directive.
     */
    private static function directiveOf(
        \ReflectionClass $class,
        \ReflectionMethod $method,
        bool $invokable,
    ): ?string {
        $docblocks = [$method->getDocComment()];

        if ($invokable) {
            $docblocks[] = $class->getDocComment();
        }

        foreach ($docblocks as $docblock) {
            if ($docblock === false) {
                continue;
            }

            if (\preg_match('/`::([a-z]+)/', $docblock, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }

    /**
     * The attribute names a method reads, and the ones the methods it calls read.
     *
     * @return list<string>
     */
    private static function attributesRead(string $class, string $method): array
    {
        $names = [];

        foreach (self::reached($class, $method) as $reached) {
            \preg_match_all(
                '/->(?:getList|getString|getFloat|getInt|getBool|get|has)\(\s*\'([^\']+)\'/',
                self::bodyOf($class, $reached),
                $matches,
            );

            foreach ($matches[1] as $name) {
                $names[$name] = true;
            }
        }

        return \array_keys($names);
    }

    /**
     * The methods of a class a method reaches, itself included.
     *
     * A helper's reads are the caller's too: the table of contents asks for its depth
     * in a helper, and the depth is an attribute of the directive.
     *
     * @return list<string>
     */
    private static function reached(string $class, string $method): array
    {
        $reached = [$method => true];
        $queue = [$method];

        while ($queue !== []) {
            $current = \array_shift($queue);

            \preg_match_all(
                '/(?:\$this->|self::|static::)([a-zA-Z_]\w*)\s*\(/',
                self::bodyOf($class, $current),
                $matches,
            );

            foreach ($matches[1] as $called) {
                if (isset($reached[$called]) || !\method_exists($class, $called)) {
                    continue;
                }

                $reached[$called] = true;
                $queue[] = $called;
            }
        }

        return \array_keys($reached);
    }

    /**
     * The source of a method, without its comments.
     */
    private static function bodyOf(string $class, string $method): string
    {
        $reflection = new \ReflectionMethod($class, $method);
        $lines = \file((string) $reflection->getFileName()) ?: [];
        $body = \implode('', \array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1,
        ));

        return self::withoutComments($body);
    }

    /**
     * The keys a class reads out of a mapping.
     *
     * A key is read by name, or by a loop over a list of them: the frontmatter block
     * hands the sections it understands over as a list, and each of them is a key of
     * the block.
     *
     * Read through the class itself, because a key of the block is one the block's own
     * reader asks for; the name the parser keeps the block under is not one of them.
     *
     * @return list<string>
     */
    private static function keysRead(string $class): array
    {
        $source = self::withoutComments(
            (string) \file_get_contents((string) (new \ReflectionClass($class))->getFileName()),
        );

        $keys = [];

        \preg_match_all('/\$this->(?:getArray|getString|getInt|getBool|get|has)\(\s*\'([^\']+)\'/', $source, $matches);

        foreach ($matches[1] as $key) {
            $keys[$key] = true;
        }

        \preg_match_all('/foreach \(\s*\[([^\]]*)\]/', $source, $matches);

        foreach ($matches[1] as $list) {
            \preg_match_all('/\'([^\']+)\'/', $list, $names);

            foreach ($names[1] as $name) {
                $keys[$name] = true;
            }
        }

        return \array_keys($keys);
    }

    /**
     * The code with its comments taken out.
     *
     * A comment names things in prose, and a name in prose is not a name the code uses:
     * the frontmatter block's own docblock names a `theme_file`, which the code does
     * not read.
     */
    private static function withoutComments(string $code): string
    {
        $kept = '';

        foreach (\token_get_all($code) as $token) {
            if (!\is_array($token)) {
                $kept .= $token;

                continue;
            }

            if ($token[0] === \T_COMMENT || $token[0] === \T_DOC_COMMENT) {
                continue;
            }

            $kept .= $token[1];
        }

        return $kept;
    }
}
