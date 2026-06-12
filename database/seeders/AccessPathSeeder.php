<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccessPathSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Populates the access_path table with initial data.
     */
    public function run()
    {
        DB::table('access_path')->insert([
            [
                'id' => '25c2944c-f7be-45d0-84f4-2e1d2f530b4a',
                'pid' => 'e10e4cd1-137d-46d7-bb0f-7864cfc5a4fc',
                'nama' => 'Akses',
                'icon' => 'dripicons-checkmark',
                'urutan' => 3,
                'urutan_path' => null,
                'link' => 'setaccess',
                'pid_path' => '131ca369-c607-11ec-9240-809133ff6caa,32361898-c607-11ec-9240-809133ff6caa,25c2944c-f7be-45d0-84f4-2e1d2f530b4a'
            ],
            [
                'id' => '32361898-c607-11ec-9240-809133ff6caa',
                'pid' => '',
                'nama' => 'Settings',
                'icon' => 'icon-fa fas fa-cog',
                'urutan' => 1,
                'urutan_path' => '',
                'link' => 'MetricaSetting',
                'pid_path' => '131ca369-c607-11ec-9240-809133ff6caa'
            ],
            [
                'id' => 'a737d05f-c607-11ec-9240-809133ff6caa',
                'pid' => 'e10e4cd1-137d-46d7-bb0f-7864cfc5a4fc',
                'nama' => 'User',
                'icon' => 'dripicons-user',
                'urutan' => 1,
                'urutan_path' => null,
                'link' => 'setusermanagement',
                'pid_path' => '131ca369-c607-11ec-9240-809133ff6caa,32361898-c607-11ec-9240-809133ff6caa,a737d05f-c607-11ec-9240-809133ff6caa'
            ],
            [
                'id' => 'd0bbd8eb-a466-453e-96cc-39b0d8192e9c',
                'pid' => '',
                'nama' => 'Setting',
                'icon' => 'icon-fa fas fa-cog',
                'urutan' => 2,
                'urutan_path' => 02,
                'link' => 'MetricaSetting',
                'pid_path' => null
            ],
            [
                'id' => 'd1c8f634-c607-11ec-9240-809133ff6caa',
                'pid' => 'e10e4cd1-137d-46d7-bb0f-7864cfc5a4fc',
                'nama' => 'Group',
                'icon' => 'dripicons-user-group',
                'urutan' => 2,
                'urutan_path' => null,
                'link' => 'setgroup',
                'pid_path' => '131ca369-c607-11ec-9240-809133ff6caa,32361898-c607-11ec-9240-809133ff6caa,d1c8f634-c607-11ec-9240-809133ff6caa'
            ],
            [
                'id' => 'e10e4cd1-137d-46d7-bb0f-7864cfc5a4fc',
                'pid' => '32361898-c607-11ec-9240-809133ff6caa',
                'nama' => 'User Access',
                'icon' => 'icon-fa fas fa-users',
                'urutan' => 1,
                'urutan_path' => '',
                'link' => '',
                'pid_path' => null
            ]
        ]);
    }
}