<?php

namespace App\Console\Commands;

use App\Services\PublicStorageMirror;
use Illuminate\Console\Command;

class MirrorPublicStorageCommand extends Command
{
    protected $signature = 'storage:mirror-public {--subdir= : Only mirror a subfolder, e.g. products}';

    protected $description = 'Copy storage/app/public files to public/storage (fix broken product/logo images)';

    public function handle(): int
    {
        $subdir = (string) ($this->option('subdir') ?? '');
        $count = PublicStorageMirror::publishAll($subdir);

        $this->info("Mirrored {$count} file(s) to public/storage.");

        return self::SUCCESS;
    }
}
