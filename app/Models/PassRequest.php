<?php

namespace App\Models;

use App\Traits\SerializesDatesAsLocal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PassRequest extends Model
{
    use HasFactory, SerializesDatesAsLocal;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'season_id',
        'season_pass_type_id',
        'passholder_email',
        'pass_type',
        'passholder_first_name',
        'passholder_last_name',
        'passholder_birth_date',
        'is_renewal',
        'renewal_pass_id',
        'renewal_order_number',
        'promo_code',
        'redemption_date',
        'assign_code_date',
        'email_notify_time',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'passholder_birth_date' => 'date',
            'is_renewal' => 'boolean',
            'redemption_date' => 'date',
            'assign_code_date' => 'date',
            'email_notify_time' => 'datetime',
        ];
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::ulid()->toBase32();
            }
        });
    }

    /**
     * Get the user that owns the pass request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the season for this pass request.
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * Get the pass type for this request.
     */
    public function seasonPassType(): BelongsTo
    {
        return $this->belongsTo(SeasonPassType::class);
    }

    /**
     * Get the display name for the pass type.
     */
    public function getPassTypeNameAttribute(): string
    {
        return $this->seasonPassType?->pass_type_name ?? $this->pass_type ?? 'Unknown';
    }
}
