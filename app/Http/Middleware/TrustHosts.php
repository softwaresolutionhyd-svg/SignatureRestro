<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts(): array
    {
        return [
            $this->allSubdomainsOfApplicationUrl(),
            'signature.test',
            'www.signature.test',
            'localhost',
            '127.0.0.1',
            '192.168.*.*',
            '10.*.*.*',
            '172.*.*.*',
            '*.test',
        ];
    }
}
