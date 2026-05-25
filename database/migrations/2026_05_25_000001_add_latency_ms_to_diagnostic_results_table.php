<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('diagnostic_results', function (Blueprint $table) {
            // default(0) is required so the ALTER succeeds against existing rows —
            // SQLite needs a default when adding a NOT NULL column to a populated table.
            $table->integer('latency_ms')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('diagnostic_results', function (Blueprint $table) {
            $table->dropColumn('latency_ms');
        });
    }
};
