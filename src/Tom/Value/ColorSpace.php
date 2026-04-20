<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom\Value;

enum ColorSpace: string
{
    case Srgb = 'srgb';
    case SrgbLinear = 'srgb-linear';
    case Hsl = 'hsl';
    case Hwb = 'hwb';
    case Lab = 'lab';
    case Lch = 'lch';
    case Oklab = 'oklab';
    case Oklch = 'oklch';
    case DisplayP3 = 'display-p3';
    case A98Rgb = 'a98-rgb';
    case ProphotoRgb = 'prophoto-rgb';
    case Rec2020 = 'rec2020';
    case XyzD65 = 'xyz-d65';
    case XyzD50 = 'xyz-d50';
}
