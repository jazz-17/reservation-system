<?php

namespace App\Models\Enums;

enum BookingMode: string
{
    case FixedDuration = 'fixed_duration';
    case VariableDuration = 'variable_duration';
    case PredefinedBlocks = 'predefined_blocks';
}

