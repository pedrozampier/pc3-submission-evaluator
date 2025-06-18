<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('common_comprehension_problems', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('problem_code')->unique();
            $table->string('problem_description');
            $table->integer('dif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('common_comprehension_problems');
    }
};
