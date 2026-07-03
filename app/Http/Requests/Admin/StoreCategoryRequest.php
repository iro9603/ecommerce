<?php

namespace App\Http\Requests\Admin;

use App\Services\CategoryTreeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:categories,slug'],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug') ?: $this->input('name');

        if (is_string($slug)) {
            $this->merge([
                'slug' => Str::slug($slug),
            ]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('parent_id')) {
                return;
            }

            $message = app(CategoryTreeService::class)->parentValidationMessage($this->parentId());

            if ($message) {
                $validator->errors()->add('parent_id', $message);
            }
        });
    }

    private function parentId(): ?int
    {
        return $this->filled('parent_id') ? (int) $this->input('parent_id') : null;
    }
}
