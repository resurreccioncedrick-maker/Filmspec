<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Creates (or updates) one login per role, all addressed to the same real inbox via Gmail's
 * "+tag" sub-addressing — jolomallare148+admin@gmail.com etc. all deliver to
 * jolomallare148@gmail.com, so one person can receive the OTP for any role without juggling
 * separate inboxes. Idempotent: safe to re-run, upserts by email. --email lets this run for
 * more than one person's inbox without editing the command each time.
 */
class SeedTestAccounts extends Command
{
    protected $signature = 'test-accounts:seed {--email=resurreccioncedrick@gmail.com : Base inbox all +tag logins deliver to}';

    protected $description = 'Create/update one test login per role, all +tag addresses to one real inbox';

    private const PASSWORD = 'Password123!';

    public function handle(): int
    {
        $baseEmail = $this->option('email');
        if (! str_contains($baseEmail, '@')) {
            $this->error('--email must be a full address, e.g. name@gmail.com');

            return self::FAILURE;
        }
        [$local, $domain] = explode('@', $baseEmail, 2);

        $roles = [
            'super_admin' => ['tag' => 'superadmin', 'name' => 'Super Admin'],
            'admin' => ['tag' => 'admin', 'name' => 'Admin'],
            'operations_manager' => ['tag' => 'ops', 'name' => 'Operations Manager'],
            'traffic' => ['tag' => 'traffic', 'name' => 'Traffic'],
            'accounting' => ['tag' => 'accounting', 'name' => 'Accounting'],
            'crew' => ['tag' => 'crew', 'name' => 'Crew'],
            'client' => ['tag' => 'client', 'name' => 'Client'],
        ];

        $hash = Hash::make(self::PASSWORD);
        $rows = [];

        foreach ($roles as $roleName => $info) {
            $roleId = DB::table('roles')->where('role_name', $roleName)->value('role_id');
            if (! $roleId) {
                $this->warn("Role '$roleName' not found — skipping.");
                continue;
            }

            $email = $local . '+' . $info['tag'] . '@' . $domain;
            $existing = DB::table('users')->where('email', $email)->first();

            $userFields = [
                'role_id' => $roleId,
                'first_name' => 'Test',
                'last_name' => $info['name'],
                'password_hash' => $hash,
                'is_active' => 1,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('users')->where('user_id', $existing->user_id)->update($userFields);
                $userId = $existing->user_id;
            } else {
                $userId = DB::table('users')->insertGetId($userFields + [
                    'email' => $email, 'created_at' => now(),
                ]);
            }

            if ($roleName === 'crew') {
                $crewExists = DB::table('crew_members')->where('user_id', $userId)->exists();
                if (! $crewExists) {
                    $posId = DB::table('crew_positions')->value('position_id');
                    DB::table('crew_members')->insert([
                        'user_id' => $userId, 'first_name' => 'Test', 'last_name' => $info['name'],
                        'email' => $email, 'primary_position_id' => $posId, 'employment_type' => 'freelance',
                        'status' => 'active', 'total_shoots' => 0, 'date_joined' => now()->toDateString(),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            if ($roleName === 'client') {
                $clientExists = DB::table('clients')->where('user_id', $userId)->exists();
                if (! $clientExists) {
                    DB::table('clients')->insert([
                        'user_id' => $userId, 'contact_person' => 'Test ' . $info['name'], 'email' => $email,
                        'client_type' => 'first_time', 'status' => 'approved', 'is_active' => 1,
                        'entity_type' => 'individual', 'payment_terms' => '50_downpayment',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            $rows[] = [$roleName, $email];
        }

        $this->newLine();
        $this->table(['Role', 'Login Email'], $rows);
        $this->info('Password for all: ' . self::PASSWORD);
        $this->info('All verification codes land in: ' . $baseEmail);

        return self::SUCCESS;
    }
}
