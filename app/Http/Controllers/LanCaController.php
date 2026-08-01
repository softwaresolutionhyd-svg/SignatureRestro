<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the local mkcert root CA so LAN phones/tablets can trust
 * https://SERVER-IP for PWA install. Never exposes the CA private key.
 */
class LanCaController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse|Response
    {
        if (! $this->isPrivateClient($request)) {
            abort(404);
        }

        $path = $this->resolveCaPath();
        if ($path === null) {
            return response(
                "LAN CA missing.\nPC par Admin se scripts\\enable-signature-lan-https.bat chalao.\n",
                404,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        return response()->file($path, [
            'Content-Type' => 'application/x-x509-ca-cert',
            'Content-Disposition' => 'attachment; filename="signature-lan-ca.crt"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function isPrivateClient(Request $request): bool
    {
        $ip = (string) $request->ip();
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        // Reject public IPv4; allow RFC1918 / link-local.
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    private function resolveCaPath(): ?string
    {
        $candidates = [
            storage_path('app/lan/lan-ca.crt'),
            storage_path('app/lan/rootCA.pem'),
        ];

        $localAppData = getenv('LOCALAPPDATA') ?: (getenv('USERPROFILE') ? rtrim((string) getenv('USERPROFILE'), '\\/').DIRECTORY_SEPARATOR.'AppData'.DIRECTORY_SEPARATOR.'Local' : null);
        if (is_string($localAppData) && $localAppData !== '') {
            $candidates[] = rtrim($localAppData, '\\/').DIRECTORY_SEPARATOR.'mkcert'.DIRECTORY_SEPARATOR.'rootCA.pem';
        }

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_file($path) && is_readable($path)) {
                if (str_contains(strtolower(basename($path)), 'key')) {
                    continue;
                }

                return $path;
            }
        }

        return null;
    }
}
