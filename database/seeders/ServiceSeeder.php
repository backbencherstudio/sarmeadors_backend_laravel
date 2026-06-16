<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Location;
use App\Models\Service;
use App\Models\SubLocation;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
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
        | Services
        |--------------------------------------------------------------------------
        */
        $services = [
            'Full-Time Nanny Placement',
            'Part-Time Nanny Placement',
            'Babysitting',
            'Newborn Care Specialist',
            'After-School Care',
            'Temporary / Backup Care',
        ];

        foreach ($services as $service) {
            Service::firstOrCreate(
                ['agency_id' => $agency->id, 'service_name' => $service],
                ['status' => true]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Sub Locations (children of existing locations)
        |--------------------------------------------------------------------------
        */
        $subLocationMap = [
            'Miami Beach' => ['South Beach', 'Mid-Beach', 'North Beach'],
            'Coral Gables' => ['Downtown Gables', 'Coral Way'],
            'Brickell' => ['Brickell Key', 'Mary Brickell Village'],
            'Key Biscayne' => ['Crandon', 'Harbor Drive'],
        ];

        foreach ($subLocationMap as $locationName => $subLocations) {
            $location = Location::where('agency_id', $agency->id)->where('location', $locationName)->first();

            if (! $location) {
                continue;
            }

            foreach ($subLocations as $subLocation) {
                SubLocation::firstOrCreate(
                    [
                        'agency_id' => $agency->id,
                        'location_id' => $location->id,
                        'sub_location' => $subLocation,
                    ]
                );
            }
        }

        $this->command->info('Service & sub-location seeder completed successfully!');
    }
}
