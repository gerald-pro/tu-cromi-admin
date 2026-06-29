<?php

namespace App\Console\Commands;

use App\Enums\LineSense;
use App\Models\Line;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LinesImport extends Command
{
    protected $signature = 'lines:import
        {--force : Truncate existing lines before import}
        {--path= : Path to the GeoJSON file}';

    protected $description = 'Import lines from a GeoJSON file';

    private function reverseMultiLineString(array $coords): array
    {
        return array_reverse(
            array_map(fn (array $seg) => array_reverse($seg), $coords)
        );
    }

    public function handle(): int
    {
        if (Line::count() > 0 && ! $this->option('force')) {
            $this->error('Lines already exist. Use --force to truncate and re-import.');

            return self::FAILURE;
        }

        if ($this->option('force')) {
            $this->warn('Truncating lines table...');
            DB::statement('TRUNCATE TABLE lines CASCADE');
        }

        $path = $this->option('path') ?: database_path('data/rutas_scz.geojson');

        if (! file_exists($path)) {
            $this->error("GeoJSON file not found: {$path}");

            return self::FAILURE;
        }

        $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        $features = $data['features'] ?? [];

        if (empty($features)) {
            $this->warn('No features found in GeoJSON file.');

            return self::SUCCESS;
        }

        $this->info('Importing '.count($features).' features...');

        $lines = [];

        foreach ($features as $feature) {
            $props = $feature['properties'];
            $geometry = $feature['geometry'];

            $sentido = (int) ($props['sentido'] ?? 1);
            $sense = $sentido === 1 ? LineSense::Outbound : LineSense::Return;

            $coords = $geometry['coordinates'];
            if ($sentido !== 1) {
                $coords = $this->reverseMultiLineString($coords);
            }

            $lines[] = [
                'objectid' => $props['objectid'] ?? null,
                'code' => $props['nombre'] ?? '',
                'sense' => $sense->value,
                'syndicate' => isset($props['sindicato']) ? (string) $props['sindicato'] : null,
                'geo_json' => json_encode([
                    'type' => 'MultiLineString',
                    'coordinates' => $coords,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Line::insert($lines);

        $this->info('Created '.count($lines).' lines');

        $this->linkOppositeLines();
        $this->populateGeometry();

        return self::SUCCESS;
    }

    private function linkOppositeLines(): void
    {
        $lines = Line::all(['id', 'code', 'sense']);
        $grouped = $lines->groupBy('code');

        $updated = 0;

        foreach ($grouped as $code => $group) {
            if ($group->count() !== 2) {
                continue;
            }

            $outbound = $group->firstWhere('sense', LineSense::Outbound);
            $return = $group->firstWhere('sense', LineSense::Return);

            if (! $outbound || ! $return) {
                continue;
            }

            $outbound->parent_line_id = $return->id;
            $return->parent_line_id = $outbound->id;

            $outbound->save();
            $return->save();

            $updated += 2;
        }

        $this->info('Linked '.($updated / 2).' pairs of opposite lines.');
    }

    private function populateGeometry(): void
    {
        $affected = DB::statement(
            'UPDATE lines SET geom = ST_GeomFromGeoJSON(geo_json::text) WHERE geo_json IS NOT NULL AND geom IS NULL'
        );

        $this->info("Geometry column populated for {$affected} lines.");
    }
}
