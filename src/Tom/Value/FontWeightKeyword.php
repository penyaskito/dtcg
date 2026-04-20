<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom\Value;

enum FontWeightKeyword: string
{
    case Thin = 'thin';
    case Hairline = 'hairline';
    case ExtraLight = 'extra-light';
    case UltraLight = 'ultra-light';
    case Light = 'light';
    case Normal = 'normal';
    case Regular = 'regular';
    case Book = 'book';
    case Medium = 'medium';
    case SemiBold = 'semi-bold';
    case DemiBold = 'demi-bold';
    case Bold = 'bold';
    case ExtraBold = 'extra-bold';
    case UltraBold = 'ultra-bold';
    case Black = 'black';
    case Heavy = 'heavy';
    case ExtraBlack = 'extra-black';
    case UltraBlack = 'ultra-black';
}
