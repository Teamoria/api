<?php

namespace App\Enums;

enum PlanStatus: string
{
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';
}
