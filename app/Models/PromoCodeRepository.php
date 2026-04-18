<?php

namespace App\Models;

use App\Traits\SerializesDatesAsLocal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCodeRepository extends Model
{
    use SerializesDatesAsLocal;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'promo_code_repository';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'promo_code';

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'promo_code',
        'season_id',
        'start_date',
        'expiration_date',
        'country',
        'is_suspended',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'expiration_date' => 'date',
            'is_suspended' => 'boolean',
        ];
    }

    /**
     * Get the season for this promo code.
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * Get the pass requests that use this promo code.
     */
    public function passRequests(): HasMany
    {
        return $this->hasMany(PassRequest::class, 'promo_code', 'promo_code');
    }
}
