<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('diagnostic_results', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('model');
            $table->text('diagnosis');
            $table->enum('pc3_category', ['Predicate', 'Concept', 'Context']);
            $table->text('feedback');
            $table->float('confidence');
            $table->integer('tokens_input');
            $table->integer('tokens_output');
            $table->uuid('request_id');
            $table->string('prompt_version');
            $table->timestamps();

            // Optional but useful for grouping rows by submission (4 rows per request_id).
            $table->index('request_id', 'idx_diagnostic_results_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostic_results');
    }
};
