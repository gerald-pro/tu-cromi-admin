<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
