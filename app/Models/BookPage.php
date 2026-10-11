<?php

namespace App\Models;

use Database\Factories\BookPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ders kitabının PDF'ten çıkarılmış bir sayfası.
 *
 * @property int $id
 * @property int $course_id
 * @property int $page_number PDF içindeki sıra (1'den başlar).
 * @property string $content
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Course $course
 */
#[Fillable(['course_id', 'page_number', 'content'])]
class BookPage extends Model
{
    /** @use HasFactory<BookPageFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
