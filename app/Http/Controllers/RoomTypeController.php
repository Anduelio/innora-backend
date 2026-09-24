<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoomTypeRequest;
use App\Http\Requests\UpdateRoomTypeRequest;
use App\Http\Resources\RoomTypeResource;
use App\Managers\Hotel\RoomTypeManager;
use App\Models\RoomType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomTypeController extends Controller
{
    public function __construct(private readonly RoomTypeManager $types) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->types->list($request->user(), $request->validate([
            'no_pagination' => ['nullable'],
        ]));

        return $this->getJsonResponse($this->fetchResults($query), RoomTypeResource::class);
    }

    public function store(StoreRoomTypeRequest $request): JsonResponse
    {
        $type = $this->types->create($request->user(), $request->validated());

        return $this->getJsonResponse($type, RoomTypeResource::class, 'messages.success.room_type_created', true);
    }

    public function show(Request $request, RoomType $roomType): JsonResponse
    {
        if ($roomType->property_id !== $request->user()->property_id) {
            abort(404);
        }

        return $this->getJsonResponse(
            $roomType->load(['bedTypes', 'amenities', 'images']),
            RoomTypeResource::class,
            asSingleModel: true,
        );
    }

    public function update(UpdateRoomTypeRequest $request, RoomType $roomType): JsonResponse
    {
        $type = $this->types->update($request->user(), $roomType, $request->validated());

        return $this->getJsonResponse($type, RoomTypeResource::class, 'messages.success.room_type_updated', true);
    }
}
