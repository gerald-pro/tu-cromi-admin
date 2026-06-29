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

        DB::statement('TRUNCATE TABLE line_transfers CASCADE');

        $lines = Line::query()->whereNotNull('geo_json')->get(['id', 'geo_json']);

        $lineMap = [];
        foreach ($lines as $line) {
            $geoJson = $line->geo_json;
            $coords = $geoJson['coordinates'][0] ?? null;
            if ($coords) {
                $lineMap[$line->id] = $coords;
            }
        }

        $this->info('Loaded '.count($lineMap).' lines with geometry');

        $candidatePairs = $this->findCandidatePairs();
        $this->info('Found '.count($candidatePairs).' candidate line pairs');

        $allTransfers = [];

        foreach ($candidatePairs as $i => $pair) {
            $lineAData = $lineMap[$pair->line_a_id] ?? null;
            $lineBData = $lineMap[$pair->line_b_id] ?? null;

            if ($lineAData && $lineBData) {
                $pairTransfers = $this->computeTransfersForPair(
                    lineAId: $pair->line_a_id,
                    lineAPolyline: $lineAData,
                    lineBId: $pair->line_b_id,
                    lineBPolyline: $lineBData,
                );
                array_push($allTransfers, ...$pairTransfers);
            }

            if (($i + 1) % 100 === 0 || $i === count($candidatePairs) - 1) {
                $this->output->write("\rProcessing: ".($i + 1).'/'.count($candidatePairs).' pairs');
            }
        }

        $this->newLine();
        $this->info('Computed '.count($allTransfers).' transfer points');

        $this->saveBatched($allTransfers);

        $duration = number_format((microtime(true) - $startTime), 1);

        $this->line(json_encode([
            'success' => true,
            'transfersCreated' => count($allTransfers),
            'duration' => "{$duration}s",
        ]));

        return self::SUCCESS;
    }

    private function findCandidatePairs(): array
    {
        return DB::select(
            'SELECT DISTINCT a.id AS line_a_id, b.id AS line_b_id
             FROM lines a
             JOIN lines b ON a.id <> b.id
             WHERE ST_DWithin(a.geom::geography, b.geom::geography, :radius)
               AND a.id < b.id',
            ['radius' => self::TRANSFER_RADIUS],
        );
    }

    private function computeTransfersForPair(
        int $lineAId,
        array $lineAPolyline,
        int $lineBId,
        array $lineBPolyline,
    ): array {
        $forwardResults = [];

        foreach ($lineAPolyline as $i => $pA) {
            $localMin = INF;
            $localBest = null;

            foreach ($lineBPolyline as $j => $pB) {
                $dist = self::haversine($pA, $pB);

                if ($dist <= self::TRANSFER_RADIUS && $dist < $localMin) {
                    $localMin = $dist;
                    $localBest = ['j' => $j, 'dist' => $dist, 'pB' => $pB];
                }
            }

            if ($localBest) {
                $forwardResults[] = [
                    'line_a_id' => $lineAId,
                    'line_b_id' => $lineBId,
                    'point_a_lng' => $pA[0],
                    'point_a_lat' => $pA[1],
                    'point_a_index' => $i,
                    'point_b_lng' => $localBest['pB'][0],
                    'point_b_lat' => $localBest['pB'][1],
                    'point_b_index' => $localBest['j'],
                    'walk_distance' => (int) round($localBest['dist']),
                    'created_at' => now(),
                ];
            }
        }

        $dedupedForward = $this->deduplicateNearby($forwardResults);

        $inverseResults = array_map(fn ($t) => [
            'line_a_id' => $t['line_b_id'],
            'line_b_id' => $t['line_a_id'],
            'point_a_lng' => $t['point_b_lng'],
            'point_a_lat' => $t['point_b_lat'],
            'point_a_index' => $t['point_b_index'],
            'point_b_lng' => $t['point_a_lng'],
            'point_b_lat' => $t['point_a_lat'],
            'point_b_index' => $t['point_a_index'],
            'walk_distance' => $t['walk_distance'],
            'created_at' => now(),
        ], $dedupedForward);

        $dedupedInverse = $this->deduplicateNearby($inverseResults);

        return array_merge($dedupedForward, $dedupedInverse);
    }

    private function deduplicateNearby(array $transfers): array
    {
        $groups = [];

        foreach ($transfers as $t) {
            $key = $t['line_a_id'].'->'.$t['line_b_id'];
            $groups[$key][] = $t;
        }

        $result = [];

        foreach ($groups as $group) {
            $dedupedGroup = [];

            foreach ($group as $candidate) {
                $tooClose = false;

                foreach ($dedupedGroup as $existing) {
                    $distA = self::haversine(
                        [$existing['point_a_lng'], $existing['point_a_lat']],
                        [$candidate['point_a_lng'], $candidate['point_a_lat']],
                    );
                    $distB = self::haversine(
                        [$existing['point_b_lng'], $existing['point_b_lat']],
                        [$candidate['point_b_lng'], $candidate['point_b_lat']],
                    );

                    if ($distA < self::MIN_SEPARATION && $distB < self::MIN_SEPARATION) {
                        $tooClose = true;
                        break;
                    }
                }

                if (! $tooClose) {
                    $dedupedGroup[] = $candidate;
                }
            }

            array_push($result, ...$dedupedGroup);
        }

        return $result;
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

    private function saveBatched(array $transfers): void
    {
        $total = count($transfers);

        for ($i = 0; $i < $total; $i += self::BATCH_SIZE) {
            $batch = array_slice($transfers, $i, self::BATCH_SIZE);
            DB::table('line_transfers')->insert($batch);
            $this->output->write("\rSaved ".min($i + self::BATCH_SIZE, $total)."/{$total}");
        }

        $this->newLine();
    }
}
