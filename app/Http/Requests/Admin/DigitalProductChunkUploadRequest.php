<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Vendor\DigitalProductChunkUploadRequest as VendorChunkUploadRequest;

class DigitalProductChunkUploadRequest extends VendorChunkUploadRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }
}
