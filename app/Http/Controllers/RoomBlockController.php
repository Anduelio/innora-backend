<?php

namespace App\Http\Controllers;

use App\Managers\Hotel\RoomManager;
use App\Models\RoomBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomBlockController extends Controller
{
    public function __construct(private readonly RoomManager $rooms) {}

    public function destroy(Request $request, RoomBlock $roomBlock): JsonResponse
    {
        $this->rooms->removeBlock($request->user(), $roomBlock);

        return $this->successResponse('messages.success.the_action_was_completed_successfully');
    }
}
