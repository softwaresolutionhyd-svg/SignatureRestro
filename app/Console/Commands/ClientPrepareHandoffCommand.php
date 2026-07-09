<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Clears Laravel caches before zipping or copying the project for a client.
 * Does not touch the database — use migrate:fresh --seed on the client side for a clean DB.
 */
class ClientPrepareHandoffCommand extends Command
{
    protected $signature = 'client:prepare-handoff';

    protected $description = 'Clear config/route/view/cache (optimize:clear) for a clean client delivery build';

    public function handle(): int
    {
        Artisan::call('optimize:clear');
        $this->output->write(Artisan::output());

        $this->newLine();
        $this->info('Client handoff checklist:');
        $this->line('  1. .env copy karein (.env.example se), phir php artisan key:generate');
        $this->line('  2. Production: APP_DEBUG=false, APP_URL sahi domain');
        $this->line('  3. Naya DB: php artisan migrate --force  (ya migrate:fresh --force --seed pehli dafa)');
        $this->line('  4. Default seed ab dummy/demo data NAHI lagata; demo ke liye: php artisan db:seed --class=DummyDataSeeder');
        $this->line('  5. Assets: npm ci && npm run build (agar Vite use ho raha ho)');
        $this->line('  6. Purana POS/test data hataane ke liye: php artisan pos:purge-and-sync-purchases --force');
        $this->line('  7. Platform super admin (sari companies): php artisan platform:create-super-admin you@email.com YourPass123');

        return self::SUCCESS;
    }
}
