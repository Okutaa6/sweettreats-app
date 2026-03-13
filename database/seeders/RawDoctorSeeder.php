<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RawDoctorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Populates the raw_doctor table with initial data.
     */
    public function run()
    {
        DB::table('raw_doctor')->insert([
            [
                'id' => '687b0b08-067e-11f1-b4b5-ea16655562dc',
                'name' => 'drg Budi'
            ],
            [
                'id' => '687b1512-067e-11f1-b4b5-ea16655562dc',
                'name' => 'drg Ayu'
            ]
        ]);
    }
}