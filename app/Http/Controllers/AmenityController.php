<?php

namespace App\Http\Controllers;

use App\Http\Resources\AmenityResource;
use App\Models\Amenity;
use Illuminate\Http\JsonResponse;

class AmenityController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->getJsonResponse(Amenity::query()->orderBy('category')->orderBy('code')->get(), AmenityResource::class);
    }
}
