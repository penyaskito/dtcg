<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom\Value;

use Penyaskito\Dtcg\Reference\Reference;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;

/**
 * A `$value` that is itself a reference — i.e. the DTCG
 * `$value: "{other.token}"` shorthand. Distinct from {@see \Penyaskito\Dtcg\Tom\ReferenceToken}
 * (which is `$ref`-bearing at the token level).
 *
 * Carries the parsed {@see Reference} — its concrete shape (curly-brace or
 * JSON-pointer-object) survives for round-trip serialization.
 */
final readonly class ReferenceValue implements Value
{
    public function __construct(
        public Reference $reference,
    ) {
    }

    /**
     * A reference value's concrete type is not known at construction time —
     * it matches the target's. Consumers that need the effective type should
     * use the enclosing {@see \Penyaskito\Dtcg\Tom\ValueToken::$type} (always
     * set, because strict mode requires it even for reference values).
     */
    public function type(): ?Type
    {
        return null;
    }
}
