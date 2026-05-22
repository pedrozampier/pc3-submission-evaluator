<?php

declare(strict_types=1);

namespace App\Models;

use App\DTOs\ErrorCode;
use App\DTOs\Pc3Category;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiagnosticResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'model',
        'diagnosis',
        'pc3_category',
        'error_code',
        'feedback',
        'confidence',
        'tokens_input',
        'tokens_output',
        'request_id',
        'prompt_version',
    ];

    protected function casts(): array
    {
        return [
            'pc3_category'  => Pc3Category::class,
            'error_code'    => ErrorCode::class,
            'confidence'    => 'float',
            'tokens_input'  => 'integer',
            'tokens_output' => 'integer',
        ];
    }
}
