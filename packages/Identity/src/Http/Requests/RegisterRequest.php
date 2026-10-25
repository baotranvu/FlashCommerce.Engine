<?php

namespace FlashCommerce\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:100'],
            // Uniqueness is enforced at the domain/DB index layer to eliminate TOCTOU race conditions and save 1 SELECT query
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }
}
