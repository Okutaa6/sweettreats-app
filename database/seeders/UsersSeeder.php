<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Populates the users table with initial data.
     */
    public function run()
    {
        DB::table('users')->insert([
            [
                'id' => '0cc1e24e-130d-11ed-b785-e82a44eb9daf',
                'nama' => 'Admin',
                'email' => 'admin@gmail.com',
                'password' => 'beb0781567aaa6c4be14eebf8f9eea5344ee836d8313bf32873ef8f1232b196b260a4aa621aa5fe4c1466287349b1e008ec5c083dae5fe41b88ea2530b20f298484a5c64a82d9fc8921117a29572ee747a79fffe07',
                'status' => 1,
                'last_login' => '2024-10-09 06:22:16',
                'reset_token' => null,
                'register_token' => null,
                'group_id' => '4673beb8-bbaf-11ec-be92-809133ff6caa'
            ],
            [
                'id' => '24af9ca3-86cd-4ec5-b41d-7932b86ebede',
                'nama' => 'Kiki',
                'email' => 'user@gmail.com',
                'password' => '73dcb26540a98f4f6343ec1f06bc08db746dd1add168a4cb9d5bf73fa3d189f7b3d779514a950d0b1d8386ee622d0df50e7ffb1283267eab659fafacd862e6b849900480db9f812e5256d3069407ba3160b4b7a698',
                'status' => 1,
                'last_login' => null,
                'reset_token' => null,
                'register_token' => 'c8a333d86242b4aad9a860de883e8546eb0da37d0fdb53caa0c645046b824482d09b8555f77e8334ce1f011ee16ca4225e3111df0abad94bd2c9b9bc8ada4bc2fdea86cb308924df9902efe8bc6f4528dee12c29aae62c',
                'group_id' => '55c6ff60-9b6b-447e-8dbb-9fe9fb6f223f'
            ]
        ]);
    }
}