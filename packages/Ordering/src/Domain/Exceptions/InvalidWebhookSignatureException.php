<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Domain\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class InvalidWebhookSignatureException extends InvalidArgumentException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'status'  => 'unauthorized',
            'message' => $this->getMessage(),
        ], 401);
    }
}
