<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:make-admin {email : The email address of an existing user} {--revoke : Remove admin access instead}')]
#[Description('Grant or revoke access to the operator report')]
class MakeAdmin extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->components->error('No user has that email address.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => ! $this->option('revoke')])->save();

        $this->components->info($user->is_admin
            ? "{$user->email} can now see the operator report."
            : "{$user->email} can no longer see the operator report.");

        return self::SUCCESS;
    }
}
