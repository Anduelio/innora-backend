<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateHotelSettingsRequest;
use App\Http\Resources\HotelSettingsResource;
use App\Managers\Hotel\HotelSettingsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HotelSettingsController extends Controller
{
    public function __construct(private readonly HotelSettingsManager $settings) {}

    public function show(Request $request): JsonResponse
    {
        return $this->getJsonResponse($this->settings->show($request->user()), HotelSettingsResource::class, asSingleModel: true);
    }

    public function update(UpdateHotelSettingsRequest $request): JsonResponse
    {
        $property = $this->settings->update($request->user(), $request->validated());

        return $this->getJsonResponse($property, HotelSettingsResource::class, 'messages.success.the_action_was_completed_successfully', true);
    }
}
