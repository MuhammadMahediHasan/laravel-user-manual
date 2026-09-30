<?php

namespace MuhammadMahediHasan\UserManual\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use MuhammadMahediHasan\UserManual\Support\CurrentManual;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentManual
{
    public function __construct(private readonly CurrentManual $currentManual) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $manual): Response
    {
        /** @var Response $response */
        $response = $this->currentManual->using($manual, fn (): Response => $next($request));

        return $response;
    }
}
