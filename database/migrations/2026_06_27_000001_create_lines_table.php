<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');

        Schema::create('lines', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name')->nullable();
            $table->string('color')->nullable();
            $table->jsonb('geo_json')->nullable();
            $table->string('sense', 20)->default('OUTBOUND');
            $table->foreignId('parent_line_id')->nullable()->constrained('lines')->nullOnDelete();
            $table->string('syndicate')->nullable();
            $table->integer('objectid')->nullable();
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->integer('total_reviews')->default(0);
            $table->timestamps();

            $table->index('code');
            $table->index('parent_line_id');
        });

        DB::statement('ALTER TABLE lines ADD COLUMN geom geometry(MultiLineString, 4326)');
        DB::statement('CREATE INDEX idx_lines_geom ON lines USING GIST(geom)');
        DB::statement("ALTER TABLE lines ADD CONSTRAINT lines_sense_check CHECK (sense IN ('OUTBOUND', 'RETURN'))");
        DB::statement('UPDATE lines SET geom = ST_GeomFromGeoJSON(geo_json::text) WHERE geo_json IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_lines_geom');
        Schema::dropIfExists('lines');
    }
};
