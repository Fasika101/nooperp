<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStorefrontToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('storefront.api_token');

        if ($expected === '') {
            return response()->json([
                'message' => 'Storefront API is not configured (STOREFRONT_API_TOKEN).',
            ], 503);
        }

        $provided = (string) $request->bearerToken();

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
