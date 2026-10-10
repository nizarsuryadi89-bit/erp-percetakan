<?php

namespace App\Support;

use Inertia\Inertia;
use Laravel\Fortify\Http\Responses\LogoutResponse;
use Symfony\Component\HttpFoundation\Response;

class ClearHistoryLogoutResponse extends LogoutResponse
{
    public function toResponse(mixed $request): Response
    {
        Inertia::clearHistory();
        $response = parent::toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
