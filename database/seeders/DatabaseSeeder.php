<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $this->call(RolePermissionSeeder::class);
        $this->call(AgencySeeder::class);
        $this->call(ClientSeeder::class);

        // $admin=User::factory()->create([
        //     'first_name' => 'Admin',
        //     'last_name' => 'Web',
        //     'email' => 'adminweb@gmail.com',
        //     'department' => 'IT',
        //     'mobile' => '01700000000',
        //     'agency_id' => 0,
        //     'password' => bcrypt('111111'),
        // ]);
        $adminApi=User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@gmail.com',
            'mobile' => '01700000001',
            'agency_id' => 0,
            'is_owner' => 1,
            'password' => bcrypt('111111'),
        ]);

        // $admin->assignRole('admin');
        $adminApi->assignRole(Role::where('name','super_admin')->where('guard_name','api')->first());
    }
}
