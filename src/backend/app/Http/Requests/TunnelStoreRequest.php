<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TunnelStoreRequest extends FormRequest
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
            'name' => 'required|string|max:100|unique:tunnels,name',
            'custom_subdomain' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9-]+$/',
            ],
            'port' => [
                'required',
                'integer',
                'between:1,65535',
                'unique:tunnels,port',
                function ($attribute, $value, $fail) {
                    if (app()->environment('testing') || $this->boolean('skip_port_check')) {
                        return;
                    }
                    $connection = @fsockopen('127.0.0.1', $value, $errno, $errstr, 0.2);
                    if (is_resource($connection)) {
                        fclose($connection);
                    }
                }
            ],
        ];
    }
}
