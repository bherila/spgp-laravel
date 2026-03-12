<?php

namespace App\Models;

use App\Traits\SerializesDatesAsLocal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Season extends Model
{
    use HasFactory, SoftDeletes, SerializesDatesAsLocal;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pass_name',
        'pass_year',
        'start_date',
        'early_spring_deadline',
        'final_deadline',
        'spreadsheet_url',
        'allow_renewals',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pass_year' => 'integer',
            'start_date' => 'datetime',
            'early_spring_deadline' => 'datetime',
            'final_deadline' => 'datetime',
            'allow_renewals' => 'boolean',
        ];
    }

    /**
     * Get the pass requests for this season.
     */
    public function passRequests(): HasMany
    {
        return $this->hasMany(PassRequest::class);
    }

    /**
     * Get the pass types available for this season.
     */
    public function passTypes(): HasMany
    {
        return $this->hasMany(SeasonPassType::class)->orderBy('sort_order');
    }

    /**
     * Get a display name for this season.
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->pass_name} {$this->pass_year}";
    }

    /**
     * Get the questions associated with this season.
     */
    public function questions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Question::class);
    }

    /**
     * Get the invite codes for this season.
     */
    public function inviteCodes(): HasMany
    {
        return $this->hasMany(InviteCode::class);
    }
}
