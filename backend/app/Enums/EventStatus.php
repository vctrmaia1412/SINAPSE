<?php

namespace App\Enums;

enum EventStatus: string
{
    case Published = 'published';
    case Cancelled = 'cancelled';
}
