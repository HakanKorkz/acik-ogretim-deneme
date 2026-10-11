<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MediaSettingsRequest;
use App\Settings\MediaSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MediaSettingsController extends Controller
{
    /**
     * Depolama ayarları. API anahtarı ve sırrı tarayıcıya gönderilmez.
     */
    public function edit(MediaSettings $settings): Response
    {
        return Inertia::render('admin/settings/media', [
            'settings' => [
                'defaultTarget' => $settings->default_target,
                'cloudName' => $settings->cloudinary_cloud_name,
                'folder' => $settings->cloudinary_folder,
                'maxMegabytes' => $settings->cloudinary_max_megabytes,
                'credentialsSet' => filled($settings->cloudinary_api_key) && filled($settings->cloudinary_api_secret),
            ],
            'uploadMaxMegabytes' => (int) config('exam.upload_max_megabytes'),
        ]);
    }

    public function update(MediaSettingsRequest $request, MediaSettings $settings): RedirectResponse
    {
        if ($request->boolean('clear_credentials')) {
            $settings->cloudinary_api_key = null;
            $settings->cloudinary_api_secret = null;
        }

        // Boş alan "değiştirme" demektir.
        foreach (['cloudinary_api_key', 'cloudinary_api_secret'] as $field) {
            if (filled($request->validated($field))) {
                $settings->{$field} = $request->validated($field);
            }
        }

        $settings->default_target = $request->validated('default_target');
        $settings->cloudinary_cloud_name = $request->validated('cloudinary_cloud_name');
        $settings->cloudinary_folder = $request->validated('cloudinary_folder');
        $settings->cloudinary_max_megabytes = (int) $request->validated('cloudinary_max_megabytes');
        $settings->save();

        return to_route('admin.settings.media.edit')->with('success', 'Depolama ayarları kaydedildi.');
    }
}
