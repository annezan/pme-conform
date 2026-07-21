<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'integer', 'exists:agents,id'],
            'type_document' => ['required', 'string', 'in:rapport_audit,politique,registre,aipd,courrier_artci,charte,autre'],
            'contexte' => ['required', 'string', 'max:20000'],
            'mission_id' => ['nullable', 'integer', 'exists:missions,id'],
        ];
    }
}
