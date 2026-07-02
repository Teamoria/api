<?php

namespace App\Enums;

enum ProjectRole: string
{
    case MANAGER = 'manager';
    case MEMBER = 'member';
    case VIEWER = 'viewer';
}
