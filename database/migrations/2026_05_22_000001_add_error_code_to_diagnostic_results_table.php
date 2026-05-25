<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('diagnostic_results', function (Blueprint $table) {
            $table->enum('error_code', ['B6', 'B8', 'B9', 'B12', 'C1', 'C3', 'C8', 'G3', 'G4', 'H1', 'NONE'])->default('NONE');
        });
    }

    public function down(): void
    {
        Schema::table('diagnostic_results', function (Blueprint $table) {
            $table->dropColumn('error_code');
        });
    }
};
