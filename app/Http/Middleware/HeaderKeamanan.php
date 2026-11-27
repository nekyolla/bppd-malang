<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk panel admin, berkas pribadi, dan halaman publik: halaman tidak boleh
 * dibingkai situs lain (clickjacking), tipe berkas tidak ditebak browser, dan URL tidak bocor
 * lewat Referer. Mode `tanpa-bingkai` dipakai di /daftar karena integrasi dengan website balai
 * belum diputuskan.
 */
class HeaderKeamanan
{
    public function handle(Request $request, Closure $next, string $mode = 'penuh'): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];

        if ($mode !== 'tanpa-bingkai') {
            $headers['X-Frame-Options'] = 'SAMEORIGIN';
            $headers['Content-Security-Policy'] = "frame-ancestors 'self'";
        }

        $response->headers->add($headers);

        return $response;
    }
}
