<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Validator\Rule;

use Penyaskito\Dtcg\Reference\CyclicReferenceException;
use Penyaskito\Dtcg\Reference\UnresolvableReferenceException;
use Penyaskito\Dtcg\Tom\Group;
use Penyaskito\Dtcg\Tom\ReferenceToken;
use Penyaskito\Dtcg\Tom\Walker;
use Penyaskito\Dtcg\Validator\Violation;
use Penyaskito\Dtcg\Validator\ViolationSource;

final class TypeResolvable implements Rule
{
    public function name(): string
    {
        return 'TypeResolvable';
    }

    public function check(Context $context): iterable
    {
        foreach (Walker::tokens($context->document) as $token) {
            if (!$token instanceof ReferenceToken) {
                continue;
            }

            if ($token->declaredType !== null) {
                continue;
            }

            try {
                $target = $context->resolver->resolveChain($token->reference);
            } catch (UnresolvableReferenceException | CyclicReferenceException) {
                // Those are other rules' business; we'd only double-report.
                continue;
            }

            if ($target instanceof Group) {
                yield new Violation(
                    source: ViolationSource::Semantic,
                    path: $token->path->toString(),
                    message: sprintf(
                        "reference '%s' resolves to a group; a token type cannot be inferred",
                        $token->reference->original(),
                    ),
                    constraint: $this->name(),
                );
            }
        }
    }
}
