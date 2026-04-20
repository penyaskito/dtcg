<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Parser\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\Value\TransitionValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value\TransitionValue;

#[CoversClass(TransitionValueFactory::class)]
final class TransitionValueFactoryTest extends TestCase
{
    private const D200 = ['value' => 200, 'unit' => 'ms'];

    private const BEZIER = [0.42, 0, 0.58, 1];

    public function testHappyPath(): void
    {
        $value = (new TransitionValueFactory())->create(
            [
                'duration' => self::D200,
                'delay' => ['value' => 0, 'unit' => 'ms'],
                'timingFunction' => self::BEZIER,
            ],
            '/t',
        );

        self::assertInstanceOf(TransitionValue::class, $value);
        self::assertSame(Type::Transition, $value->type());
    }

    public function testRejectsNonObject(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('transition $value must be an object (at /t)');
        (new TransitionValueFactory())->create('not an object', '/t');
    }

    public function testRejectsListInput(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('transition $value must be an object (at /t)');
        (new TransitionValueFactory())->create([1, 2, 3], '/t');
    }

    public function testRejectsUnknownProperty(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage("unknown transition property 'iterationCount' (at /t)");
        (new TransitionValueFactory())->create(
            [
                'duration' => self::D200,
                'delay' => self::D200,
                'timingFunction' => self::BEZIER,
                'iterationCount' => 3,
            ],
            '/t',
        );
    }

    public function testRejectsMissingDuration(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('transition $value.duration is required (at /t)');
        (new TransitionValueFactory())->create(
            ['delay' => self::D200, 'timingFunction' => self::BEZIER],
            '/t',
        );
    }

    public function testRejectsMissingDelay(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('transition $value.delay is required (at /t)');
        (new TransitionValueFactory())->create(
            ['duration' => self::D200, 'timingFunction' => self::BEZIER],
            '/t',
        );
    }

    public function testRejectsMissingTimingFunction(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('transition $value.timingFunction is required (at /t)');
        (new TransitionValueFactory())->create(
            ['duration' => self::D200, 'delay' => self::D200],
            '/t',
        );
    }

    public function testPropagatesDurationSubFactoryError(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('(at /t/duration)');
        (new TransitionValueFactory())->create(
            [
                'duration' => ['value' => 1, 'unit' => 'm'],
                'delay' => self::D200,
                'timingFunction' => self::BEZIER,
            ],
            '/t',
        );
    }

    public function testPropagatesDelaySubFactoryError(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('(at /t/delay)');
        (new TransitionValueFactory())->create(
            [
                'duration' => self::D200,
                'delay' => ['value' => 1, 'unit' => 'm'],
                'timingFunction' => self::BEZIER,
            ],
            '/t',
        );
    }

    public function testPropagatesCubicBezierSubFactoryError(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('(at /t/timingFunction)');
        (new TransitionValueFactory())->create(
            [
                'duration' => self::D200,
                'delay' => self::D200,
                'timingFunction' => [1.5, 0, 0.5, 1],
            ],
            '/t',
        );
    }
}
