<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Tom;

use Penyaskito\Dtcg\Tom\Path;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Path::class)]
final class PathTest extends TestCase
{
    public function testRootIsEmpty(): void
    {
        $root = Path::root();

        self::assertTrue($root->isRoot());
        self::assertSame([], $root->segments);
        self::assertSame('', $root->toString());
        self::assertNull($root->last());
    }

    public function testFromDotsParsesDottedPath(): void
    {
        $path = Path::fromDots('a.b.c');

        self::assertSame(['a', 'b', 'c'], $path->segments);
        self::assertSame('c', $path->last());
        self::assertSame('a.b.c', $path->toString());
        self::assertFalse($path->isRoot());
    }

    public function testFromDotsWithEmptyStringReturnsRoot(): void
    {
        $path = Path::fromDots('');

        self::assertTrue($path->isRoot());
    }

    public function testAppendReturnsNewPath(): void
    {
        $original = Path::fromDots('a.b');
        $appended = $original->append('c');

        self::assertSame(['a', 'b'], $original->segments);
        self::assertSame(['a', 'b', 'c'], $appended->segments);
    }

    public function testCustomSeparator(): void
    {
        $path = Path::fromDots('a.b.c');

        self::assertSame('a/b/c', $path->toString('/'));
    }
}
