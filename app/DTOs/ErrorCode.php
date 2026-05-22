<?php

declare(strict_types=1);

namespace App\DTOs;

enum ErrorCode: string
{
    case B6   = 'B6';
    case B8   = 'B8';
    case B9   = 'B9';
    case B12  = 'B12';
    case C1   = 'C1';
    case C3   = 'C3';
    case C8   = 'C8';
    case G3   = 'G3';
    case G4   = 'G4';
    case H1   = 'H1';
    case None = 'NONE';
}
