<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkRoomRequest;
use App\Http\Requests\ChangeRoomStatusRequest;
use App\Http\Requests\StoreRoomBlockRequest;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Http\Resources\RoomBlockResource;
use App\Http\Resources\RoomResource;
use App\Managers\Hotel\RoomManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function __construct(private readonly RoomManager $rooms) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->rooms->list($request->user(), $request->validate([
            'no_pagination' => ['nullable'],
        ]));

        return $this->getJsonResponse($this->fetchResults($query), RoomResource::class);
    }

    public function store(StoreRoomRequest $request): JsonResponse
    {
        $room = $this->rooms->create($request->user(), $request->validated());

        return $this->getJsonResponse($room, RoomResource::class, 'messages.success.room_created', true);
    }

    public function bulk(BulkRoomRequest $request): JsonResponse
    {
        $rooms = $this->rooms->createMany($request->user(), $request->validated());

        return $this->getJsonResponse(collect($rooms), RoomResource::class, 'messages.success.rooms_created');
    }

    public function update(UpdateRoomRequest $request, string $number): JsonResponse
    {
        $room = $this->rooms->find($request->user(), $number);
        $room = $this->rooms->update($request->user(), $room, $request->validated());

        return $this->getJsonResponse($room, RoomResource::class, 'messages.success.room_updated', true);
    }

    public function status(ChangeRoomStatusRequest $request, string $number): JsonResponse
    {
        $room = $this->rooms->find($request->user(), $number);
        $room = $this->rooms->changeStatus($request->user(), $room, $request->validated('operationalStatus'));

        return $this->getJsonResponse($room, RoomResource::class, 'messages.success.room_updated', true);
    }

    public function block(StoreRoomBlockRequest $request, string $number): JsonResponse
    {
        $room = $this->rooms->find($request->user(), $number);
        $block = $this->rooms->block($request->user(), $room, $request->validated());

        return $this->getJsonResponse($block, RoomBlockResource::class, 'messages.success.room_blocked', true);
    }
}
