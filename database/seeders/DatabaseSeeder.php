<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'first_name' => 'System',
            'last_name'  => 'Admin',
            'email'      => 'admin@sdss.local',
            'password'   => 'Admin@1234',
            'status'     => 'approved',
            'role'       => 'admin',
        ]);
    }
}
