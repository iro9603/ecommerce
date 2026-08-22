<?php

namespace Database\Seeders\Frontend;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::create([
            'name' => 'Test user',
            'email' => 'user@gmail.com',
            'password' => '1234',
            'status' => true
        ]);
    }
}
