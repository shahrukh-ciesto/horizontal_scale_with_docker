<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MillionUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $totalUsers = 1000000;
        $chunkSize = 5000;

        $password = Hash::make('password');

        for ($i = 0; $i < $totalUsers; $i += $chunkSize) {

            $users = [];

            $count = min($chunkSize, $totalUsers - $i);

            for ($j = 1; $j <= $count; $j++) {

                $number = $i + $j;

                $users[] = [
                    'name' => "Test User {$number}",
                    'email' => "testuser{$number}@example.com",
                    'password' => $password,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            User::insert($users);

            $this->command->info(
                "Inserted " . min($i + $count, $totalUsers) . " / {$totalUsers} users"
            );
        }
    }
}
