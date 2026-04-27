<?php

declare(strict_types=1);

namespace App\DTOs;

enum Pc3Category: string
{
    case Predicate = 'Predicate';
    case Concept   = 'Concept';
    case Context   = 'Context';
}
