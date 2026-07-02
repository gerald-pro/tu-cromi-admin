<?php

namespace App\Models;

use App\Enums\LineSense;
use Database\Factories\LineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Represents a single direction of a transport line.
 *
 * Each real-world line (e.g. "1") generates two records:
 * OUTBOUND (ida) and RETURN (vuelta), each with its own geometry (MultiLineString).
 * The `code` field groups the two opposite directions.
 * When both directions exist, they link via parent_line_id (bidirectional).
 * Lines with only one sense (e.g. 72, 73) have no counterpart.
 *
 * @property int $id Auto-increment primary key
 * @property string $code Public identifier grouping OUTBOUND and RETURN (e.g. "1", "16 azul", "104 C"). Not unique — two rows share the same code
 * @property string|null $name Display name (e.g. "Línea 1")
 * @property string|null $color Hex color for UI
 * @property array|null $geo_json Raw GeoJSON MultiLineString coordinates [lng, lat]
 * @property LineSense $sense Direction of travel: OUTBOUND (ida) or RETURN (vuelta)
 * @property int|null $parent_line_id Opposite-direction sibling ID (self-referential FK). Null when no counterpart exists
 * @property string|null $syndicate Operating company
 * @property int|null $objectid External system identifier
 * @property float|null $average_rating Average user rating (1.00–5.00)
 * @property int $total_reviews Number of user reviews
 * @property \Geometry|null $geom PostGIS geometry(MultiLineString, 4326) with GIST index for spatial queries (ST_DWithin, etc.)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'code',
    'name',
    'color',
    'geo_json',
    'sense',
    'parent_line_id',
    'syndicate',
    'objectid',
    'average_rating',
    'total_reviews',
])]
class Line extends Model
{
    /** @use HasFactory<LineFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'geo_json' => 'array',
            'sense' => LineSense::class,
            'average_rating' => 'decimal:2',
        ];
    }

    public function parentLine(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_line_id');
    }

    public function childLines(): HasMany
    {
        return $this->hasMany(self::class, 'parent_line_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
