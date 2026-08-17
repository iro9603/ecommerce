<?php

namespace App\Http\Requests\Vendor;

use App\Models\Product;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DigitalProductChunkUploadRequest extends FormRequest
{
    /**
     * Extensions are only a first-pass constraint. The assembled file must
     * still be inspected by MIME/content before being made available.
     */
    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'tif', 'tiff',
        'pdf', 'txt', 'csv', 'rtf',
        'docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp', 'epub',
        'mp3', 'wav', 'ogg', 'flac', 'm4a', 'aac',
        'mp4', 'webm', 'ogv', 'mov',
        'zip', '7z',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user?->can('create', Product::class) !== true) {
            return false;
        }

        $productId = filter_var(
            $this->input('product_id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($productId === false) {
            return true;
        }

        $product = Product::query()->find($productId);

        // Leave a missing ID to the exists rule, while denying an existing
        // product that belongs to another vendor.
        return $product === null
            || $user->can('manageDigitalFiles', $product);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxChunkSize = (int) config('products.digital_upload.max_chunk_size_kb', 10240) * 1024;
        $maxFileSize = (int) config('products.digital_upload.max_file_size_kb', 262144) * 1024;
        $maxChunks = (int) config('products.digital_upload.max_chunks', 4096);

        return [
            'product_id' => ['required', 'integer', 'min:1', 'exists:products,id'],
            'file' => ['required', 'file', 'max:'.(int) ceil($maxChunkSize / 1024)],
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! $this->isSafeFileName($value)) {
                        $fail('The file name is invalid.');
                    }
                },
            ],
            'dzuuid' => ['required', 'uuid'],
            'dzchunkindex' => ['required', 'integer', 'min:0', 'lt:dztotalchunkcount'],
            'dztotalchunkcount' => ['required', 'integer', 'min:1', 'max:'.$maxChunks],
            'dzchunksize' => ['required', 'integer', 'min:1', 'max:'.$maxChunkSize],
            'dztotalfilesize' => ['required', 'integer', 'min:1', 'max:'.$maxFileSize],
            'dzchunkbyteoffset' => ['required', 'integer', 'min:0', 'lt:dztotalfilesize'],

            'store' => ['prohibited'],
            'store_id' => ['prohibited'],
            'approved_status' => ['prohibited'],
            'is_featured' => ['prohibited'],
            'featured' => ['prohibited'],
            'is_hot' => ['prohibited'],
            'hot' => ['prohibited'],
            'is_new' => ['prohibited'],
            'new' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ([
                'file',
                'dzchunkindex',
                'dztotalchunkcount',
                'dzchunksize',
                'dztotalfilesize',
                'dzchunkbyteoffset',
            ] as $attribute) {
                if ($validator->errors()->has($attribute)) {
                    return;
                }
            }

            $index = (int) $this->input('dzchunkindex');
            $chunkCount = (int) $this->input('dztotalchunkcount');
            $chunkSize = (int) $this->input('dzchunksize');
            $totalSize = (int) $this->input('dztotalfilesize');
            $offset = (int) $this->input('dzchunkbyteoffset');

            if ((int) ceil($totalSize / $chunkSize) !== $chunkCount) {
                $validator->errors()->add(
                    'dztotalchunkcount',
                    'The chunk count does not match the declared file size.',
                );
            }

            $expectedOffset = $index * $chunkSize;

            if ($offset !== $expectedOffset) {
                $validator->errors()->add(
                    'dzchunkbyteoffset',
                    'The chunk offset does not match its index.',
                );
            }

            $expectedBytes = min($chunkSize, $totalSize - $expectedOffset);
            $actualBytes = $this->file('file')?->getSize();

            if ($expectedBytes < 1 || $actualBytes !== $expectedBytes) {
                $validator->errors()->add(
                    'file',
                    'The uploaded chunk size does not match its metadata.',
                );
            }
        });
    }

    public function chunkStorageKey(): string
    {
        return strtolower((string) $this->validated('dzuuid'));
    }

    private function isSafeFileName(string $name): bool
    {
        if ($name !== trim($name) || preg_match('/^[.]+$/', $name) === 1) {
            return false;
        }

        if (
            str_contains($name, '/')
            || str_contains($name, '\\')
            || preg_match('/[\x00-\x1F\x7F]/u', $name) === 1
        ) {
            return false;
        }

        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        return in_array($extension, self::ALLOWED_EXTENSIONS, true);
    }
}
