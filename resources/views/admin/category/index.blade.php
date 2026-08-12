@extends('admin.layouts.app')
@push('styles')
    <style>
        .dd-item.custom-cat-item {
            border: none;
            padding: 0;
            margin-bottom: 0;
            background: none;
            border-radius: 0;
        }

        .dd-item-row.custom-cat-row {
            user-select: text;
            background: none;
            gap: 4px;
            border: 1px solid #e9ecef;
            border-radius: 0.25rem;
            min-height: 38px;
            display: flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            transition: border-color 0.15s ease, background-color 0.15s ease;
        }

        .dd-item-row.custom-cat-row.is-selected {
            background-color: #eef5ff;
            border-color: #86b7fe;
        }

        .dd-handle.custom-cat-handle {
            cursor: move;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.5rem;
            color: #6c757d;
        }

        .cat-folder-icon {
            font-size: 16px;
            color: #6c757d;
        }

        .cat-label.custom-cat-label {
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            flex: 1 1 auto;
            min-width: 0;
        }

        .cat-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .cat-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex: 0 0 auto;
        }

        .cat-status-dot.is-active {
            background-color: #198754;
        }

        .cat-status-dot.is-inactive {
            background-color: #dc3545;
        }

        .dd-list .dd-list {
            padding-left: 50px;
        }

        .category-empty-state {
            border: 1px dashed #ced4da;
            border-radius: 0.25rem;
            color: #6c757d;
            padding: 1rem;
            text-align: center;
        }
    </style>
