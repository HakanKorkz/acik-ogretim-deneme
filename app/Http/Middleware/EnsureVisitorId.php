<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Her tarayıcıya hesap gerektirmeyen, anonim ve uzun ömürlü bir kimlik verir.
 *
 * Bu kimlik iki iş görür: devam eden sınavın sahibini tanır (oturum
 * süresinden bağımsız) ve yeni sınavlarda ziyaretçinin daha önce gördüğü
 * soruları sona bırakmayı sağlar. Kişisel veri içermez.
 */
class EnsureVisitorId
{
    public const COOKIE = 'visitor_id';

    public const ATTRIBUTE = 'visitor_id';

    /**
     * Çerezin ömrü (dakika): 2 yıl.
     */
    private const LIFETIME_MINUTES = 60 * 24 * 365 * 2;

    public function handle(Request $request, Closure $next): Response
    {
        $visitorId = $request->cookie(self::COOKIE);
        $isNew = ! is_string($visitorId) || ! Str::isUlid($visitorId);

        if ($isNew) {
            $visitorId = (string) Str::ulid();
        }

        $request->attributes->set(self::ATTRIBUTE, $visitorId);

        $response = $next($request);

        if ($isNew) {
            $response->headers->setCookie(Cookie::make(self::COOKIE, $visitorId, self::LIFETIME_MINUTES));
        }

        return $response;
    }

    /**
     * İsteğin ziyaretçi kimliği.
     */
    public static function of(Request $request): string
    {
        return (string) $request->attributes->get(self::ATTRIBUTE);
    }
}
