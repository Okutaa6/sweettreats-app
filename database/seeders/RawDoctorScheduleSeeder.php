<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RawDoctorScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Populates the raw_doctor_schedule table with initial data.
     */
    public function run()
    {
        DB::table('raw_doctor_schedule')->insert([
            [
                'id' => '13d615b0-067f-11f1-b4b5-ea16655562dc',
                'raw_doctor_id' => '687b0b08-067e-11f1-b4b5-ea16655562dc',
                'raw_branch_id' => '6fb99a92-067e-11f1-b4b5-ea16655562dc',
                'day' => 2,
                'start_hour' => '08:00:00',
                'end_hour' => '12:00:00',
                'created_at' => '2026-02-10 12:50:28',
                'created_by' => '',
                'updated_at' => null,
                'updated_by' => null
            ],
            [
                'id' => '18f94152-067f-11f1-b4b5-ea16655562dc',
                'raw_doctor_id' => '687b0b08-067e-11f1-b4b5-ea16655562dc',
                'raw_branch_id' => '6fb99a92-067e-11f1-b4b5-ea16655562dc',
                'day' => 3,
                'start_hour' => '08:00:00',
                'end_hour' => '12:00:00',
                'created_at' => '2026-02-10 12:50:37',
                'created_by' => '',
                'updated_at' => null,
                'updated_by' => null
            ],
            [
                'id' => '438c114c-067f-11f1-b4b5-ea16655562dc',
                'raw_doctor_id' => '687b1512-067e-11f1-b4b5-ea16655562dc',
                'raw_branch_id' => '6fb9a226-067e-11f1-b4b5-ea16655562dc',
                'day' => 1,
                'start_hour' => '08:00:00',
                'end_hour' => '12:00:00',
                'created_at' => '2026-02-10 12:51:48',
                'created_by' => '',
                'updated_at' => null,
                'updated_by' => null
            ],
            [
                'id' => '4c1cdf58-067f-11f1-b4b5-ea16655562dc',
                'raw_doctor_id' => '687b1512-067e-11f1-b4b5-ea16655562dc',
                'raw_branch_id' => '6fb9a226-067e-11f1-b4b5-ea16655562dc',
                'day' => 2,
                'start_hour' => '08:00:00',
                'end_hour' => '12:00:00',
                'created_at' => '2026-02-10 12:52:03',
                'created_by' => '',
                'updated_at' => null,
                'updated_by' => null
            ],
            [
                'id' => 'ef043eb0-067e-11f1-b4b5-ea16655562dc',
                'raw_doctor_id' => '687b0b08-067e-11f1-b4b5-ea16655562dc',
                'raw_branch_id' => '6fb99a92-067e-11f1-b4b5-ea16655562dc',
                'day' => 1,
                'start_hour' => '08:00:00',
                'end_hour' => '12:00:00',
                'created_at' => '2026-02-10 12:49:27',
                'created_by' => '',
                'updated_at' => null,
                'updated_by' => null
            ]
        ]);
    }
}