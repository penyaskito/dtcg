<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Reference;

use Penyaskito\Dtcg\Reference\CycleDetector;
use Penyaskito\Dtcg\Reference\CyclicReferenceException;
use Penyaskito\Dtcg\Tom\Path;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CycleDetector::class)]
#[CoversClass(CyclicReferenceException::class)]
final class CycleDetectorTest extends TestCase
{
    public function testDistinctPathsDoNotRaise(): void
    {
        $detector = new CycleDetector();
        $detector->visit(Path::fromDots('a'));
        $detector->visit(Path::fromDots('b'));
        $detector->visit(Path::fromDots('c'));

        $this->expectNotToPerformAssertions();
    }

    public function testRevisitingAPathThrows(): void
    {
        $detector = new CycleDetector();
        $detector->visit(Path::fromDots('a.b'));

        try {
            $detector->visit(Path::fromDots('a.b'));
            self::fail('expected CyclicReferenceException');
        } catch (CyclicReferenceException $e) {
            self::assertSame(['a.b', 'a.b'], $e->trail);
            self::assertStringContainsString('a.b -> a.b', $e->getMessage());
        }
    }

    public function testTrailShowsFullHistoryOnCycle(): void
    {
        $detector = new CycleDetector();
        $detector->visit(Path::fromDots('x'));
        $detector->visit(Path::fromDots('y'));

        try {
            $detector->visit(Path::fromDots('x'));
            self::fail('expected CyclicReferenceException');
        } catch (CyclicReferenceException $e) {
            self::assertSame(['x', 'y', 'x'], $e->trail);
        }
    }
}
