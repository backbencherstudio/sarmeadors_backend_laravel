<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::where('subdomain_prefix', 'coasttocoast')->first();

        if (! $agency) {
            $this->command->error('Agency not found. Run AgencySeeder first.');

            return;
        }

        $client = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Alex',
            'last_name' => 'Johnson',
            'email' => 'client@gmail.com',
            'mobile' => '+1 305 000 0002',
            'hear_about_us' => 'Google',
            'payment_status' => 'pending',
        ]);

        $user = User::create([
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'email' => $client->email,
            'mobile' => $client->mobile,
            'agency_id' => $agency->id,
            'is_owner' => 0,
            'password' => bcrypt('111111'),
        ]);

        $user->assignRole(
            Role::where('name', 'client')->where('guard_name', 'api')->first()
        );
    }
}
