<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_a_id')->constrained('lines')->cascadeOnDelete();
            $table->foreignId('line_b_id')->constrained('lines')->cascadeOnDelete();
            $table->float('point_a_lng');
            $table->float('point_a_lat');
            $table->integer('point_a_index');
            $table->float('point_b_lng');
            $table->float('point_b_lat');
            $table->integer('point_b_index');
            $table->float('walk_distance');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_transfers');
    }
};
