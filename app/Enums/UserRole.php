<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case COMPANY_OWNER = 'company_owner';
    case COMPANY_MANAGER = 'company_manager';
    case COMPANY_MEMBER = 'company_member';
}
