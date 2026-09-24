<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class AssignSystemAdmin extends Command
{
    protected $signature = 'system-admin:assign {email : Email of an existing account}';

    protected $description = 'Grant System Admin access to an existing active, unlinked account';

    public function handle(): int
    {
        $account = User::where('email', $this->argument('email'))->first();
        if (! $account || ! $account->is_active) {
            $this->error('An active account with that email must already exist.');

            return self::FAILURE;
        }
        if ($account->student_id || $account->assignedSubjects()->exists()) {
            $this->error('Remove the student-account link or reassign teaching subjects before changing this role.');

            return self::FAILURE;
        }
        $account->update(['role' => 'admin']);
        $account->tokens()->delete();
        $this->info('System Admin assigned. Sign in and open /admin/dashboard.');

        return self::SUCCESS;
    }
}
