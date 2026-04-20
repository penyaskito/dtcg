<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Reference;

use Penyaskito\Dtcg\Reference\CurlyBraceReference;
use Penyaskito\Dtcg\Reference\InvalidReferenceException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CurlyBraceReference::class)]
final class CurlyBraceReferenceTest extends TestCase
{
    public function testParsesSingleSegment(): void
    {
        $ref = CurlyBraceReference::parse('{base}');

        self::assertSame('{base}', $ref->original());
        self::assertSame(['base'], $ref->segments());
        self::assertSame('/base', $ref->toJsonPointer());
    }

    public function testParsesNestedPath(): void
    {
        $ref = CurlyBraceReference::parse('{colors.blue.500}');

        self::assertSame(['colors', 'blue', '500'], $ref->segments());
        self::assertSame('/colors/blue/500', $ref->toJsonPointer());
    }

    public function testEscapesTildeAndSlashInPointerOutput(): void
    {
        $ref = new CurlyBraceReference('{weird}', ['a/b', 'c~d']);

        self::assertSame('/a~1b/c~0d', $ref->toJsonPointer());
    }

    #[DataProvider('invalidReferences')]
    public function testRejectsMalformedInput(string $raw): void
    {
        $this->expectException(InvalidReferenceException::class);
        CurlyBraceReference::parse($raw);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidReferences(): iterable
    {
        yield 'missing braces' => ['colors.blue'];
        yield 'only opening brace' => ['{colors.blue'];
        yield 'empty path' => ['{}'];
        yield 'segment starts with $' => ['{$value}'];
        yield 'segment contains brace' => ['{foo{bar}'];
        yield 'double dot' => ['{foo..bar}'];
    }
}
