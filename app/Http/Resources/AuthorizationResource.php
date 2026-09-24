<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthorizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'token_type' => $this->token_type,
            'expires_in' => (int) $this->expires_in,
            'access_token' => $this->access_token,
            'refresh_token' => $this->refresh_token,
        ];
    }
}
