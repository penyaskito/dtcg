<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Tom;

use Penyaskito\Dtcg\Tom\SourceMap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceMap::class)]
final class SourceMapTest extends TestCase
{
    public function testCarriesUriAndPointer(): void
    {
        $sm = new SourceMap('file:///tokens.json', '/colors/blue');

        self::assertSame('file:///tokens.json', $sm->uri);
        self::assertSame('/colors/blue', $sm->pointer);
    }

    public function testSyntheticHasNullUri(): void
    {
        $sm = SourceMap::synthetic('/colors/blue');

        self::assertNull($sm->uri);
        self::assertSame('/colors/blue', $sm->pointer);
    }

    public function testSyntheticDefaultsToRootPointer(): void
    {
        $sm = SourceMap::synthetic();

        self::assertNull($sm->uri);
        self::assertSame('', $sm->pointer);
    }
}
