<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GroupPathSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Populates the group_path table with initial data.
     */
    public function run()
    {
        DB::table('group_path')->insert([
            [
                'id' => '4673beb8-bbaf-11ec-be92-809133ff6caa',
                'nama' => 'ADMIN',
                'deskripsi' => 'As an Admin',
                'landing_page' => 'setaccess'
            ],
            [
                'id' => '55c6ff60-9b6b-447e-8dbb-9fe9fb6f223f',
                'nama' => 'USER',
                'deskripsi' => 'User',
                'landing_page' => 'setsetting'
            ]
        ]);
    }
}