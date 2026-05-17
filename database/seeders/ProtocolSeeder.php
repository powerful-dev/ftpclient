<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProtocolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('protocols')->insert([
            [
                'name' => 'FTP',
            ],
            [
                'name' => 'FTPS',
            ],
            [
                'name' => 'SFTP',
            ],
            [
                'name' => 'Amazon S3',
            ],
        ]);
    }
}
