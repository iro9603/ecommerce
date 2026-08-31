<?php

namespace Database\Seeders\Frontend;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            [
                'email' => 'user@gmail.com',
            ],
            [
                'name' => 'Test user',
                'password' => Hash::make('password'),
                'status' => true,
            ]
        );
    }
}
