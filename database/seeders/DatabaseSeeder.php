<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RawBranchSeeder::class,
            RawDoctorSeeder::class,
            RawDocServiceSeeder::class,
            RawDoctorScheduleSeeder::class,
            AccessPathSeeder::class,
            GroupAccessSeeder::class,
            GroupPathSeeder::class,
            SysProfileSeeder::class,
            UsersSeeder::class,
        ]);
    }
}
