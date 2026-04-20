<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Tom;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Penyaskito\Dtcg\Tom\Metadata;

#[CoversClass(Metadata::class)]
final class MetadataTest extends TestCase
{
    public function testEmptyHasNoFieldsSet(): void
    {
        $m = Metadata::empty();

        self::assertNull($m->description);
        self::assertSame([], $m->extensions);
        self::assertNull($m->deprecated);
    }

    public function testCarriesAllThreeFields(): void
    {
        $m = new Metadata(
            description: 'the description',
            extensions: ['com.example' => ['a' => 1]],
            deprecated: 'use newer version',
        );

        self::assertSame('the description', $m->description);
        self::assertSame(['com.example' => ['a' => 1]], $m->extensions);
        self::assertSame('use newer version', $m->deprecated);
    }

    public function testDeprecatedAcceptsBool(): void
    {
        $m = new Metadata(deprecated: true);

        self::assertSame(true, $m->deprecated);
    }
}
