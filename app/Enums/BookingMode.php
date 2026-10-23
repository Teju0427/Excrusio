<?php

namespace App\Enums;

enum BookingMode: string
{
    case Instant = 'instant';   // confirmed straight after payment
    case Request = 'request';   // provider must approve (hotels)
}
