<?php

namespace App\Enums;

enum UploadVisibility: string
{
    case PRIVATE = 'private';
    case MEMBERS = 'members';
    case SELECTED = 'selected';
}
