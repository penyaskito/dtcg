<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Penyaskito\Dtcg\Reference\InvalidReferenceException;
use Penyaskito\Dtcg\Reference\JsonPointerReference;

#[CoversClass(JsonPointerReference::class)]
final class JsonPointerReferenceTest extends TestCase
{
    public function testParsesTokenReference(): void
    {
        $ref = JsonPointerReference::parse('#/colors/blue');

        self::assertSame('#/colors/blue', $ref->original());
        self::assertSame(['colors', 'blue'], $ref->segments());
        self::assertSame('/colors/blue', $ref->toJsonPointer());
    }

    public function testParsesPropertyLevelReferenceThroughDollarValue(): void
    {
        $ref = JsonPointerReference::parse('#/colors/blue/$value/components/0');

        self::assertSame(
            ['colors', 'blue', '$value', 'components', '0'],
            $ref->segments(),
        );
    }

    public function testUnescapesRfc6901SequencesWhenParsing(): void
    {
        $ref = JsonPointerReference::parse('#/a~1b/c~0d');

        self::assertSame(['a/b', 'c~d'], $ref->segments());
        self::assertSame('/a~1b/c~0d', $ref->toJsonPointer());
    }

    public function testParsesRootFragment(): void
    {
        $ref = JsonPointerReference::parse('#');

        self::assertSame([], $ref->segments());
        self::assertSame('', $ref->toJsonPointer());
    }

    public function testRejectsMissingHash(): void
    {
        $this->expectException(InvalidReferenceException::class);
        JsonPointerReference::parse('/colors/blue');
    }

    public function testRejectsEmptyString(): void
    {
        $this->expectException(InvalidReferenceException::class);
        JsonPointerReference::parse('');
    }

    public function testConsecutiveSlashesProduceEmptySegment(): void
    {
        $ref = JsonPointerReference::parse('#/a//b');

        self::assertSame(['a', '', 'b'], $ref->segments());
    }

    public function testHashSlashOnlyIsRootReference(): void
    {
        $ref = JsonPointerReference::parse('#/');

        self::assertSame([], $ref->segments());
    }
}
