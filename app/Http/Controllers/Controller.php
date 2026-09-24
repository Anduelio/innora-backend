<?php

namespace App\Http\Controllers;

use App\Traits\ApiTrait;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use ApiTrait, AuthorizesRequests, ValidatesRequests;
}
