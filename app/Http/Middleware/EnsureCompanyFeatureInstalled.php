<?php

namespace App\Http\Middleware;

use App\Support\CompanyFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyFeatureInstalled
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $cid = current_company_id();
        if ($cid === null || ! CompanyFeatures::isInstalled($cid, $feature)) {
            abort(404);
        }

        return $next($request);
    }
}
