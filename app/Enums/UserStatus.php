<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Pending = 'pending';     // providers waiting for admin approval
    case Suspended = 'suspended';
}
