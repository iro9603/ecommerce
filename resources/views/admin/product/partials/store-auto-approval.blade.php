<div class="card mb-3" id="store-auto-approval-card"
    data-endpoint="{{ route('admin.stores.product-auto-approval.update', $store) }}"
    data-enabled="{{ $store->auto_approve_products ? '1' : '0' }}">
    <div class="card-header">
        <h3 class="card-title">Store Auto-Approval</h3>
    </div>
    <div class="card-body">
        <p class="text-secondary small">
            Applies to <strong>{{ $store->name }}</strong>. Only low-risk products can be approved automatically.
        </p>
        <label class="form-check form-switch mb-3">
            <div class="form-check form-switch">
                <input class="form-check-input" id="store-auto-approval-enabled" type="checkbox"
                    @checked($store->auto_approve_products)>

                <label class="form-check-label" for="store-auto-approval-enabled">
                    Automatic product approval:
                    <strong>
                        {{ $store->auto_approve_products ? 'Enabled' : 'Disabled' }}
                    </strong>
                </label>
            </div>
        </label>
        <label class="form-label" for="store-auto-approval-reason">Reason</label>
        <textarea class="form-control mb-3" id="store-auto-approval-reason" rows="3" maxlength="1000"
            placeholder="Document why this store should or should not be trusted."></textarea>
        <button type="button" class="btn btn-primary" id="store-auto-approval-save">
            Save trust setting
        </button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const card = document.getElementById('store-auto-approval-card');

        if (!card) {
            return;
        }

        const toggle = document.getElementById('store-auto-approval-enabled');
        const label = document.getElementById('store-auto-approval-label');
        const reason = document.getElementById('store-auto-approval-reason');
        const button = document.getElementById('store-auto-approval-save');

        button.addEventListener('click', async () => {
            const explanation = reason.value.trim();

            if (explanation.length < 10) {
                notyf.error('Enter a reason of at least 10 characters.');
                return;
            }

            button.disabled = true;

            try {
                const response = await fetch(card.dataset.endpoint, {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        enabled: toggle.checked,
                        reason: explanation
                    })
                });
                const payload = await response.json();

                if (!response.ok) {
                    throw payload;
                }

                card.dataset.enabled = payload.enabled ? '1' : '0';
                toggle.checked = Boolean(payload.enabled);
                label.textContent = payload.enabled ? 'Enabled' : 'Disabled';
                reason.value = '';
                notyf.success(payload.message);
            } catch (payload) {
                toggle.checked = card.dataset.enabled === '1';
                label.textContent = toggle.checked ? 'Enabled' : 'Disabled';
                const message = payload?.errors?.enabled?.[0] ||
                    payload?.errors?.reason?.[0] ||
                    payload?.message ||
                    'Could not update the store trust setting.';
                notyf.error(message);
            } finally {
                button.disabled = false;
            }
        });
    });
</script>
