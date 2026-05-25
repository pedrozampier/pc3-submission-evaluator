<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExerciseLabel extends Model
{
    protected $fillable = [
        'anchor_request_id',
        'label',
    ];
}
