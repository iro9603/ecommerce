<div class="accordion-item" data-attribute-id="{{ $attribute->id }}">
    <div class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#collapse-{{ $attribute->id }}" aria-expanded="false">
            <span class="attribute-title">{{ $attribute->name }}</span>
            <div class="accordion-button-toggle">
                <!-- Download SVG icon from http://tabler.io/icons/icon/chevron-down -->
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="icon icon-1">
                    <path d="M6 9l6 6l6 -6"></path>
                </svg>
            </div>
        </button>
        <span class="delete-btn btn btn-danger" style="padding: 5px; margin-right:10px"><i
                class="ti ti-trash"></i></span>
    </div>
    <div id="collapse-{{ $attribute->id }}" class="accordion-collapse collapse" data-bs-parent="#accordion-default">
        <div class="accordion-body">
            <input type="hidden" name="attribute_id" value="{{ $attribute->id }}">
            <div class="row">
                <div class="col-md-6">
                    <label for="" class="form-label">Name</label>
                    <input type="text" class="form-control" value="{{ $attribute->name }}" name="attribute_name">
                    <input type="hidden" value="{{ $attribute->id }}" name="attribute_id">
                </div>
                <div class="col-md-6">
                    <label for="" class="form-label">Type</label>
                    <select name="attribute_type" class="form-control main-type">
                        <option value="text" @selected($attribute->type == 'text')>Text</option>
                        <option value="color" @selected($attribute->type == 'color')>Color</option>
                    </select>
                </div>
            </div>
            <table class="table table-bordered section-table attribute-values-table mt-3"
                style="{{ count($attribute->values) ? '' : 'display:none;' }}">
                <thead>
                    <tr>
                        <th>Label</th>
                        <th class="value-header">{{ $attribute->type === 'color' ? 'Color' : 'Value' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($attribute->type == 'color')
                        @foreach ($attribute->values as $value)
                            @php($displayColor = $value->color ?: '#000000')
                            <tr>
                                <td>
                                    <input type="hidden" class="attribute-value-id" name="value_id[]"
                                        value="{{ $value->id }}">
                                    <input type="text" name="label[]" class="form-control label-input"
                                        value="{{ $value->value }}" placeholder="e.g. Ocean blue">
                                </td>
                                <td>
                                    <div class="attribute-color-control">
                                        <div class="color-picker-wrap">
                                            <div id="pickr-{{ $value->id }}" class="color-preview"
                                                data-color="{{ $displayColor }}"
                                                style="background-color: {{ $displayColor }}">
                                            </div>
                                        </div>
                                        <input type="hidden" class="color-value"
                                            data-picker-id="pickr-{{ $value->id }}" name="color_value[]"
                                            value="{{ $displayColor }}">
                                        <div class="color-details">
                                            <span class="color-value-display">{{ strtoupper($displayColor) }}</span>
                                            <span class="color-help">Click the swatch to change it</span>
                                        </div>
                                        <button type="button"
                                            class="btn btn-outline-danger review-row-btn remove-attribute-row"
                                            aria-label="Remove color">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        @foreach ($attribute->values as $value)
                            <tr>
                                <td colspan="2">
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="hidden" class="attribute-value-id" name="value_id[]"
                                            value="{{ $value->id }}">
                                        <input type="text" class="form-control label-input" name="label[]"
                                            placeholder="Label" value="{{ $value->value }}">
                                        <button type="button"
                                            class="btn btn-outline-danger review-row-btn remove-attribute-row"
                                            aria-label="Remove value">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
            <div class="mt-2">
                <button class="btn btn-sm btn-primary add-row-btn" type="button">
                    <i class="ti ti-plus me-1"></i>Add Row
                </button>
                <button class="btn btn-sm btn-success save-btn" type="button">
                    <i class="ti ti-device-floppy me-1"></i>Save
                </button>
            </div>
        </div>
    </div>
</div>
