<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class UpdateLastLoginDate
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        // Update last login date if the user has that column
        // For now, this is a no-op since our User model doesn't have last_login_at
    }
}
