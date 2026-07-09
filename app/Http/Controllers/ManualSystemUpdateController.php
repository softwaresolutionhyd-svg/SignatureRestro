<?php

namespace App\Http\Controllers;

use App\Services\ManualSystemUpdateInstaller;
use Illuminate\Http\Request;

class ManualSystemUpdateController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()?->isPlatformSuperAdmin(), 403);

        return view('platform.manual-update.index');
    }

    public function store(Request $request, ManualSystemUpdateInstaller $installer)
    {
        abort_unless(auth()->user()?->isPlatformSuperAdmin(), 403);

        $request->validate([
            'package' => ['required', 'file', 'mimes:zip', 'max:102400'],
        ], [
            'package.required' => 'ZIP file select karein.',
            'package.mimes' => 'Sirf .zip file.',
        ]);

        $result = $installer->installFromZip($request->file('package'));

        if (! $result['ok']) {
            return back()->withErrors(['package' => implode(' ', $result['messages'])]);
        }

        return back()->with('status_lines', $result['messages']);
    }
}
