<?php

namespace App\Models;

use App\Traits\SerializesDatesAsLocal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Binds a local user to a verified subject from the API OAuth issuer. Deliberately separate from
 * users.oauth_provider/oauth_subject, which auth-laravel's provider sign-in uses.
 */
class ApiIdentityLink extends Model
{
    use SerializesDatesAsLocal;

    protected $fillable = ['user_id', 'provider', 'subject'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
