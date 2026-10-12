<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Testler yalnızca `*_testing` ile biten bir veritabanında çalışabilir.
     *
     * `phpunit.xml` içindeki DB_DATABASE yalnızca config ÖNBELLEĞE ALINMAMIŞKEN
     * geçerlidir. Config önbellekteyse testler yanlışlıkla geliştirme
     * veritabanında koşar ve RefreshDatabase onu sıfırlar. Bu koruma, o
     * durumda `RefreshDatabase` devreye girmeden önce testleri durdurur.
     *
     * `setUpTraits()` içinde denetlenir çünkü uygulama (dolayısıyla `config()`)
     * bu noktada hazırdır ama veritabanı henüz tazelenmemiştir.
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits()
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');

        if (! str_ends_with($database, '_testing')) {
            throw new RuntimeException(
                "Testler yalnızca `*_testing` veritabanında çalışabilir; etkin veritabanı: [{$database}]. "
                .'Config önbelleğe alınmış olabilir; `php artisan config:clear` çalıştırıp yeniden deneyin.',
            );
        }

        return parent::setUpTraits();
    }
}
