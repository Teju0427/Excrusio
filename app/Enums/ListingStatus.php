<?php

namespace App\Enums;

enum ListingStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Rejected = 'rejected';
    case Archived = 'archived';
}
