<?php

namespace App\Enums;

enum RoomStatus: string
{
    case Active = 'active';
    case OutOfService = 'out_of_service';
}
