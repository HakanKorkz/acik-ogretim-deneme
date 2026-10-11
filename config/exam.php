<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PDF Kaynak Dizini
    |--------------------------------------------------------------------------
    |
    | Ders PDF dosyalarının yer aldığı dizin. `questions:generate` komutu ve
    | PDF ayrıştırma servisi varsayılan olarak bu dizini tarar.
    |
    */

    'pdf_path' => public_path('dersler'),

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
    | Bir Sınavdaki Soru Sayısı
    |--------------------------------------------------------------------------
    |
    | Her sınav oturumunda bankadan rastgele seçilecek soru sayısı.
    |
    */

    'questions_per_exam' => (int) env('EXAM_QUESTIONS_PER_EXAM', 20),

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
    | PDF İçin Maksimum Sayfa Sayısı
    |--------------------------------------------------------------------------
    |
    | Çok büyük PDF dosyalarında performans için işlenecek maksimum sayfa
    | sayısı. `null` verilirse tüm sayfalar işlenir.
    |
    */

    'pdf_max_pages' => env('EXAM_PDF_MAX_PAGES') !== null ? (int) env('EXAM_PDF_MAX_PAGES') : null,

    /*
    |--------------------------------------------------------------------------
    | Soru Üretim Sürücüsü
    |--------------------------------------------------------------------------
    |
    | `local`      : PDF metninden yerel (sezgisel) üretim.
    | `openrouter` : OpenRouter üzerinden yapay zekâ ile üretim. API anahtarı
    |                tanımlı değilse otomatik olarak `local`a düşer.
    |
    */

    'generator' => env('EXAM_GENERATOR', 'local'),

    /*
    |--------------------------------------------------------------------------
    | OpenRouter Ayarları
    |--------------------------------------------------------------------------
    |
    | OpenAI uyumlu chat completions API'si kullanılır.
    |
    */

    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
        // Tek model ya da "openrouter/free" yönlendiricisi kullanılabilir.
        'model' => env('OPENROUTER_MODEL', 'openrouter/free'),
        // Virgülle ayrılmış yedek model zinciri (doluysa `model` yerine bu
        // gönderilir; OpenRouter sırayla dener). Ör. bir sağlayıcı 429 verirse
        // otomatik olarak sonrakine geçer.
        'models' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('OPENROUTER_MODELS', '')),
        ))),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'connect_timeout' => (int) env('OPENROUTER_CONNECT_TIMEOUT', 10),
        'timeout' => (int) env('OPENROUTER_TIMEOUT', 90),
        'max_input_chars' => (int) env('OPENROUTER_MAX_INPUT_CHARS', 12000),
        // Tek istekte istenecek soru sayısı. Banka bu boyutta parçalara
        // bölünür ve her parça, PDF'in farklı bir bölümünden üretilir.
        'questions_per_request' => (int) env('OPENROUTER_QUESTIONS_PER_REQUEST', 10),
        'max_tokens' => (int) env('OPENROUTER_MAX_TOKENS', 4096),
        'temperature' => (float) env('OPENROUTER_TEMPERATURE', 0.4),
        // Muhakeme (reasoning) modellerinin cevabı "düşünmeye" harcayıp JSON'u
        // üretememesini önlemek için varsayılan olarak kapatılır.
        'allow_reasoning' => (bool) env('OPENROUTER_ALLOW_REASONING', false),
        // Modelin yapılandırılmış JSON döndürmesini ister (destekleyen modellerde).
        'json_mode' => (bool) env('OPENROUTER_JSON_MODE', true),
    ],

];
