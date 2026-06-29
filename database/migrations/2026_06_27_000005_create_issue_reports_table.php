<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50);
            $table->text('description');
            $table->json('location')->nullable();
            $table->string('status', 50)->default('PENDING');
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_reports');
    }
};
