<?php

namespace Database\Factories;

use App\Enums\CourseLevel;
use App\Enums\CourseSource;
use App\Enums\TextStatus;
use App\Models\BookPage;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $words = array_map(
            static fn (): string => fake()->unique()->word(),
            range(1, 3),
        );

        $name = Str::title(implode(' ', $words));

        return [
            'level' => CourseLevel::Lise,
            'name' => $name,
            'subject' => Str::upper($words[0]),
            'slug' => Str::slug($name),
            'source' => CourseSource::Upload,
            'file_path' => 'dersler/'.Str::slug($name).'.pdf',
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * MEB aokul kataloğundan gelen, PDF'i adresinden indirilen ders.
     */
    public function aokul(string $externalKey = '2/1/1'): static
    {
        return $this->state(fn (array $attributes): array => [
            'source' => CourseSource::Aokul,
            'external_key' => $externalKey,
            'source_url' => "http://aokul.gov.tr/Kitaplar/Kitaplar/{$externalKey}/kitap.pdf",
            'file_path' => null,
        ]);
    }

    /**
     * Metni çıkarılmış, belirtilen sayıda sayfası olan ders.
     */
    public function withText(int $pages = 5): static
    {
        return $this->state(fn (array $attributes): array => [
            'text_status' => TextStatus::Ready,
            'text_extracted_at' => now(),
            'page_count' => $pages,
        ])->afterCreating(function (Course $course) use ($pages): void {
            foreach (range(1, $pages) as $pageNumber) {
                BookPage::factory()->for($course)->create([
                    'page_number' => $pageNumber,
                    'content' => "Sayfa {$pageNumber}. ".fake()->paragraph(),
                ]);
            }
        });
    }

    /**
     * Ön yüzde gösterilmeyen ders.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
