<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom;

enum Type: string
{
    case Color = 'color';
    case Dimension = 'dimension';
    case FontFamily = 'fontFamily';
    case FontWeight = 'fontWeight';
    case Duration = 'duration';
    case CubicBezier = 'cubicBezier';
    case Number = 'number';
    case StrokeStyle = 'strokeStyle';
    case Border = 'border';
    case Transition = 'transition';
    case Shadow = 'shadow';
    case Gradient = 'gradient';
    case Typography = 'typography';
}
