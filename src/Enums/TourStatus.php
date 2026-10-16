<?php

namespace Shapperize\Activation\Enums;

enum TourStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Skipped = 'skipped';

    /** Skipped tours are resumable; only a finished one stops being offered. */
    public function isResumable(): bool
    {
        return $this === self::InProgress || $this === self::Skipped;
    }
}
