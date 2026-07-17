<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\LanServerUrl;
use Illuminate\Http\JsonResponse;

class ServerConfigController extends Controller
{
    /** Public LAN config for mobile / tablet apps (no auth). */
    public function show(): JsonResponse
    {
        return response()->json(LanServerUrl::apiPayload());
    }
}
