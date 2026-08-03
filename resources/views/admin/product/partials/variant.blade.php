<div class="accordion-item">
    <div class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#variant-{{ $variant->id }}" aria-expanded="false">
            <span class="attribute-title">{{ $variant->name }}</span>
            @if ($variant->is_active == 1)
                <span class="badge bg-success text-white">active</span>
            @else
                <span class="badge bg-danger text-white">Inactive</span>
            @endif
            <span @class([
                'badge text-white',
                'bg-primary' => $variant->is_default,
                'bg-secondary' => !$variant->is_default,
            ])>
                {{ $variant->is_default ? 'Default' : 'Default' }}
            </span>
            <div class="accordion-button-toggle">
                <!-- Download SVG icon from http://tabler.io/icons/icon/chevron-down -->
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="icon icon-1">
                    <path d="M6 9l6 6l6 -6"></path>
                </svg>
            </div>
        </button>
    </div>
    <div id="variant-{{ $variant->id }}" class="accordion-collapse collapse" data-bs-parent="#accordion-variant">
        <div class="accordion-body">
            <div class="variant-form">
                <div class="row">
                    <div class="col-md-12">
                        <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                        <label for="" class="form-label">Sku</label>
                        <input type="text" class="form-control" value="{{ $variant->sku }}" name="variant_sku">
                    </div>
                    <div class="col-md-6">
                        <label for="" class="form-label">Price</label>
                        <input type="text" class="form-control" value="{{ $variant->price }}" name="variant_price">
                    </div>
                    <div class="col-md-6">
                        <label for="" class="form-label">Special Price</label>
                        <input type="text" class="form-control" value="{{ $variant->special_price }}"
                            name="variant_special_price">
                    </div>

                    <div class="col-md-12">

                        <label class="form-check mt-2">
                            <input class="form-check-input variant-manage-stock" type="checkbox"
                                name="variant_manage_stock" value="1" @checked($variant->manage_stock)>
                            <span class="form-check-label">Manage Stock</span>
                        </label>
                        <div class="variant-quantity" style="display:none">
                            <label for="" class="form-label">Quantity</label>
                            <input type="text" class="form-control" value="{{ $variant->qty }}"
                                name="variant_quantity">
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="card my-3">
                            <div class="card-body">
                                <label for="" class="form-label">Stock Status</label>
                                <div class="d-flex gap-2">
                                    @php
                                        $stockGroup = 'variant_stock_status_' . $variant->id;
                                    @endphp

                                    <label for="variant-{{ $variant->id }}-in-stock" class="form-check">
                                        <input id="variant-{{ $variant->id }}-in-stock" type="radio"
                                            class="form-check-input" name="{{ $stockGroup }}" value="in_stock"
                                            @checked($variant->in_stock === null || (bool) $variant->in_stock)>
                                        <span class="form-check-label">In Stock</span>
                                    </label>

                                    <label for="variant-{{ $variant->id }}-out-of-stock" class="form-check">
                                        <input id="variant-{{ $variant->id }}-out-of-stock" type="radio"
                                            class="form-check-input" name="{{ $stockGroup }}" value="out_of_stock"
                                            @checked($variant->in_stock !== null && !(bool) $variant->in_stock)>
                                        <span class="form-check-label">Out of Stock</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="d-flex gap-2">
                            <label class="form-check form-switch form-switch-3">
                                <input class="form-check-input" type="checkbox" value="1" name="variant_is_default"
                                    @checked($variant->is_default)>
                                <span class="form-check-label">Is Default</span>
                            </label>
                            <label class="form-check form-switch form-switch-3">
                                <input class="form-check-input" type="checkbox" value="1" name="variant_is_active"
                                    @checked($variant->is_active)>
                                <span class="form-check-label">Is Active</span>
                            </label>
                        </div>

                    </div>
                </div>

                <div class="mt-2">
                    <button class="btn btn-md btn-success variant-save-btn" type="button">
                        <i class="ti ti-device-floppy me-1"></i>Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
