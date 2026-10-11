<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CourseLevel;
use App\Http\Controllers\Controller;
use App\Services\Catalog\CatalogSynchronizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class CatalogSyncController extends Controller
{
    /**
     * Kademenin MEB kataloğunu eşitler. Yalnızca katalog sayfası okunur,
     * PDF indirilmez; birkaç saniye sürer.
     */
    public function store(Request $request, CatalogSynchronizer $synchronizer): RedirectResponse
    {
        $level = CourseLevel::from($request->validate([
            'level' => ['required', Rule::enum(CourseLevel::class)],
        ])['level']);

        try {
            $summary = $synchronizer->sync($level);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', "Katalog alınamadı: {$exception->getMessage()}");
        }

        return back()->with('success', sprintf(
            '%s kataloğu eşitlendi: %d yeni, %d güncellendi, %d değişmedi.',
            $level->label(),
            $summary['created'],
            $summary['updated'],
            $summary['unchanged'],
        ));
    }
}
