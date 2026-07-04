<?php

namespace App\Enums;

enum UploadScope: string
{
    case COMPANY = 'company';
    case PROJECT = 'project';
    case TASK = 'task';
    case PERSONAL = 'personal';
}
