<?php

namespace App\Enums;


enum BatchStatus: string
{
    case DRAFT = 'DRAFT';
    case VALIDATED = 'VALIDATED';
    case PENDING_APPROVAL = 'PENDING_APPROVAL';
    case APPROVED = 'APPROVED';
    case POSTING = 'POSTING';
    case PARTIALLY_POSTED = 'PARTIALLY_POSTED';
    case POSTED = 'POSTED';
    case REJECTED = 'REJECTED';
    case FAILED = "FAILED";
}
