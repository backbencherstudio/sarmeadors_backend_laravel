<?php

namespace App\Traits;

/**
 * Shared helpers for presenting monetary amounts.
 */
trait FormatsMoney
{
    /**
     * Format an amount as a trimmed decimal string (e.g. "35", "20.5").
     *
     * Normalises to two decimals first so whole-number column values such as
     * "100" are never mangled into "1" by trailing-zero trimming.
     */
    protected function formatAmount(int|float|string|null $amount): string
    {
        return rtrim(rtrim(number_format((float) $amount, 2, '.', ''), '0'), '.');
    }

    /**
     * Format an hourly compensation amount as a "$35/hr" style label.
     */
    protected function formatHourlyRate(int|float|string|null $amount): string
    {
        return '$'.$this->formatAmount($amount).'/hr';
    }
}
