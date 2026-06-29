<?php

namespace App;

enum TaskPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case EMERGENCY = 'emergency';
}
