<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RawDocServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Populates the raw_doc_service table with initial data.
     */
    public function run()
    {
        DB::table('raw_doc_service')->insert([
            [
                'id' => '8e4fca44-067e-11f1-b4b5-ea16655562dc',
                'name' => 'Konsultasi',
                'duration_minutes' => 30,
                'price' => 100000,
                'created_at' => '2026-02-10 12:46:44',
                'created_by' => '',
                'updated_at' => null,
                'updated_by' => null
            ],
            [
                'id' => 'bcb5d41e-067e-11f1-b4b5-ea16655562dc',
                'name' => 'Scaling',
                'duration_minutes' => 90,
                'price' => 250000,
                'created_at' => '2026-02-10 12:48:02',
                'created_by' => '',
                'updated_at' => null,
                'updated_by' => null
            ],
            [
                'id' => 'bcb5e4c2-067e-11f1-b4b5-ea16655562dc',
                'name' => 'Penambalan',
                'duration_minutes' => 120,
                'price' => 300000,
                'created_at' => '2026-02-10 12:48:02',
                'created_by' => '',
                'updated_at' => null,
                'updated_by' => null
            ]
        ]);
    }
}