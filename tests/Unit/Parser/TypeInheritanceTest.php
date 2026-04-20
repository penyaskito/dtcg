<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Parser;

use Penyaskito\Dtcg\Parser\Parser;
use Penyaskito\Dtcg\Tom\Group;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\ValueToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Parser::class)]
final class TypeInheritanceTest extends TestCase
{
    public function testInnerGroupTypeOverridesOuterGroupType(): void
    {
        $doc = (new Parser())->parseArray([
            '$type' => 'dimension',
            'inner' => [
                '$type' => 'number',
                'leaf' => ['$value' => 1.5],
            ],
            'outerLeaf' => [
                '$value' => ['value' => 8, 'unit' => 'px'],
            ],
        ]);

        $inner = $doc->root->child('inner');
        self::assertInstanceOf(Group::class, $inner);
        self::assertSame(Type::Number, $inner->defaultType);

        $leaf = $inner->child('leaf');
        self::assertInstanceOf(ValueToken::class, $leaf);
        self::assertSame(Type::Number, $leaf->type);

        $outerLeaf = $doc->root->child('outerLeaf');
        self::assertInstanceOf(ValueToken::class, $outerLeaf);
        self::assertSame(Type::Dimension, $outerLeaf->type);
    }

    public function testTokenOwnTypeOverridesInheritedType(): void
    {
        $doc = (new Parser())->parseArray([
            'outer' => [
                '$type' => 'dimension',
                'special' => [
                    '$type' => 'number',
                    '$value' => 42,
                ],
            ],
        ]);

        $outer = $doc->root->child('outer');
        self::assertInstanceOf(Group::class, $outer);

        $special = $outer->child('special');
        self::assertInstanceOf(ValueToken::class, $special);
        self::assertSame(Type::Number, $special->type);
    }

    public function testTypeInheritanceSpansMultipleLevels(): void
    {
        $doc = (new Parser())->parseArray([
            '$type' => 'duration',
            'group1' => [
                'group2' => [
                    'leaf' => ['$value' => ['value' => 200, 'unit' => 'ms']],
                ],
            ],
        ]);

        $group1 = $doc->root->child('group1');
        self::assertInstanceOf(Group::class, $group1);
        self::assertNull($group1->defaultType, 'group1 does not declare its own $type');

        $group2 = $group1->child('group2');
        self::assertInstanceOf(Group::class, $group2);

        $leaf = $group2->child('leaf');
        self::assertInstanceOf(ValueToken::class, $leaf);
        self::assertSame(Type::Duration, $leaf->type);
    }
}
