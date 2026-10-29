<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk panel admin dan berkas pribadi: halaman tidak boleh dibingkai situs
 * lain (clickjacking), tipe berkas tidak ditebak browser, dan URL tidak bocor lewat Referer.
 * Form /daftar belum memakainya karena integrasi dengan website balai belum diputuskan.
 */
class HeaderKeamanan
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->add([
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "frame-ancestors 'self'",
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]);

        return $response;
    }
}
