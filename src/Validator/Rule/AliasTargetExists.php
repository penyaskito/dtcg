<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Validator\Rule;

use Penyaskito\Dtcg\Reference\ReferenceCollector;
use Penyaskito\Dtcg\Reference\UnresolvableReferenceException;
use Penyaskito\Dtcg\Tom\Walker;
use Penyaskito\Dtcg\Validator\Violation;
use Penyaskito\Dtcg\Validator\ViolationSource;

final class AliasTargetExists implements Rule
{
    public function name(): string
    {
        return 'AliasTargetExists';
    }

    public function check(Context $context): iterable
    {
        foreach (Walker::tokens($context->document) as $token) {
            foreach (ReferenceCollector::inToken($token) as $reference) {
                try {
                    $context->resolver->resolve($reference);
                } catch (UnresolvableReferenceException $e) {
                    yield new Violation(
                        source: ViolationSource::Semantic,
                        path: $token->path->toString(),
                        message: $e->getMessage(),
                        constraint: $this->name(),
                    );
                }
            }
        }
    }
}
