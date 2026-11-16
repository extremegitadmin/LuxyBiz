<?php

namespace App\Console\Commands;

use App\Models\Permission;
use Illuminate\Console\Command;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Make the permissions table match config/permissions.php';

    public function handle(): int
    {
        Permission::syncFromConfig();
        $this->info(Permission::count() . ' permissions in the database.');

        return self::SUCCESS;
    }
}
