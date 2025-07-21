<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventTrackingRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'properties' => 'nullable|array',
            'url' => 'nullable|url',
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
            'name.required' => 'Event name is required.',
            'name.string' => 'Event name must be a string.',
            'name.max' => 'Event name cannot exceed 255 characters.',
            'properties.array' => 'Properties must be an array.',
            'url.url' => 'URL must be a valid URL.',
        ];
    }
}
