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
     * Get a display name for this season.
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->pass_name} {$this->pass_year}";
    }
}
