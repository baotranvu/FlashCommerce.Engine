<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Http\Middleware;

use Closure;
use FlashCommerce\Identity\Domain\Entities\UserEntity;
use FlashCommerce\Identity\Domain\Exceptions\AccountUnverifiedException;
use FlashCommerce\Identity\Domain\Exceptions\InvalidTokenException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerifiedFastPath
{
    private const FAST_PATH_PREFIX = 'user_verified:';
    private const FAST_PATH_TTL = 86400; // 24 hours

    public function handle(Request $request, Closure $next): Response
    {
        /** @var UserEntity|null $user */
        $user = $request->user();

        if ($user === null) {
            throw new InvalidTokenException('User is unauthenticated.');
        }

        $cacheKey = self::FAST_PATH_PREFIX . $user->id;

        // 🚀 Fast-Path: 0.2ms Redis RAM check
        if (Redis::exists($cacheKey)) {
            return $next($request);
        }

        // 🛡️ Fallback: Check persistence state & warm up Redis cache
        if ($user->isVerified()) {
            Redis::setex($cacheKey, self::FAST_PATH_TTL, '1');
            return $next($request);
        }

        throw new AccountUnverifiedException();
    }
}
