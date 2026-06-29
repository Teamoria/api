<?php

namespace App;

enum ProjectRole: string
{
    case MANAGER = 'manager';
    case MEMBER = 'member';
    case VIEWER = 'viewer';
}
