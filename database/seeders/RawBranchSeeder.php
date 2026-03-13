<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RawBranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Populates the raw_branch table with initial data.
     */
    public function run()
    {
        DB::table('raw_branch')->insert([
            [
                'id' => '6fb99a92-067e-11f1-b4b5-ea16655562dc',
                'name' => 'Bintaro'
            ],
            [
                'id' => '6fb9a226-067e-11f1-b4b5-ea16655562dc',
                'name' => 'Kalideres'
            ],
            [
                'id' => '7ac39cf8-067e-11f1-b4b5-ea16655562dc',
                'name' => 'Sunter'
            ],
            [
                'id' => '7ac3a4f0-067e-11f1-b4b5-ea16655562dc',
                'name' => 'Pamulang'
            ],
            [
                'id' => '7e2284ea-067e-11f1-b4b5-ea16655562dc',
                'name' => 'Cinere'
            ]
        ]);
    }
}