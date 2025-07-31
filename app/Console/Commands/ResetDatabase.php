<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ResetDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:reset {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset database and recreate from models';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (!$this->option('force') && !$this->confirm('🚨 This will DELETE all data and migrations. Continue?')) {
            $this->info('Operation cancelled');
            return;
        }

        $this->info('🔄 Resetting database...');

        // 1. Resetear BD
        $this->info('1️⃣ Resetting database...');
        Artisan::call('migrate:fresh');

        // 2. Eliminar migraciones
        $this->info('2️⃣ Removing migration files...');
        $migrationPath = database_path('migrations');
        if (File::exists($migrationPath)) {
            File::cleanDirectory($migrationPath);
        }

        // 3. Crear migraciones desde modelos
        $this->info('3️⃣ Creating migrations from models...');
        Artisan::call('auto:migrate', ['--sync' => true, '--force' => true]);

        // 4. Crear roles básicos
        $this->info('4️⃣ Creating basic roles...');
        $this->createBasicRoles();

        $this->info('✅ Database reset completed successfully!');
    }

    private function createBasicRoles()
    {
        if (Schema::hasTable('roles')) {
            $roles = [
                ['name' => 'Admin', 'description' => 'Administrador del sistema'],
                ['name' => 'User', 'description' => 'Usuario estándar'],
                ['name' => 'Manager', 'description' => 'Gerente'],
            ];

            foreach ($roles as $role) {
                \App\Models\Role::firstOrCreate(['name' => $role['name']], $role);
            }

            $this->info('✅ Basic roles created');
        }
    }
}
