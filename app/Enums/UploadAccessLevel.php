<?php

namespace App\Enums;

enum UploadAccessLevel: string
{
    case VIEW = 'view';
    case MANAGE = 'manage';
}
