<?php

namespace App\Traits;

use Carbon\Carbon;

trait FormatsTime
{
    protected function formatTime(?string $time): ?string
    {
        return $time ? Carbon::parse($time)->format('g:i A') : null;
    }
}
