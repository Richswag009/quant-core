<?php

namespace App\Enums;


enum BatchStatusItem: string
{
    case PENDING = 'PENDING';
    case VALID = 'VALID';
    case INVALID = 'INVALID';
    case POSTING = 'POSTING';
    case POSTED = 'POSTED';
    case FAILED = 'FAILED';
}
