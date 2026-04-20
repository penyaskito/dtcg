<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Validator\Rule;

use Penyaskito\Dtcg\Validator\Violation;

interface Rule
{
    /**
     * Identifier for this rule — surfaces as the Violation::$constraint field.
     */
    public function name(): string;

    /**
     * @return iterable<Violation>
     */
    public function check(Context $context): iterable;
}
