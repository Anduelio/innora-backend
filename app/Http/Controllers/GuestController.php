<?php

namespace App\Http\Controllers;

use App\Http\Resources\GuestResource;
use App\Managers\Hotel\GuestManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    public function __construct(private readonly GuestManager $guests) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->guests->list($request->user(), $request->validate([
            'no_pagination' => ['nullable'],
        ]));

        return $this->getJsonResponse($this->fetchResults($query), GuestResource::class);
    }
}
