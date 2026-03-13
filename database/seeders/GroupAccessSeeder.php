<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GroupAccessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Populates the group_access table with initial data.
     */
    public function run()
    {
        DB::table('group_access')->insert([
            [
                'id' => '2f0de4b1-0939-4f80-90be-af14f1bf20e5',
                'group_id' => '4673beb8-bbaf-11ec-be92-809133ff6caa',
                'access_id' => 'e10e4cd1-137d-46d7-bb0f-7864cfc5a4fc'
            ],
            [
                'id' => '40d61527-c39a-451c-8134-f1014fb5f883',
                'group_id' => '4673beb8-bbaf-11ec-be92-809133ff6caa',
                'access_id' => '32361898-c607-11ec-9240-809133ff6caa'
            ],
            [
                'id' => 'cd9a3a77-a820-4b53-af79-7c8b366216d2',
                'group_id' => '4673beb8-bbaf-11ec-be92-809133ff6caa',
                'access_id' => 'd1c8f634-c607-11ec-9240-809133ff6caa'
            ],
            [
                'id' => 'd50bf3ac-43a2-4036-a03f-124f0ebdfc35',
                'group_id' => '4673beb8-bbaf-11ec-be92-809133ff6caa',
                'access_id' => '25c2944c-f7be-45d0-84f4-2e1d2f530b4a'
            ],
            [
                'id' => 'ea4354d1-b8f6-40c3-aa63-e32b5b7cfdb2',
                'group_id' => '4673beb8-bbaf-11ec-be92-809133ff6caa',
                'access_id' => 'a737d05f-c607-11ec-9240-809133ff6caa'
            ]
        ]);
    }
}