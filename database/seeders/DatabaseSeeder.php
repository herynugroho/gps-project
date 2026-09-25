<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@primatrack.com'],
            [
                'name' => 'Administrator PrimaTrack',
                'password' => bcrypt('Tidakadaji'),
                'role' => 'super_admin',
            ]
        );

        $this->call(DeviceSeeder::class);
    }
}
