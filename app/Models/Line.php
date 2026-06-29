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
 * @property int $id
 * @property string $code
 * @property string|null $name
 * @property string|null $color
 * @property array|null $geo_json
 * @property LineSense $sense
 * @property int|null $parent_line_id
 * @property string|null $syndicate
 * @property int|null $objectid
 * @property float|null $average_rating
 * @property int $total_reviews
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
