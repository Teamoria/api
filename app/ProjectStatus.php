<?php

namespace App;

enum ProjectStatus: string
{
    case ACTIVE = 'active';
    case PENDING = 'pending';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
