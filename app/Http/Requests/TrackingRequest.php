<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrackingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'site_id' => 'required|string',
            'url' => 'required|url',
            'referrer' => 'nullable|url',
            'screen_resolution' => 'nullable|string',
            'time_on_page' => 'nullable|integer',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'site_id.required' => 'Site ID is required.',
            'site_id.string' => 'Site ID must be a string.',
            'url.required' => 'URL is required.',
            'url.url' => 'URL must be a valid URL.',
            'referrer.url' => 'Referrer must be a valid URL.',
            'screen_resolution.string' => 'Screen resolution must be a string.',
            'time_on_page.integer' => 'Time on page must be an integer.',
        ];
    }
} 