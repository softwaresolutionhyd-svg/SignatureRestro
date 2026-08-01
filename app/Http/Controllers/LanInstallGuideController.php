<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Step-by-step guide so Android/iOS can trust LAN HTTPS and install the PWA.
 */
class LanInstallGuideController extends Controller
{
    public function __invoke(Request $request): View
    {
        $host = $request->getHost();
        $httpsUrl = 'https://'.$host.'/';
        $caUrl = url('/lan-ca.crt');
        $loginUrl = url('/login');

        return view('lan-install', compact('host', 'httpsUrl', 'caUrl', 'loginUrl'));
    }
}
