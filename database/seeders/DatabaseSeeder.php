<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Yerel geliştirme için bilinen şifreli yönetici; canlıda
        // `php artisan admin:create` kullanılır.
        if (app()->isLocal()) {
            User::factory()->admin()->create([
                'name' => 'Test Yönetici',
                'email' => 'test@example.com',
            ]);
        }

        $this->call(CourseSeeder::class);
    }
}
