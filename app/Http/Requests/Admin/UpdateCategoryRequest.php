<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Services\CategoryTreeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($this->route('id')),
            ],
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

            $category = Category::query()->find($this->route('id'));

            if (! $category) {
                return;
            }

            $message = app(CategoryTreeService::class)->parentValidationMessage($this->parentId(), $category);

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
