<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Parser\Value;

use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\ValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;
use Penyaskito\Dtcg\Tom\Value\CubicBezierValue;
use Penyaskito\Dtcg\Tom\Value\DurationValue;
use Penyaskito\Dtcg\Tom\Value\ReferenceValue;
use Penyaskito\Dtcg\Tom\Value\TransitionValue;

final class TransitionValueFactory implements ValueFactory
{
    use CompositeFieldTrait;

    private const ALLOWED_KEYS = ['duration', 'delay', 'timingFunction'];

    private readonly DurationValueFactory $durationFactory;

    private readonly CubicBezierValueFactory $cubicBezierFactory;

    public function __construct()
    {
        $this->durationFactory = new DurationValueFactory();
        $this->cubicBezierFactory = new CubicBezierValueFactory();
    }

    public function type(): Type
    {
        return Type::Transition;
    }

    public function create(mixed $raw, string $pointer): Value
    {
        if (!is_array($raw) || (array_is_list($raw) && $raw !== [])) {
            throw ParseError::at($pointer, 'transition $value must be an object');
        }

        foreach (array_keys($raw) as $key) {
            if (!in_array($key, self::ALLOWED_KEYS, true)) {
                throw ParseError::at(
                    $pointer,
                    sprintf("unknown transition property '%s'", (string) $key),
                );
            }
        }

        foreach (self::ALLOWED_KEYS as $required) {
            if (!array_key_exists($required, $raw)) {
                throw ParseError::at(
                    $pointer,
                    sprintf('transition $value.%s is required', $required),
                );
            }
        }

        $duration = $this->tryReadReference($raw['duration'], $pointer . '/duration')
            ?? $this->durationFactory->create($raw['duration'], $pointer . '/duration');
        $delay = $this->tryReadReference($raw['delay'], $pointer . '/delay')
            ?? $this->durationFactory->create($raw['delay'], $pointer . '/delay');
        $timingFunction = $this->tryReadReference($raw['timingFunction'], $pointer . '/timingFunction')
            ?? $this->cubicBezierFactory->create($raw['timingFunction'], $pointer . '/timingFunction');

        \assert($duration instanceof DurationValue || $duration instanceof ReferenceValue);
        \assert($delay instanceof DurationValue || $delay instanceof ReferenceValue);
        \assert($timingFunction instanceof CubicBezierValue || $timingFunction instanceof ReferenceValue);

        return new TransitionValue($duration, $delay, $timingFunction);
    }
}
