<?php

namespace App\Models;

use App\Traits\SerializesDatesAsLocal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeasonPassType extends Model
{
    use HasFactory, SerializesDatesAsLocal;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'season_id',
        'pass_type_name',
        'regular_early_price',
        'regular_regular_price',
        'group_early_price',
        'group_regular_price',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'regular_early_price' => 'decimal:2',
            'regular_regular_price' => 'decimal:2',
            'group_early_price' => 'decimal:2',
            'group_regular_price' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the season that owns this pass type.
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * Get the pass requests for this pass type.
     */
    public function passRequests(): HasMany
    {
        return $this->hasMany(PassRequest::class);
    }
}