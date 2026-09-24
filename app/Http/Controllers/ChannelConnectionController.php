<?php

namespace App\Http\Controllers;

use App\Channels\ChannelCatalog;
use App\Http\Requests\SaveChannelConnectionRequest;
use App\Managers\Hotel\ChannelConnectionManager;
use App\Models\ChannelConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelConnectionController extends Controller
{
    public function __construct(private readonly ChannelConnectionManager $channels) {}

    public function catalog(): JsonResponse
    {
        return $this->responseToJson('messages.success.the_action_was_completed_successfully', 200, [
            'providers' => ChannelCatalog::catalog(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $rows = $this->channels->list($request->user())
            ->map(fn (ChannelConnection $connection) => $this->channels->present($connection))
            ->values()
            ->all();

        return $this->responseToJson('messages.success.the_action_was_completed_successfully', 200, $rows);
    }

    public function store(SaveChannelConnectionRequest $request): JsonResponse
    {
        $connection = $this->channels->save($request->user(), $request->validated());

        return $this->responseToJson(
            'messages.channels.saved',
            200,
            $this->channels->present($connection),
        );
    }

    public function destroy(Request $request, ChannelConnection $channelConnection): JsonResponse
    {
        $this->channels->delete($request->user(), $channelConnection);

        return $this->successResponse('messages.channels.removed');
    }
}
