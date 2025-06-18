<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommonComprehensionProblem extends Model
{
    protected $table = 'common_comprehension_problems';

    protected $fillable = [
        'problem_code',
        'problem_description',
        'dif',
    ];
}
