<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexGuestRequest;
use App\Http\Requests\StoreGuestRequest;
use App\Http\Requests\UpdateGuestRequest;
use App\Http\Resources\GuestResource;
use App\Managers\Hotel\GuestManager;
use App\Models\Guest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    public function __construct(private readonly GuestManager $guests) {}

    public function index(IndexGuestRequest $request): JsonResponse
    {
        $query = $this->guests->list($request->user(), $request->validated());

        return $this->getJsonResponse($this->fetchResults($query), GuestResource::class);
    }

    public function show(Request $request, Guest $guest): JsonResponse
    {
        $guest = $this->guests->find($request->user(), $guest);

        return $this->getJsonResponse($guest, GuestResource::class, '', true);
    }

    public function store(StoreGuestRequest $request): JsonResponse
    {
        $guest = $this->guests->create($request->user(), $request->validated());

        return $this->getJsonResponse($guest, GuestResource::class, 'messages.guests.created', true);
    }

    public function update(UpdateGuestRequest $request, Guest $guest): JsonResponse
    {
        $guest = $this->guests->update($request->user(), $guest, $request->validated());

        return $this->getJsonResponse($guest, GuestResource::class, 'messages.guests.updated', true);
    }

    public function destroy(Request $request, Guest $guest): JsonResponse
    {
        $this->guests->delete($request->user(), $guest);

        return $this->responseToJson('messages.guests.deleted');
    }
}
