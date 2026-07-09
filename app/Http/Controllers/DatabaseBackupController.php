<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupExporter;
use Illuminate\Http\Request;

class DatabaseBackupController extends Controller
{
    public function download(Request $request, DatabaseBackupExporter $exporter)
    {
        $user = $request->user();
        abort_unless(in_array($user->role ?? '', ['company_admin', 'super_admin'], true), 403);

        $full = $user->isPlatformSuperAdmin();

        try {
            set_time_limit(600);
            @ini_set('memory_limit', '512M');

            $result = $exporter->createBackup($full);

            return response()
                ->download($result['path'], $result['filename'])
                ->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->to(route('settings.index').'#tab-system')
                ->withErrors(['backup' => 'Backup nahi ban saka: '.$e->getMessage()]);
        }
    }
}
