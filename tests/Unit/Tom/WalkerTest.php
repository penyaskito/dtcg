<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Tom;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Penyaskito\Dtcg\Parser\Parser;
use Penyaskito\Dtcg\Tom\Group;
use Penyaskito\Dtcg\Tom\Token;
use Penyaskito\Dtcg\Tom\Walker;

#[CoversClass(Walker::class)]
final class WalkerTest extends TestCase
{
    public function testYieldsAllTokensFromNestedDocument(): void
    {
        $doc = (new Parser())->parseArray([
            'spacing' => [
                '$type' => 'dimension',
                'base' => ['$value' => ['value' => 8, 'unit' => 'px']],
                'nested' => [
                    'small' => ['$value' => ['value' => 4, 'unit' => 'px']],
                ],
            ],
            'count' => ['$type' => 'number', '$value' => 1],
        ]);

        $paths = array_map(
            static fn (Token $t): string => $t->path->toString(),
            iterator_to_array(Walker::tokens($doc), false),
        );

        sort($paths);
        self::assertSame(
            ['count', 'spacing.base', 'spacing.nested.small'],
            $paths,
        );
    }

    public function testTokensAcceptsGroupDirectly(): void
    {
        $doc = (new Parser())->parseArray([
            'inner' => [
                '$type' => 'number',
                'a' => ['$value' => 1],
                'b' => ['$value' => 2],
            ],
            'outer' => ['$type' => 'number', '$value' => 99],
        ]);

        $inner = $doc->root->child('inner');
        self::assertInstanceOf(Group::class, $inner);

        $names = array_map(
            static fn (Token $t): string => $t->name,
            iterator_to_array(Walker::tokens($inner), false),
        );

        sort($names);
        self::assertSame(['a', 'b'], $names);
    }

    public function testGroupsYieldsRootAndAllDescendantGroups(): void
    {
        $doc = (new Parser())->parseArray([
            'a' => [
                'b' => [
                    'leaf' => ['$type' => 'number', '$value' => 1],
                ],
            ],
            'c' => [
                'leaf' => ['$type' => 'number', '$value' => 2],
            ],
        ]);

        $paths = array_map(
            static fn (Group $g): string => $g->path->toString(),
            iterator_to_array(Walker::groups($doc), false),
        );

        sort($paths);
        self::assertSame(['', 'a', 'a.b', 'c'], $paths);
    }

    public function testEmptyDocumentYieldsNoTokens(): void
    {
        $doc = (new Parser())->parseArray([]);

        self::assertSame([], iterator_to_array(Walker::tokens($doc), false));
    }
}
