<?php

use Illuminate\Support\Facades\Schedule;

/*
 * MEB kataloğu haftada bir eşitlenir; ardından metni çıkarılmış kitapların
 * PDF'i değişmiş mi diye bakılır (yalnızca HEAD isteği).
 */
Schedule::command('catalog:sync')->weeklyOn(1, '03:00')->withoutOverlapping();
Schedule::command('catalog:check-updates')->weeklyOn(1, '03:30')->withoutOverlapping();
