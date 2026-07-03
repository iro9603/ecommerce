<?php

namespace App\Http\Requests\Admin;

use App\Services\CategoryTreeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCategoryOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tree' => ['required', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('tree') || ! is_array($this->input('tree'))) {
                return;
            }

            $message = app(CategoryTreeService::class)->orderTreeValidationMessage($this->input('tree'));

            if ($message) {
                $validator->errors()->add('tree', $message);
            }
        });
    }
}
