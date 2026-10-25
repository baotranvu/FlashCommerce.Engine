<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Http\Controllers;

use FlashCommerce\Identity\Domain\Entities\UserEntity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserProfileController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var UserEntity $user */
        $user = $request->user();

        return response()->json([
            'user' => $user->toArray(),
        ], Response::HTTP_OK);
    }
}
