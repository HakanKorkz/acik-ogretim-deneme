<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PastExamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MEB'in yayımladığı bir çıkmış sınav kitapçığı (tek oturum).
 *
 * @property int $id
 * @property string $title
 * @property string $period
 * @property int|null $session_number
 * @property string $source_url
 * @property int $sort_order
 * @property ImportStatus|null $status
 * @property string|null $error
 * @property CarbonImmutable|null $parsed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, PastExamSection> $sections
 */
#[Fillable(['title', 'period', 'session_number', 'source_url', 'sort_order', 'status', 'error', 'parsed_at'])]
class PastExam extends Model
{
    /** @use HasFactory<PastExamFactory> */
    use HasFactory;

    /**
     * @return HasMany<PastExamSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(PastExamSection::class)->orderBy('code')->orderBy('name');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'parsed_at' => 'datetime',
        ];
    }
}
