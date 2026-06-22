<?php

namespace Tests\Unit;

use App\Models\LongTermJobChild;
use App\Models\ShortTermJobChild;
use App\Models\ShortTermJobDate;
use Tests\TestCase;

class JobDateCastsTest extends TestCase
{
    /**
     * A non-ISO but parseable date string must be normalized to Y-m-d so it is
     * never handed to MySQL as-is (which would raise a 1292 invalid date error).
     */
    public function test_short_term_job_child_normalizes_date_of_birth(): void
    {
        $child = new ShortTermJobChild(['date_of_birth' => '11-Oct-1994']);

        $this->assertSame('1994-10-11', $child->date_of_birth->format('Y-m-d'));
        $this->assertSame('1994-10-11', $child->toArray()['date_of_birth']);
    }

    public function test_long_term_job_child_normalizes_date_of_birth(): void
    {
        $child = new LongTermJobChild(['date_of_birth' => '11-Oct-1994']);

        $this->assertSame('1994-10-11', $child->date_of_birth->format('Y-m-d'));
        $this->assertSame('1994-10-11', $child->toArray()['date_of_birth']);
    }

    public function test_short_term_job_date_normalizes_booking_date(): void
    {
        $date = new ShortTermJobDate(['booking_date' => '11-Oct-1994']);

        $this->assertSame('1994-10-11', $date->booking_date->format('Y-m-d'));
        $this->assertSame('1994-10-11', $date->toArray()['booking_date']);
    }
}
