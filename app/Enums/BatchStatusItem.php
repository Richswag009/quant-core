<?php

namespace App\Enums;


enum BatchStatusItem: string
{
    case DRAFT = 'DRAFT';
    case PENDING = 'PENDING';
    case VALID = 'VALID';
    case INVALID = 'INVALID';
    case PENDING_APPROVAL = 'PENDING_APPROVAL';
    case APPROVED = 'APPROVED';
    case POSTING = 'POSTING';
    case POSTED = 'POSTED';
    case FAILED = 'FAILED';
}
