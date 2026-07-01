<?php

namespace App\Enums;

enum TaskStatus: string
{
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case ON_HOLD = 'on_hold';
    case BLOCKED = 'blocked';
    case REVIEW = 'review';
    case DONE = 'done';
}
