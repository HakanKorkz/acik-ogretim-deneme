# Açık Öğretim Deneme

Açık Öğretim Lisesi ve Açık Öğretim Ortaokulu adayları için, gerçek sınav düzeninde çevrim içi deneme sınavı uygulaması.

Sorular iki kaynaktan gelir:

- **MEB ders kitapları:** [aokul.gov.tr](http://aokul.gov.tr/Kitaplar/Kitaplar.aspx) kataloğundaki kitapların, her dersin sorumlu olduğu sayfa aralığından yapay zekâyla üretilir.
- **Çıkmış sınavlar:** [MEB'in yayımladığı kitapçıklardan](https://aol.meb.gov.tr/www/cikmis-sinav-sorulari/icerik/15) alınır; doğru şıklar kitapçığın cevap anahtarındandır.

## Özellikler

### Aday tarafı (giriş gerekmez)

- Kademe seçimi (lise / ortaokul), ardından **1–10 ders** seçimi.
- Soru türü: yalnızca çıkmış sorular, yapay zekâ destekli sorular ya da karışık.
- Sonuç gösterimi: her ders bitince o dersin sonucu ya da en sonda toplu sonuç.
- Gerçek sınav düzeni:
    - Dersler sırayla çözülür, birbirine karışmaz; her derste 10 soru vardır.
    - Her dersin kendi süresi vardır (varsayılan 15 dk). Süre sunucuda tutulur; dolunca ders kendiliğinden teslim edilir.
    - Sınav "Adayların dikkatine" ekranı ve **BAŞLAYINIZ** düğmesiyle başlar.
    - Sorular iki sütunlu kitapçık görünümünde, yanında optik cevap kâğıdıyla gösterilir.
    - Olumsuz kökler ("…değildir?") kitapçıktaki gibi vurgulanır.
    - Cevaplar işaretlendiği anda kaydedilir; sayfa yenilense de kaybolmaz.
- Puan 100 üzerindendir, geçme notu 50'dir. Sonuçta cevap anahtarı ve sorunun kitaptaki sayfasına bağlantı bulunur.
- Her sınav öncekinden bağımsızdır:
    - Ziyaretçinin daha önce gördüğü sorular sona bırakılır (anonim, hesap gerektirmeyen tarayıcı kimliği).
    - Şık sırası her sınavda yeniden karıştırılır.
    - Görülmemiş soru azalınca derse arka planda yeni sorular eklenir.

### Yönetim paneli (`/admin`)

- **Dersler:**
    - MEB kataloğunu eşitleme.
    - Kitap metnini hazırlama, soru bankası üretme.
    - Sorumluluk aralığını ve sayfa kaydırmasını düzenleme (önizlemeli).
    - Yayına alma.
- **PDF ile ders ekleme:** Katalogda olmayan kitaplar için. Dosya sunucuda ya da Cloudinary'de saklanır.
- **Çıkmış sorular:** MEB kitapçık listesini eşitleme, kitapçık içe aktarma, bölüm bazında durum ve yeniden deneme.
- **Yapay zekâ:** OpenRouter, OpenAI ya da Anthropic seçimi; sıralı model listesi; şifreli anahtarlar; bağlantı testi.
- **Depolama:** Cloudinary bilgileri (şifreli).
- **Profil:** Ad, e-posta, şifre.

## Nasıl çalışır?

- **Kitaplar barındırılmaz.** MEB kataloğundaki PDF'ler geçici olarak indirilir, metni sayfa sayfa `book_pages` tablosuna yazılır ve dosya silinir. Açıklamadaki "83. sayfasına kadar" gibi sorumluluk aralıkları otomatik okunur. Basılı sayfa numarası ile PDF sırası arasındaki fark (kapak, ön sayfalar) tespit edilir.
- **Soru üretimi:** Kitap bölümlere ayrılır, her bölüm için ayrı istek gönderilir. İstem, gerçek kitapçıklardaki dağılıma göre hazırlanmıştır: metne dayalı, olumsuz kökü olan ve öncüllü sorular. Dersin çıkmış soruları üslup örneği olarak verilir. Anlamsız sorular (öncülü olmayan öncül sorusu, metinsiz "metne göre" vb.) bir kalite denetimiyle elenir. Yapay zekâ yanıt vermezse yerel (kural tabanlı) üreticiye düşülür.
- **Çıkmış sorular:** Kitapçık ders bölümlerine ayrılır. Cevap anahtarı, kelimelerin sayfadaki koordinatlarına göre (`pdftotext -bbox`) okunur; okunamayan anahtar atılır, hiçbir derse tahmini anahtar atanmaz. Kitapçıktaki ders adları katalogla eşlenir. Soru metinleri yapay zekâyla yapılandırılır; görsel gerektiren sorular atlanır.

## Gereksinimler

- PHP 8.3+ (geliştirmede 8.5), Composer
- MySQL 8
- Node.js 22+
- **poppler-utils** (`pdftotext`): Kitap metni ve çıkmış sınav cevap anahtarı için gereklidir. Yoksa metin yedek ayrıştırıcıyla çıkarılır, ama çıkmış sınav kitapçıkları işlenemez.
- Kuyruk işçisi ve zamanlayıcı (aşağıya bakın)

## Kurulum

```bash
git clone https://github.com/HakanKorkz/acik-ogretim-deneme.git
cd acik-ogretim-deneme
cp .env.example .env            # DB_* bilgilerini düzenleyin
composer install
php artisan key:generate
php artisan migrate
npm install && npm run build

php artisan admin:create        # yönetici hesabı (kayıt sayfası yoktur)
php artisan catalog:sync        # MEB ders kataloğu (PDF indirmez)
php artisan past-exams:sync     # çıkmış sınav kitapçıkları listesi
```

Ardından:

1. Panelde **Yapay Zekâ** sayfasından sağlayıcıyı ve anahtarı girin.
2. **Dersler** sayfasından istediğiniz dersler için "Soruları üret"e basın; kitap metni gerekirse önce otomatik hazırlanır.
3. Dersleri yayına alın.

Geliştirme için `composer run dev` sunucuyu, kuyruğu ve Vite'ı birlikte başlatır.

## Çalışma zamanı

Uzun işler (kitap indirme, soru üretimi, kitapçık aktarımı) kuyrukta çalışır:

```bash
php artisan queue:work --timeout=900
```

`config/queue.php` içindeki `retry_after` (varsayılan 960 sn) iş zaman aşımından büyük olmalıdır.

Zamanlanmış görevler için cron'a şu satır eklenir:

```cron
* * * * * cd /yol/proje && php artisan schedule:run >> /dev/null 2>&1
```

| Görev                                                                                         | Zaman           |
| --------------------------------------------------------------------------------------------- | --------------- |
| `catalog:sync` — MEB kataloğu                                                                 | Pazartesi 03:00 |
| `catalog:check-updates` — metni çıkarılmış kitapların PDF'i değişti mi (yalnızca HEAD isteği) | Pazartesi 03:30 |
| `past-exams:sync` — çıkmış sınav listesi                                                      | Pazartesi 04:00 |

### Yükleme sınırı

PDF yükleme sınırı `EXAM_UPLOAD_MAX_MEGABYTES` ile ayarlanır (varsayılan 300 MB). Sunucuda `upload_max_filesize`, `post_max_size` ve web sunucusunun gövde sınırı (ör. nginx `client_max_body_size`) da buna uygun olmalıdır.

### Cloudinary

Ücretsiz planda dosya başına sınır yaklaşık **10 MB**'tır; daha büyük kitaplar otomatik olarak sunucuya kaydedilir. PDF'lerin dışarıya sunulabilmesi için Cloudinary hesabının _Security_ ayarlarında **"Allow delivery of PDF and ZIP files"** açılmalıdır. Uygulama Cloudinary'nin imzalı REST API'sini doğrudan kullanır; ek paket gerekmez.

## Yayına alırken (canlı ortam)

### Sunucu

- **poppler-utils** (`pdftotext`) kurulu olmalı. Kitap metni ve çıkmış sınav cevap anahtarı buna bağlı.
- PHP eklentileri ve `storage/`, `bootstrap/cache/` yazma izinleri (web kullanıcısı, ör. `www-data`).
- Yükleme sınırı en az `EXAM_UPLOAD_MAX_MEGABYTES` kadar olmalı: `upload_max_filesize`, `post_max_size` (php.ini) ve nginx `client_max_body_size`.

### Ortam dosyası

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://alanadiniz
```

Veritabanı, kuyruk (`QUEUE_CONNECTION=database` ya da `redis`), önbellek ve posta ayarlarını canlı değerlere çevirin. Ayrıntılar için `.env.example`'a bakın.

### Dağıtım adımları

```bash
composer install --no-dev --optimize-autoloader
php artisan down --render="errors::503"        # isteğe bağlı, kısa kesinti
php artisan migrate --force
npm ci && npm run build
php artisan optimize                           # config + event + route + view önbelleği
php artisan queue:restart                      # kuyruk işçileri yeni kodu alsın
php artisan up
```

> **Uyarı:** `php artisan optimize` (config önbelleği) çalıştırıldığında `.env` artık okunmaz ve yalnızca `config/` dosyalarındaki `env()` çağrıları geçerli olur. Kodda config dışında `env()` kullanmayın.

### Kuyruk işçisi (Supervisor)

Kuyruk işçisi kalıcı bir süreçtir; çökerse yeniden başlatılması için Supervisor gerekir. `/etc/supervisor/conf.d/` altına:

```ini
[program:acik-ogretim-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/acik-ogretim-deneme/artisan queue:work --sleep=3 --tries=1 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/acik-ogretim-deneme/storage/logs/worker.log
stopwaitsecs=960
```

- `stopwaitsecs`, en uzun işten **büyük** olmalı (bu projede iş zaman aşımı 900 sn, `retry_after` 960 sn). `stopwaitsecs=960` bu yüzden.
- `config/queue.php` içindeki `retry_after` (960) her zaman `queue:work --timeout` değerinden birkaç saniye uzun olmalı; değilse işler iki kez işlenebilir.

### Zamanlayıcı

```cron
* * * * * cd /var/www/acik-ogretim-deneme && php artisan schedule:run >> /dev/null 2>&1
```

### İlk kurulum sonrası

```bash
php artisan admin:create      # yönetici hesabı
php artisan catalog:sync      # MEB ders kataloğu
php artisan past-exams:sync   # çıkmış sınav listesi
```

Ardından panelden yapay zekâ sağlayıcısını ve (kullanılacaksa) Cloudinary bilgilerini girin; dersleri yayına almadan önce soru bankalarını üretin.

## Komutlar

| Komut                                                                             | Açıklama                                                       |
| --------------------------------------------------------------------------------- | -------------------------------------------------------------- |
| `php artisan admin:create`                                                        | Yönetici oluşturur ya da mevcut kullanıcıyı yönetici yapar     |
| `php artisan catalog:sync [lise\|ortaokul]`                                       | MEB ders kataloğunu eşitler                                    |
| `php artisan catalog:check-updates`                                               | Değişen kitapları işaretler                                    |
| `php artisan questions:generate [ders] [--count=] [--driver=ai\|local] [--force]` | Soru bankası üretir (ders verilmezse tüm aktif dersler)        |
| `php artisan past-exams:sync`                                                     | Çıkmış sınav listesini eşitler                                 |
| `php artisan past-exams:import [--latest=3] [--id=*]`                             | Kitapçıkları kuyrukta içe aktarır (yalnızca yayındaki dersler) |

## Önemli ortam değişkenleri

| Değişken                                                    | Varsayılan | Açıklama                                                 |
| ----------------------------------------------------------- | ---------- | -------------------------------------------------------- |
| `EXAM_QUESTIONS_PER_COURSE`                                 | 40         | Ders başına üretilen soru bankası                        |
| `EXAM_QUESTIONS_PER_EXAM`                                   | 10         | Sınavda ders başına soru (gerçek AÖL: 10)                |
| `EXAM_SECTION_MINUTES`                                      | 15         | Bir dersin süresi                                        |
| `EXAM_BANK_TOPUP_THRESHOLD`                                 | 20         | Görülmemiş soru bunun altına inince bankaya soru eklenir |
| `EXAM_BANK_MAX_PER_COURSE`                                  | 400        | Ders başına banka üst sınırı                             |
| `EXAM_GENERATOR`                                            | `ai`       | `ai` ya da `local`                                       |
| `OPENROUTER_API_KEY`, `OPENAI_API_KEY`, `ANTHROPIC_API_KEY` | —          | Panelde anahtar yoksa kullanılır                         |

## Testler

```bash
php artisan test --compact     # MySQL'de acik_ogretim_deneme_testing veritabanını kullanır
vendor/bin/phpstan analyse
vendor/bin/pint
npm run check && npm run types:check
```

Testler gerçek MEB sayfalarından ve bir kitapçıktan alınmış fixture'larla (`tests/Fixtures`) çalışır. Yapay zekâ çağrıları taklit edilir; testler ağa çıkmaz.

## Teknolojiler

- Laravel 13, Inertia v3, React 19, Tailwind CSS 4, Wayfinder
- `laravel/ai`, `laravel/fortify`, `spatie/laravel-settings`
- Pest, Larastan

## Veri kaynakları

Ders kitapları ve çıkmış sınav soruları T.C. Millî Eğitim Bakanlığı tarafından herkese açık olarak yayımlanmaktadır. Uygulama kitapları barındırmaz; kaynağa bağlantı verir ve çıkmış soruları kaynağını belirterek gösterir.
