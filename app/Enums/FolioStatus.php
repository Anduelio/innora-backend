<?php

namespace App\Enums;

enum FolioStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Voided = 'voided';
}
