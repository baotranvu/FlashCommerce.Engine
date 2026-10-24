<?php

namespace FlashCommerce\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'exists:users,email'],
            'otp' => ['required', 'string', 'size:6'],
        ];
    }
}
