<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Validator;

enum ViolationSource: string
{
    case Schema = 'schema';
    case Semantic = 'semantic';
}
