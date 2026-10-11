<?php

namespace App\Http\Controllers\Admin;

use App\Ai\Agents\QuestionWriter;
use App\Enums\AiProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiSettingsRequest;
use App\Services\Ai\AiModelChain;
use App\Settings\AiSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AiSettingsController extends Controller
{
    /**
     * Yapay zekâ ayarları. Anahtarlar ASLA tarayıcıya gönderilmez; yalnızca
     * nereden geldiği (panel / .env / yok) bildirilir.
     */
    public function edit(AiSettings $settings): Response
    {
        return Inertia::render('admin/settings/ai', [
            'settings' => [
                'provider' => $settings->activeProvider()->value,
                'models' => $settings->models,
                'questionsPerRequest' => $settings->questions_per_request,
            ],
            'providers' => array_map(static fn (AiProvider $provider): array => [
                'value' => $provider->value,
                'label' => $provider->label(),
                'keySource' => match (true) {
                    $settings->hasPanelKey($provider) => 'panel',
                    $settings->keyFor($provider) !== null => 'env',
                    default => 'none',
                },
                'suggestedModels' => $provider->suggestedModels(),
            ], AiProvider::cases()),
            'generator' => config('exam.generator'),
        ]);
    }

    public function update(AiSettingsRequest $request, AiSettings $settings): RedirectResponse
    {
        /** @var list<string> $clearKeys */
        $clearKeys = $request->validated('clear_keys', []);

        foreach (AiProvider::cases() as $provider) {
            $property = "{$provider->value}_key";
            $typed = $request->validated($property);

            // Boş alan "değiştirme" demektir; silmek için ayrı işaret gerekir.
            if (in_array($provider->value, $clearKeys, true)) {
                $settings->{$property} = null;
            } elseif (filled($typed)) {
                $settings->{$property} = $typed;
            }
        }

        /** @var list<string> $models */
        $models = array_values($request->validated('models'));

        $settings->provider = $request->validated('provider');
        $settings->models = $models;
        $settings->questions_per_request = (int) $request->validated('questions_per_request');
        $settings->save();

        return to_route('admin.settings.ai.edit')->with('success', 'Yapay zekâ ayarları kaydedildi.');
    }

    /**
     * Kayıtlı ayarlarla küçük bir deneme isteği gönderir ve ilk yanıt veren
     * modeli bildirir.
     */
    public function test(AiSettings $settings, AiModelChain $chain): RedirectResponse
    {
        $provider = $settings->activeProvider();

        if ($settings->keyFor($provider) === null) {
            return back()->with('error', "{$provider->label()} için anahtar tanımlı değil.");
        }

        $agent = new QuestionWriter(
            'Türkiye Büyük Millet Meclisi 23 Nisan 1920 tarihinde Ankara\'da açıldı.',
            1,
        );
        $model = $chain->run(
            $agent,
            $agent->payload(),
            static fn (array $output, string $model): ?string => is_array($output['questions'] ?? null) && $output['questions'] !== [] ? $model : null,
            timeout: 60,
        );

        return $model !== null
            ? back()->with('success', "Bağlantı başarılı. İlk yanıt veren model: {$model}")
            : back()->with('error', 'Hiçbir model geçerli yanıt vermedi. Ayrıntılar uygulama günlüğünde.');
    }
}
