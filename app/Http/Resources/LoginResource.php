<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{user: User, authorization: object} $resource
 */
class LoginResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user' => UserResource::make($this->resource['user']),
            'authorization' => AuthorizationResource::make($this->resource['authorization']),
        ];
    }
}
