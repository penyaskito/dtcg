<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom;

use Generator;

final class Walker
{
    /** @return Generator<Token> */
    public static function tokens(Document|Group $root): Generator
    {
        $group = $root instanceof Document ? $root->root : $root;
        yield from self::walkTokens($group);
    }

    /** @return Generator<Group> */
    public static function groups(Document|Group $root): Generator
    {
        $group = $root instanceof Document ? $root->root : $root;
        yield $group;
        yield from self::walkGroups($group);
    }

    /** @return Generator<Token> */
    private static function walkTokens(Group $group): Generator
    {
        foreach ($group->children as $child) {
            if ($child instanceof Token) {
                yield $child;
            } elseif ($child instanceof Group) {
                yield from self::walkTokens($child);
            }
        }
    }

    /** @return Generator<Group> */
    private static function walkGroups(Group $group): Generator
    {
        foreach ($group->children as $child) {
            if ($child instanceof Group) {
                yield $child;
                yield from self::walkGroups($child);
            }
        }
    }
}
