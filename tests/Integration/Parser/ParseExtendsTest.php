<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Integration\Parser;

use PHPUnit\Framework\TestCase;
use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\Parser;
use Penyaskito\Dtcg\Reference\CurlyBraceReference;
use Penyaskito\Dtcg\Reference\JsonPointerReference;
use Penyaskito\Dtcg\Tom\Group;
use Penyaskito\Dtcg\Tom\Value\DimensionValue;
use Penyaskito\Dtcg\Tom\ValueToken;

final class ParseExtendsTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../fixtures/valid/extends.tokens.json';

    public function testMaterializesInheritedChildrenPreservingOwnOrder(): void
    {
        $doc = (new Parser())->parseFile(self::FIXTURE);
        $spacing = $doc->root->child('spacing');

        self::assertInstanceOf(Group::class, $spacing);
        // Own child first, inherited children follow.
        self::assertSame(
            ['large', 'small', 'medium'],
            array_keys($spacing->children),
        );
    }

    public function testInheritedTokensGetRelocatedPathsPointingAtTheNewParent(): void
    {
        $doc = (new Parser())->parseFile(self::FIXTURE);
        $spacing = $doc->root->child('spacing');
        self::assertInstanceOf(Group::class, $spacing);

        $small = $spacing->child('small');
        self::assertInstanceOf(ValueToken::class, $small);
        self::assertSame('spacing.small', $small->path->toString());
    }

    public function testInheritedFromMapRecordsOriginGroupForInheritedChildren(): void
    {
        $doc = (new Parser())->parseFile(self::FIXTURE);
        $spacing = $doc->root->child('spacing');
        self::assertInstanceOf(Group::class, $spacing);

        self::assertArrayHasKey('small', $spacing->inheritedFrom);
        self::assertArrayHasKey('medium', $spacing->inheritedFrom);
        self::assertArrayNotHasKey('large', $spacing->inheritedFrom);
        self::assertSame('base', $spacing->inheritedFrom['small']->toString());
    }

    public function testOwnChildShadowsInheritedChildWithSameName(): void
    {
        $doc = (new Parser())->parseFile(self::FIXTURE);
        $compact = $doc->root->child('compact');
        self::assertInstanceOf(Group::class, $compact);

        // `compact.medium` is the own-declared override, not the inherited one.
        $medium = $compact->child('medium');
        self::assertInstanceOf(ValueToken::class, $medium);
        self::assertInstanceOf(DimensionValue::class, $medium->value);
        self::assertSame(6.0, $medium->value->value);

        // inheritedFrom should not contain `medium` (it was overridden).
        self::assertArrayNotHasKey('medium', $compact->inheritedFrom);

        // `small` is still inherited.
        self::assertArrayHasKey('small', $compact->inheritedFrom);
    }

    public function testInheritedValueTokensRetainTheirTypeFromOriginGroup(): void
    {
        $doc = (new Parser())->parseFile(self::FIXTURE);
        $spacing = $doc->root->child('spacing');
        self::assertInstanceOf(Group::class, $spacing);

        $small = $spacing->child('small');
        self::assertInstanceOf(ValueToken::class, $small);
        self::assertSame('dimension', $small->type->value);
    }

    public function testCurlyBraceExtendsIsParsedAsReference(): void
    {
        $doc = (new Parser())->parseFile(self::FIXTURE);
        $compact = $doc->root->child('compact');
        self::assertInstanceOf(Group::class, $compact);

        self::assertInstanceOf(CurlyBraceReference::class, $compact->extendsFrom);
        self::assertSame('{base}', $compact->extendsFrom->original());
    }

    public function testJsonPointerExtendsIsParsedAsReference(): void
    {
        $doc = (new Parser())->parseFile(self::FIXTURE);
        $spacing = $doc->root->child('spacing');
        self::assertInstanceOf(Group::class, $spacing);

        self::assertInstanceOf(JsonPointerReference::class, $spacing->extendsFrom);
        self::assertSame('#/base', $spacing->extendsFrom->original());
    }

    public function testMultiLevelChainPreservesOriginalOriginPath(): void
    {
        // A extends B extends C. A's inherited tokens from C should record
        // C as their origin, not B.
        $doc = (new Parser())->parseArray([
            'c' => [
                '$type' => 'dimension',
                'x' => ['$value' => ['value' => 1, 'unit' => 'px']],
            ],
            'b' => [
                '$extends' => '#/c',
                '$type' => 'dimension',
                'y' => ['$value' => ['value' => 2, 'unit' => 'px']],
            ],
            'a' => [
                '$extends' => '#/b',
            ],
        ]);

        $a = $doc->root->child('a');
        self::assertInstanceOf(Group::class, $a);

        // 'x' came from c (via b); 'y' came from b directly.
        self::assertArrayHasKey('x', $a->children);
        self::assertArrayHasKey('y', $a->children);
        self::assertSame('c', $a->inheritedFrom['x']->toString());
        self::assertSame('b', $a->inheritedFrom['y']->toString());
    }

    public function testRejectsNonStringExtends(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('$extends must be a string reference (at /bad)');
        (new Parser())->parseArray([
            'bad' => ['$extends' => 42],
        ]);
    }

    public function testRejectsMalformedExtends(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessageMatches("/invalid \\\$extends: .* \\(at \\/bad\\)/");
        (new Parser())->parseArray([
            'bad' => ['$extends' => 'not-a-reference'],
        ]);
    }

    public function testRejectsUnresolvableExtendsTarget(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage("\$extends target '#/does/not/exist' cannot be resolved");
        (new Parser())->parseArray([
            'x' => ['$extends' => '#/does/not/exist'],
        ]);
    }

    public function testRejectsExtendsTargetThatIsAToken(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage("must be a group, got a token");
        (new Parser())->parseArray([
            'base' => ['$type' => 'dimension', '$value' => ['value' => 1, 'unit' => 'px']],
            'bad' => ['$extends' => '#/base'],
        ]);
    }

    public function testDetectsSelfReferentialExtendsCycle(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('$extends cycle detected');
        (new Parser())->parseArray([
            'a' => ['$extends' => '#/a'],
        ]);
    }

    public function testDetectsTwoNodeExtendsCycle(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('$extends cycle detected');
        (new Parser())->parseArray([
            'a' => ['$extends' => '#/b'],
            'b' => ['$extends' => '#/a'],
        ]);
    }
}
