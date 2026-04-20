<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Validator;

use Penyaskito\Dtcg\Reference\Resolver;
use Penyaskito\Dtcg\Tom\Document;
use Penyaskito\Dtcg\Validator\Rule\AliasTargetExists;
use Penyaskito\Dtcg\Validator\Rule\Context;
use Penyaskito\Dtcg\Validator\Rule\NoAliasCycles;
use Penyaskito\Dtcg\Validator\Rule\NoExtendsCycles;
use Penyaskito\Dtcg\Validator\Rule\Rule;
use Penyaskito\Dtcg\Validator\Rule\TypeResolvable;

final class SemanticValidator
{
    /** @var list<Rule> */
    private readonly array $rules;

    /** @param list<Rule>|null $rules */
    public function __construct(?array $rules = null)
    {
        $this->rules = $rules ?? self::defaultRules();
    }

    /** @return list<Rule> */
    public static function defaultRules(): array
    {
        return [
            new AliasTargetExists(),
            new NoAliasCycles(),
            new TypeResolvable(),
            new NoExtendsCycles(),
        ];
    }

    /** @return list<Violation> */
    public function validate(Document $document): array
    {
        $context = new Context($document, new Resolver($document));

        $violations = [];
        foreach ($this->rules as $rule) {
            foreach ($rule->check($context) as $violation) {
                $violations[] = $violation;
            }
        }

        return $violations;
    }
}
