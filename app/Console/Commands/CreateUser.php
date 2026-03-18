<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create {email} {--first-name=} {--last-name=} {--name=} {--password=} {--is-admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new user with confirmed email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $isAdmin = $this->option('is-admin');

        // Support --name for backward compatibility (split at first space)
        $firstName = $this->option('first-name');
        $lastName = $this->option('last-name');
        if (!$firstName && $this->option('name')) {
            $parts = explode(' ', $this->option('name'), 2);
            $firstName = $parts[0];
            $lastName = $parts[1] ?? null;
        }
        if (!$firstName) {
            $firstName = $this->ask('First Name');
        }
        if (!$lastName) {
            $lastName = $this->ask('Last Name');
        }

        $password = $this->option('password');
        if (empty($password)) {
            $password = $this->secret('Password (leave blank to generate)');
        }
        if (empty($password)) {
            $password = Str::random(12);
            $generated = true;
        } else {
            $generated = false;
        }

        $validator = Validator::make([
            'email' => $email,
        ], [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return 1;
        }

        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'is_admin' => $isAdmin ? true : false,
        ]);

        $adminText = $user->is_admin ? ' (Admin)' : '';
        $this->info("User {$user->first_name} {$user->last_name} ({$user->email}){$adminText} created successfully.");
        if (!empty($generated)) {
            $this->info("Generated password: {$password}");
        }
        return 0;
    }
}