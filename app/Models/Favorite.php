<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorite extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'line_id',
        'name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }
}
