<?php

namespace App\Http\Controllers;

use App\Managers\Sync\SyncManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(private readonly SyncManager $sync) {}

    public function status(Request $request): JsonResponse
    {
        return $this->responseToJson('', 200, $this->sync->status($request->user()));
    }

    public function retry(Request $request): JsonResponse
    {
        return $this->responseToJson('messages.success.the_action_was_completed_successfully', 200, $this->sync->retry($request->user()));
    }

    public function fail(Request $request): JsonResponse
    {
        return $this->responseToJson('', 200, $this->sync->fail($request->user()));
    }
}
