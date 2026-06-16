<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\CheckList;
use App\Models\EventType;
use App\Models\Location;
use App\Models\Status;
use App\Models\Tag;
use App\Models\Type;
use Illuminate\Database\Seeder;

/**
 * Single source of truth for the agency's lookup data shared across the
 * client & candidate domain (types, locations, tags, statuses, checklists,
 * event types). All other seeders read these values back via firstOrCreate
 * on the same keys, so the data is never duplicated.
 */
class SupportingDataSeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::where('subdomain_prefix', 'coasttocoast')->first();

        if (! $agency) {
            $this->command->error('Agency not found. Run AgencySeeder first.');

            return;
        }

        // Client engagement types + candidate role types (candidate.type_id references these).
        foreach (['Full-Time', 'Part-Time', 'Temporary', 'Weekend'] as $name) {
            Type::firstOrCreate(
                ['agency_id' => $agency->id, 'name' => $name, 'type' => 'client'],
                ['status' => 1]
            );
        }

        foreach (['Nanny', 'Babysitter', 'Au Pair', 'Newborn Specialist'] as $name) {
            Type::firstOrCreate(
                ['agency_id' => $agency->id, 'name' => $name, 'type' => 'candidate'],
                ['status' => 1]
            );
        }

        // Locations are shared by clients, candidates and jobs.
        foreach (['Miami Beach', 'Coral Gables', 'Brickell', 'Key Biscayne'] as $location) {
            Location::firstOrCreate(
                ['agency_id' => $agency->id, 'location' => $location],
                ['status' => 1]
            );
        }

        foreach (['VIP', 'New', 'Referral', 'Repeat'] as $name) {
            Tag::firstOrCreate(
                ['agency_id' => $agency->id, 'name' => $name, 'type' => 'client'],
                ['status' => 1]
            );
        }

        foreach ([
            ['Active', '#28a745', 1],
            ['Inactive', '#dc3545', 2],
            ['Lead', '#ffc107', 3],
        ] as [$name, $color, $serial]) {
            Status::firstOrCreate(
                ['agency_id' => $agency->id, 'name' => $name, 'type' => 'client'],
                ['color' => $color, 'serial' => $serial]
            );
        }

        foreach (['ID Verified', 'Contract Signed', 'Background Check', 'Orientation Completed'] as $name) {
            CheckList::firstOrCreate(
                ['agency_id' => $agency->id, 'name' => $name, 'type' => 'client'],
                ['status' => 1]
            );
        }

        foreach (['Interview', 'Meeting', 'Follow-up', 'Orientation'] as $name) {
            EventType::firstOrCreate(
                ['agency_id' => $agency->id, 'name' => $name, 'type' => 'client'],
                ['status' => 1]
            );
        }

        $this->command->info('Supporting data seeder completed successfully!');
    }
}
