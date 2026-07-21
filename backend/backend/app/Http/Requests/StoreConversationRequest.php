<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'integer', 'exists:agents,id'],
            'message' => ['required', 'string', 'max:10000'],
            'mission_id' => ['nullable', 'integer', 'exists:missions,id'],
        ];
    }
}
