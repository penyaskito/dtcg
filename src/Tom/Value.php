<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom;

interface Value
{
    /**
     * The concrete type of this value, or null when the value is itself a
     * reference whose target type is not known at construction time (see
     * {@see Value\ReferenceValue}). All non-reference value classes return
     * a non-null Type.
     */
    public function type(): ?Type;
}
