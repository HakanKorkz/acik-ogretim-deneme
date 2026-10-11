<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Soru Bankası Büyüklüğü
    |--------------------------------------------------------------------------
    |
    | Her ders için üretilecek toplam soru sayısı (soru bankası). Sınavlar bu
    | bankadan rastgele seçilir; bu yüzden sınav uzunluğundan büyük olmalıdır.
    |
    */

    'questions_per_course' => (int) env('EXAM_QUESTIONS_PER_COURSE', 40),

    /*
    |--------------------------------------------------------------------------
    | Sınav Kuralları
    |--------------------------------------------------------------------------
    |
    | Gerçek AÖL sınavı: her derste 10 soru, geçme notu 100 üzerinden 50.
    | Burada dersler sırayla, her biri kendi süresiyle çözülür; süre bir
    | dersin bölümüne aittir ve dersler arasında işlemez.
    |
    */

    'questions_per_exam' => (int) env('EXAM_QUESTIONS_PER_EXAM', 10),

    'max_courses_per_exam' => 10,

    // Bir dersin bölümü için süre (dakika).
    'section_minutes' => (int) env('EXAM_SECTION_MINUTES', 15),

    'pass_score' => 50,

    // Panelden yüklenebilecek ders kitabı PDF'inin üst sınırı (MB). Sunucuda
    // upload_max_filesize, post_max_size ve web sunucusu sınırı da buna göre olmalı.
    'upload_max_megabytes' => (int) env('EXAM_UPLOAD_MAX_MEGABYTES', 300),

    /*
    |--------------------------------------------------------------------------
    | Kendini Büyüten Soru Bankası
    |--------------------------------------------------------------------------
    |
    | Bir ziyaretçinin bir derste henüz görmediği soru sayısı bu eşiğin altına
    | inince o derse arka planda yeni sorular eklenir (eskiler silinmez).
    | Banka `bank_max_per_course` sınırına ulaşınca büyüme durur.
    |
    */

    'bank_topup_unseen_threshold' => (int) env('EXAM_BANK_TOPUP_THRESHOLD', 20),

    'bank_topup_batch' => (int) env('EXAM_BANK_TOPUP_BATCH', 20),

    'bank_max_per_course' => (int) env('EXAM_BANK_MAX_PER_COURSE', 400),

    /*
    |--------------------------------------------------------------------------
    | Takılı Kalmış Üretimlerin Zaman Aşımı (dakika)
    |--------------------------------------------------------------------------
    |
    | Bu süreden uzun "sırada/üretiliyor" durumunda kalan bir ders yeniden
    | üretime açılır (ör. kuyruk işçisi çöktüyse).
    |
    */

    'generation_stale_after_minutes' => (int) env('EXAM_GENERATION_STALE_AFTER_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Soru Üretim Sürücüsü
    |--------------------------------------------------------------------------
    |
    | `ai`    : Panelde seçilen yapay zekâ sağlayıcısı (OpenRouter, OpenAI,
    |           Anthropic). Anahtar ya da model tanımlı değilse `local`e düşer.
    | `local` : PDF metninden yerel (sezgisel) üretim; harici servis kullanmaz.
    |
    */

    'generator' => env('EXAM_GENERATOR', 'ai'),

    /*
    |--------------------------------------------------------------------------
    | Yapay Zekâ
    |--------------------------------------------------------------------------
    |
    | Sağlayıcı, anahtarlar ve model sırası yönetim panelinden (AiSettings)
    | ayarlanır. Buradaki anahtarlar yalnızca panelde anahtar girilmemişse
    | kullanılan geri düşüş değerleridir.
    |
    */

    'ai' => [
        'keys' => [
            'openrouter' => env('OPENROUTER_API_KEY'),
            'openai' => env('OPENAI_API_KEY'),
            'anthropic' => env('ANTHROPIC_API_KEY'),
        ],
        // Tek bir model denemesinin zaman aşımı (saniye).
        'timeout' => (int) env('EXAM_AI_TIMEOUT', 120),
        // İlk kurulumda panele aktarılan varsayılanlar (sonrası panelden).
        'initial_models' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('OPENROUTER_MODELS', 'openrouter/free')),
        ))),
        'initial_questions_per_request' => (int) env('OPENROUTER_QUESTIONS_PER_REQUEST', 10),
        // Bir isteğe gönderilecek en fazla kaynak metin uzunluğu (karakter).
        'max_input_chars' => (int) env('EXAM_AI_MAX_INPUT_CHARS', 12000),
    ],

];
