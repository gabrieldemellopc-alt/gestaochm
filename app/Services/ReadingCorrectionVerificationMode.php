<?php

namespace App\Services;

/** Centralizes the temporary verification policy until it becomes configurable per unit. */
class ReadingCorrectionVerificationMode
{
    public const PHOTO = 'photo';
    public const VIDEO = 'video';

    public function current(): string
    {
        return self::PHOTO;
    }
}
