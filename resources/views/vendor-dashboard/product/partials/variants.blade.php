@forelse ($variants as $variant)
    @include('vendor.product.partials.variant', [
        'variant' => $variant,
    ])
@empty
    <div class="text-center text-muted py-4 variant-empty-state">
        <i class="ti ti-box-multiple fs-2 d-block mb-2"></i>
        <span>Variants will appear after saving product attributes.</span>
    </div>
@endforelse
