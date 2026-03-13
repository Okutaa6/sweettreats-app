<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SysProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Populates the sys_profile table with initial data.
     */
    public function run()
    {
        DB::table('sys_profile')->insert([
            [
                'id' => '7ee2b583-1316-11ed-b785-e82a44eb9daf',
                'syslogo' => '7ee2b583-1316-11ed-b785-e82a44eb9daf.jpeg',
                'systitle' => 'Multi Sharing',
                'sysname' => 'Oktav'
            ]
        ]);
    }
}