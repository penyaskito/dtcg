<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Reference;

use Penyaskito\Dtcg\Reference\CurlyBraceReference;
use Penyaskito\Dtcg\Reference\InvalidReferenceException;
use Penyaskito\Dtcg\Reference\JsonPointerReference;
use Penyaskito\Dtcg\Reference\ReferenceParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReferenceParser::class)]
final class ReferenceParserTest extends TestCase
{
    public function testDispatchesToCurlyBrace(): void
    {
        $ref = ReferenceParser::parse('{colors.blue}');

        self::assertInstanceOf(CurlyBraceReference::class, $ref);
    }

    public function testDispatchesToJsonPointer(): void
    {
        $ref = ReferenceParser::parse('#/colors/blue/$value');

        self::assertInstanceOf(JsonPointerReference::class, $ref);
    }

    public function testLooksLikeReferenceHelper(): void
    {
        self::assertTrue(ReferenceParser::looksLikeReference('{foo}'));
        self::assertTrue(ReferenceParser::looksLikeReference('#/foo'));
        self::assertFalse(ReferenceParser::looksLikeReference('plain string'));
        self::assertFalse(ReferenceParser::looksLikeReference(''));
    }

    public function testRejectsUnrecognizedSyntax(): void
    {
        $this->expectException(InvalidReferenceException::class);
        ReferenceParser::parse('not a reference');
    }
}