@endpush
@section('contents')
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Categories</span>
                        <button class="btn btn-primary" id="btn-new" type="button">
                            <i class="ti ti-plus"></i>
                            <span>New</span>
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="category-tree" class="dd"></div>
                        <div id="tree-loading" class="text-center my-2 d-none" role="status">
                            <div class="spinner-border"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <span id="category-title">Create Category</span>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.categories.store') }}" id="category-form" method="POST">
                            <input type="hidden" id="category-id" name="id">
                            <div class="mb-2">
                                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required id="name"
                                    autocomplete="off">
                            </div>
                            <div class="mb-2">
                                <label for="slug" class="form-label">Slug <span class="text-danger">*</span></label>
                                <input type="text" name="slug" class="form-control" required id="slug"
                                    autocomplete="off">
                            </div>
                            <div class="mb-2">
                                <label for="parent_id" class="form-label">Parent Category</label>
                                <select name="parent_id" id="parent_id" class="form-select"></select>
                            </div>
                            <div class="mb-2">
                                <label for="is_active" class="form-check form-switch form-switch-3">
                                    <input type="checkbox" class="form-check-input" checked name="is_active" id="is_active">
                                    <span class="form-check-label">Active</span>
                                </label>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary" id="btn-save">
                                    <i class="ti ti-device-floppy"></i>
                                    <span>Save</span>
                                </button>
                                <button type="button" class="btn btn-danger d-none" id="btn-delete">
                                    <i class="ti ti-trash"></i>
                                    <span>Delete</span>
                                </button>
                                <button type="button" class="btn btn-secondary" id="btn-cancel">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function() {
            const maxDepth = 3;
            const routes = {
                nested: "{{ route('admin.categories.nested') }}",
                store: "{{ route('admin.categories.store') }}",
                update: "{{ route('admin.categories.update', '__ID__') }}",
                show: "{{ route('admin.categories.show', '__ID__') }}",
                destroy: "{{ route('admin.categories.destroy', '__ID__') }}",
                updateOrder: "{{ route('admin.categories.update-order') }}",
            };
            const csrfToken = '{{ csrf_token() }}';
            let selectedCategoryId = null;

            function categoryUrl(template, id) {
                return template.replace('__ID__', encodeURIComponent(id));
            }

            function escapeHtml(value) {
                return $('<div>').text(value ?? '').html();
            }

            function isTruthy(value) {
                return value === true || value === 1 || value === '1';
            }

            function showAjaxErrors(xhr, fallbackMessage) {
                const response = xhr.responseJSON || {};

                if (response.errors) {
                    $.each(response.errors, function(key, messages) {
                        notyf.error(messages[0]);
                    });
                    return;
                }

                notyf.error(response.message || fallbackMessage);
            }

            function setFormLoading(isLoading) {
                $('#btn-save').prop('disabled', isLoading);
                $('#btn-delete').prop('disabled', isLoading);
                $('#btn-cancel').prop('disabled', isLoading);
                $('#btn-new').prop('disabled', isLoading);
            }

            function loadTree() {
                $('#tree-loading').removeClass('d-none');

                $.get(routes.nested)
                    .done(function(data) {
                        const categories = Array.isArray(data) ? data : [];

                        if (!categories.length) {
                            $('#category-tree').html(
                                '<div class="category-empty-state">No categories yet.</div>');
                            return;
                        }

                        $('#category-tree').html('<div class="dd" id="nestable-tree">' + renderTree(
                            categories) + '</div>');
                        $('#nestable-tree').nestable({
                            maxDepth: maxDepth
                        }).off('change').on('change', function() {
                            updateOrder();
                        });
                        highlightSelectedCategory();
                    })
                    .fail(function(xhr) {
                        showAjaxErrors(xhr, 'Unable to load categories.');
                    })
                    .always(function() {
                        $('#tree-loading').addClass('d-none');
                    });
            }

            function renderTree(categories) {
                if (!Array.isArray(categories) || !categories.length) {
                    return '';
                }

                let html = '<ol class="dd-list" style="margin-bottom: 0">';

                categories.forEach(function(cat) {
                    const id = Number(cat.id);
                    const isActive = isTruthy(cat.is_active);
                    const statusText = isActive ? 'Active' : 'Inactive';
                    const selectedClass = selectedCategoryId == id ? ' is-selected' : '';

                    html += `<li class="dd-item custom-cat-item" data-id="${id}">
                                    <div class="dd-item-row custom-cat-row${selectedClass}">
                                        <div class="dd-handle custom-cat-handle" title="Drag to reorder">
                                            <i class="ti ti-grip-horizontal"></i>
                                        </div>
                                        <i class="ti ti-folder cat-folder-icon"></i>
                                        <div class="cat-label custom-cat-label" data-id="${id}" role="button" tabindex="0">
                                            <span class="cat-name">${escapeHtml(cat.name)}</span>
                                            <span class="cat-status-dot ${isActive ? 'is-active' : 'is-inactive'}" title="${statusText}" aria-label="${statusText}"></span>
                                        </div>
                                    </div>`;

                    if (cat.children_nested && cat.children_nested.length) {
                        html += renderTree(cat.children_nested);
                    }

                    html += '</li>';
                });

                html += '</ol>';

                return html;
            }

            function highlightSelectedCategory() {
                $('.custom-cat-row').removeClass('is-selected');

                if (!selectedCategoryId) {
                    return;
                }

                $(`.cat-label[data-id="${selectedCategoryId}"]`).closest('.custom-cat-row').addClass('is-selected');
            }

            function updateOrder() {
                const tree = $('#nestable-tree').nestable('serialize');

                $.post({
                    url: routes.updateOrder,
                    data: {
                        tree: tree,
                        _token: csrfToken,
                    }
                }).done(function(response) {
                    if (response.success) {
                        notyf.success(response.message);
                    }
                }).fail(function(xhr) {
                    showAjaxErrors(xhr, 'Unable to update category order.');
                    loadTree();
                });
            }

            $('#category-form').on('submit', function(e) {
                e.preventDefault();

                const id = $('#category-id').val();
                const method = id ? 'PUT' : 'POST';
                const url = id ? categoryUrl(routes.update, id) : routes.store;

                setFormLoading(true);

                $.ajax({
                    url: url,
                    method: method,
                    data: {
                        name: $('#name').val(),
                        slug: $('#slug').val(),
                        parent_id: $('#parent_id').val(),
                        is_active: $('#is_active').is(':checked') ? 1 : 0,
                        _token: csrfToken
                    }
                }).done(function(response) {
                    selectedCategoryId = null;
                    clearForm();
                    loadTree();
                    notyf.success(response.message);
                }).fail(function(xhr) {
                    showAjaxErrors(xhr, 'Unable to save category.');
                }).always(function() {
                    setFormLoading(false);
                });
            });

            function loadParentDropdown(selectedId, excludeId) {
                $.get(routes.nested)
                    .done(function(data) {
                        const categories = Array.isArray(data) ? data : [];
                        const options = ['<option value="">None (Root)</option>'];

                        function addOptions(cats, prefix, depth) {
                            cats.forEach(function(cat) {
                                const id = Number(cat.id);

                                if (excludeId && id == excludeId) {
                                    return;
                                }

                                const selected = selectedId == id ? 'selected' : '';
                                const disabled = depth >= maxDepth ? 'disabled' : '';
                                options.push(
                                    `<option value="${id}" ${selected} ${disabled}>${escapeHtml(prefix + cat.name)}</option>`
                                    );

                                if (cat.children_nested && cat.children_nested.length) {
                                    addOptions(cat.children_nested, prefix + '-- ', depth + 1);
                                }
                            });
                        }

                        addOptions(categories, '', 1);
                        $('#parent_id').html(options.join(''));
                    })
                    .fail(function(xhr) {
                        $('#parent_id').html('<option value="">None (Root)</option>');
                        showAjaxErrors(xhr, 'Unable to load parent categories.');
                    });
            }

            $('#btn-delete').on('click', function() {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (!result.isConfirmed) {
                        return;
                    }

                    const id = $('#category-id').val();
                    setFormLoading(true);

                    $.ajax({
                        url: categoryUrl(routes.destroy, id),
                        method: 'POST',
                        data: {
                            _token: csrfToken,
                            _method: 'DELETE'
                        }
                    }).done(function(response) {
                        if (response.success) {
                            selectedCategoryId = null;
                            clearForm();
                            loadTree();
                            notyf.success(response.message);
                        }
                    }).fail(function(xhr) {
                        showAjaxErrors(xhr, 'Unable to delete category.');
                    }).always(function() {
                        setFormLoading(false);
                    });
                });
            });

            $(document).off('click keydown', '.cat-label').on('click keydown', '.cat-label', function(e) {
                if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();

                const id = $(this).data('id');
                selectedCategoryId = id;
                highlightSelectedCategory();

                $.get(categoryUrl(routes.show, id))
                    .done(function(cat) {
                        fillForm(cat);
                    })
                    .fail(function(xhr) {
                        showAjaxErrors(xhr, 'Unable to load category.');
                    });
            });

            // slug auto-generate
            $('#name').on('input', function() {
                if (!$('#category-id').val()) {
                    $('#slug').val(slugify($(this).val()));
                }
            });

            function slugify(text) {
                return text.toString().toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/[^a-z0-9\-]/g, '')
                    .replace(/\-+/g, '-')
                    .replace(/^\-+|\-+$/g, '');
            }

            function fillForm(cat) {
                $('#category-title').text('Edit Category');
                $('#name').val(cat.name);
                $('#slug').val(cat.slug);
                $('#is_active').prop('checked', isTruthy(cat.is_active));
                loadParentDropdown(cat.parent_id, cat.id);
                $('#category-id').val(cat.id);
                $('#btn-delete').removeClass('d-none');
            }

            function clearForm() {
                $('#category-title').text('Create Category');
                $('#name').val('');
                $('#slug').val('');
                $('#parent_id').val('');
                $('#is_active').prop('checked', true);
                $('#category-id').val('');
                $('#btn-delete').addClass('d-none');
                selectedCategoryId = null;
                highlightSelectedCategory();
                loadParentDropdown(null, null);
            }

            $('#btn-new').on('click', function() {
                clearForm();
            });

            $('#btn-cancel').on('click', function() {
                clearForm();
            });

            clearForm();
            loadTree();
        })
    </script>
@endpush
