<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('diagnostic_results', function (Blueprint $table) {
            // D-05 pattern: rawColumn() for CHECK constraints (Blueprint::check() absent in Laravel 13.6.0).
            // default 'NONE' is required so the ALTER works against any existing rows — SQLite requires
            // a default when adding a NOT NULL column to a populated table.
            $table->rawColumn(
                'error_code',
                "varchar not null default 'NONE' constraint check_error_code check (error_code IN ('B6', 'B8', 'B9', 'B12', 'C1', 'C3', 'C8', 'G3', 'G4', 'H1', 'NONE'))"
            );
        });
    }

    public function down(): void
    {
        Schema::table('diagnostic_results', function (Blueprint $table) {
            $table->dropColumn('error_code');
        });
    }
};
