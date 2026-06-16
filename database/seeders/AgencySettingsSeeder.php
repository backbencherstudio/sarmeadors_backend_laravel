<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\AgencyBusinessHour;
use App\Models\AgencyGlobalJobSetting;
use App\Models\AgencyHoliday;
use App\Models\AgencyNote;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;

class AgencySettingsSeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::where('subdomain_prefix', 'coasttocoast')->first();

        if (! $agency) {
            $this->command->error('Agency not found. Run AgencySeeder first.');

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Business Hours
        |--------------------------------------------------------------------------
        */
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        foreach ($days as $day) {
            $isWeekend = in_array($day, ['Saturday', 'Sunday']);
            AgencyBusinessHour::firstOrCreate(
                ['agency_id' => $agency->id, 'day' => $day],
                [
                    'start_time' => $isWeekend ? null : '09:00:00',
                    'end_time' => $isWeekend ? null : '17:00:00',
                    'is_open' => ! $isWeekend,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Holidays
        |--------------------------------------------------------------------------
        */
        $locationIds = Location::where('agency_id', $agency->id)->pluck('id')->all();

        $holidays = [
            ['name' => 'New Year\'s Day', 'date' => now()->startOfYear()->format('Y-m-d'), 'common' => true],
            ['name' => 'Independence Day', 'date' => now()->setDate(now()->year, 7, 4)->format('Y-m-d'), 'common' => true],
            ['name' => 'Thanksgiving', 'date' => now()->setDate(now()->year, 11, 27)->format('Y-m-d'), 'common' => true],
            ['name' => 'Christmas Day', 'date' => now()->setDate(now()->year, 12, 25)->format('Y-m-d'), 'common' => true],
            ['name' => 'Agency Anniversary', 'date' => now()->setDate(now()->year, 6, 1)->format('Y-m-d'), 'common' => false],
        ];

        foreach ($holidays as $holiday) {
            AgencyHoliday::firstOrCreate(
                ['agency_id' => $agency->id, 'holiday_name' => $holiday['name']],
                [
                    'date' => $holiday['date'],
                    'location_ids' => $holiday['common'] ? null : $locationIds,
                    'is_common' => $holiday['common'],
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Global Job Settings
        |--------------------------------------------------------------------------
        */
        AgencyGlobalJobSetting::firstOrCreate(
            ['agency_id' => $agency->id],
            [
                'settings' => [
                    'short_term' => [
                        'auto_approve' => true,
                        'payment_required' => false,
                        'default_currency' => 'usd',
                        'min_hours' => 2,
                    ],
                    'long_term' => [
                        'auto_approve' => false,
                        'require_interview' => true,
                        'default_currency' => 'usd',
                        'probation_days' => 30,
                    ],
                    'notifications' => [
                        'email' => true,
                        'sms' => false,
                    ],
                ],
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Agency Notes
        |--------------------------------------------------------------------------
        */
        $owner = User::where('agency_id', $agency->id)->where('is_owner', 1)->first()
            ?? User::where('agency_id', $agency->id)->first();

        if ($owner) {
            $notes = [
                ['note' => 'Remember to follow up with pending client onboarding documents.', 'pin' => true, 'read' => false],
                ['note' => 'Quarterly review meeting scheduled for next month.', 'pin' => false, 'read' => true],
                ['note' => 'Update background-check vendor contract before renewal date.', 'pin' => false, 'read' => false],
            ];

            foreach ($notes as $note) {
                AgencyNote::firstOrCreate(
                    ['agency_id' => $agency->id, 'user_id' => $owner->id, 'note' => $note['note']],
                    ['pin_note' => $note['pin'], 'is_read' => $note['read']]
                );
            }
        }

        $this->command->info('Agency settings seeder completed successfully!');
    }
}
