<?php

namespace App\Models;

use App\Traits\SerializesDatesAsLocal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLogin extends Model
{
    use HasFactory, SerializesDatesAsLocal;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'email',
        'ip_address',
        'user_agent',
        'successful',
        'failure_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
        ];
    }

    /**
     * Get the user associated with this login attempt.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log a successful login attempt.
     */
    public static function logSuccess(User $user, ?string $ipAddress = null, ?string $userAgent = null): static
    {
        return static::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'successful' => true,
        ]);
    }

    /**
     * Log a failed login attempt.
     */
    public static function logFailure(string $email, string $reason, ?string $ipAddress = null, ?string $userAgent = null): static
    {
        return static::create([
            'user_id' => null,
            'email' => $email,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'successful' => false,
            'failure_reason' => $reason,
        ]);
    }
}
