<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AuthenticationTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('authentication_types')->insert([
            [
                'name' => 'Password',
                'tag_name' => 'password',
            ],
            [
                'name' => 'SSH Key',
                'tag_name' => 'ssh_key',
            ],
        ]);
    }
}
