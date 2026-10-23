<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PassRequests\ApiAccess;
use BWH\Auth\OAuth\Introspection\OAuthIntrospectionException;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class LinkApiIdentity extends Command
{
    protected $signature = 'api:link-identity {user : Existing local user ID} {subject : Verified opaque OAuth subject}';

    protected $description = 'Bind an existing local user to a verified subject from the configured API OAuth issuer';

    public function handle(): int
    {
        try {
            $configuration = ApiAccess::configuration();
        } catch (OAuthIntrospectionException) {
            $this->error('Configure the API OAuth issuer and resource before linking an identity.');

            return self::FAILURE;
        }

        $userId = $this->argument('user');
        $subject = $this->argument('subject');
        if (! is_string($userId) || ! ctype_digit($userId)
            || ! is_string($subject) || trim($subject) === '' || strlen($subject) > 255) {
            $this->error('Provide a local numeric user ID and a nonempty opaque subject of at most 255 bytes.');

            return self::FAILURE;
        }

        try {
            $result = DB::transaction(function () use ($userId, $subject, $configuration): int {
                $user = User::query()->lockForUpdate()->find($userId);
                if ($user === null) {
                    $this->error('The local user does not exist.');

                    return self::FAILURE;
                }

                if ($user->oauth_provider !== null || $user->oauth_subject !== null) {
                    if ($user->oauth_provider === $configuration['issuer'] && $user->oauth_subject === $subject) {
                        $this->info('The local user already has this identity binding.');

                        return self::SUCCESS;
                    }
                    $this->error('This local user already has a different identity binding; review it before replacing it.');

                    return self::FAILURE;
                }

                if (User::query()->where('oauth_provider', $configuration['issuer'])->where('oauth_subject', $subject)->exists()) {
                    $this->error('This identity is already linked to another local user.');

                    return self::FAILURE;
                }

                $user->forceFill(['oauth_provider' => $configuration['issuer'], 'oauth_subject' => $subject])->save();
                $this->info('The identity was linked to the existing local user.');

                return self::SUCCESS;
            });
        } catch (UniqueConstraintViolationException) {
            $this->error('This identity is already linked to another local user.');

            return self::FAILURE;
        }

        return $result;
    }
}
