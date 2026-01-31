<?php

namespace App\Models;

use App\Traits\SerializesDatesAsLocal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InviteCode extends Model
{
    use HasFactory, SoftDeletes, SerializesDatesAsLocal;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'invite_code',
        'max_number_of_uses',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_number_of_uses' => 'integer',
        ];
    }

    /**
     * Get the users that used this invite code.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the current usage count of this invite code.
     */
    public function getUsageCountAttribute(): int
    {
        return $this->users()->count();
    }

    /**
     * Check if this invite code can still be used.
     */
    public function canBeUsed(): bool
    {
        return $this->usage_count < $this->max_number_of_uses;
    }
}
