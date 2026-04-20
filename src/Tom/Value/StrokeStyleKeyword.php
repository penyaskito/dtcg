<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom\Value;

enum StrokeStyleKeyword: string
{
    case Solid = 'solid';
    case Dashed = 'dashed';
    case Dotted = 'dotted';
    case Double = 'double';
    case Groove = 'groove';
    case Ridge = 'ridge';
    case Outset = 'outset';
    case Inset = 'inset';
}
