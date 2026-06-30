<?php

namespace App\Console\Commands;

use App\Models\Line;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TransfersCompute extends Command
{
    protected $signature = 'transfers:compute';

    protected $description = 'Precompute pedestrian transfer points between lines';

    private const TRANSFER_RADIUS = 300;

    private const BATCH_SIZE = 500;

    private const MIN_SEPARATION = 100;

    public function handle(): int
    {
        $startTime = microtime(true);

        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');

        $lineCount = Line::count();
        $this->info("Lines found: {$lineCount}");

        // ── 1. Calcular forwards via PostGIS ─────────────────────────────
        $this->info('Computing forward transfers via PostGIS...');

        $forwardRows = DB::select('
            SELECT
                a.id          AS line_a_id,
                b.id          AS line_b_id,
                ST_X(pa.geom) AS point_a_lng,
                ST_Y(pa.geom) AS point_a_lat,
                (pa.path[1] - 1) AS point_a_index,
                ST_X(pb.geom) AS point_b_lng,
                ST_Y(pb.geom) AS point_b_lat,
                (pb.path[1] - 1) AS point_b_index,
                ROUND(ST_Distance(pa.geom::geography, pb.geom::geography))::int AS walk_distance
            FROM lines a
            JOIN lines b
                ON  a.id < b.id
                AND ST_DWithin(a.geom::geography, b.geom::geography, :radius)
            CROSS JOIN LATERAL
                ST_DumpPoints(ST_GeometryN(a.geom, 1)) AS pa
            CROSS JOIN LATERAL (
                SELECT pb_inner.geom, pb_inner.path
                FROM   ST_DumpPoints(ST_GeometryN(b.geom, 1)) AS pb_inner
                WHERE  ST_DWithin(pa.geom::geography, pb_inner.geom::geography, :radius)
                ORDER BY pa.geom <-> pb_inner.geom
                LIMIT 1
            ) AS pb
        ', ['radius' => self::TRANSFER_RADIUS]);

        $this->info('Raw forward rows from PostGIS: '.count($forwardRows));

        // ── 2. Deduplicar forwards en PHP ────────────────────────────────
        $forwardTransfers = $this->deduplicateNearby(
            array_map(fn ($r) => (array) $r, $forwardRows)
        );
        $this->info('After dedup: '.count($forwardTransfers).' forward transfers');

        // ── 3. Generar inversos desde forwards deduplicados ──────────────
        $inverseTransfers = array_map(fn ($t) => [
            'line_a_id' => $t['line_b_id'],
            'line_b_id' => $t['line_a_id'],
            'point_a_lng' => $t['point_b_lng'],
            'point_a_lat' => $t['point_b_lat'],
            'point_a_index' => $t['point_b_index'],
            'point_b_lng' => $t['point_a_lng'],
            'point_b_lat' => $t['point_a_lat'],
            'point_b_index' => $t['point_a_index'],
            'walk_distance' => $t['walk_distance'],
            'created_at' => $t['created_at'],
        ], $forwardTransfers);

        $inverseTransfers = $this->deduplicateNearby($inverseTransfers);

        $allTransfers = array_merge($forwardTransfers, $inverseTransfers);
        $this->info('Total transfers to save: '.count($allTransfers));

        // ── 4. Swap atómico ──────────────────────────────────────────────
        $this->saveBatched($allTransfers);

        $duration = number_format((microtime(true) - $startTime), 1);

        $this->line(json_encode([
            'success' => true,
            'transfersCreated' => count($allTransfers),
            'duration' => "{$duration}s",
        ]));

        return self::SUCCESS;
    }

    private function deduplicateNearby(array $transfers): array
    {
        $now = now();
        $groups = [];

        foreach ($transfers as $t) {
            $key = $t['line_a_id'].'->'.$t['line_b_id'];
            $groups[$key][] = $t;
        }

        $cellSize = self::MIN_SEPARATION / 111320.0;
        $result = [];

        foreach ($groups as $group) {
            $occupiedCells = [];
            $dedupedGroup = [];

            foreach ($group as $candidate) {
                $cellA = floor($candidate['point_a_lat'] / $cellSize)
                       .','
                       .floor($candidate['point_a_lng'] / $cellSize);
                $cellB = floor($candidate['point_b_lat'] / $cellSize)
                       .','
                       .floor($candidate['point_b_lng'] / $cellSize);
                $cellKey = $cellA.'|'.$cellB;

                if (isset($occupiedCells[$cellKey])) {
                    continue;
                }

                $occupiedCells[$cellKey] = true;
                $candidate['created_at'] = $now;
                $dedupedGroup[] = $candidate;
            }

            foreach ($dedupedGroup as $t) {
                $result[] = $t;
            }
        }

        return $result;
    }

    private function saveBatched(array $transfers): void
    {
        DB::statement('TRUNCATE TABLE line_transfers CASCADE');

        $total = count($transfers);

        for ($i = 0; $i < $total; $i += self::BATCH_SIZE) {
            $batch = array_slice($transfers, $i, self::BATCH_SIZE);
            DB::table('line_transfers')->insert($batch);
            $this->output->write("\rSaved ".min($i + self::BATCH_SIZE, $total)."/{$total}");
        }

        $this->newLine();
    }

    private static function haversine(array $p1, array $p2): float
    {
        $R = 6371000;
        $lat1 = deg2rad($p1[1]);
        $lat2 = deg2rad($p2[1]);
        $dLat = deg2rad($p2[1] - $p1[1]);
        $dLng = deg2rad($p2[0] - $p1[0]);
        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
