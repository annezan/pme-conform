<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fichier' => ['required', 'file', 'mimes:pdf,docx,doc,png,jpg,jpeg,webp,tiff,bmp,gif', 'max:20480'], // 20 Mo max
            'titre' => ['required', 'string', 'max:255'],
            'mission_id' => ['nullable', 'integer', 'exists:missions,id'],
            'type' => ['nullable', 'string', 'in:document_client,modele,autre'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_confidentiel' => ['nullable', 'boolean'],
        ];
    }
}
