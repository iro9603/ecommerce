<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreAutoApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::guard('admin')->user()
            ?->can('Store Auto-Approval Management') === true;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'status' => ['prohibited'],
            'seller_id' => ['prohibited'],
            'approved_at' => ['prohibited'],
            'suspended_at' => ['prohibited'],
        ];
    }
}
