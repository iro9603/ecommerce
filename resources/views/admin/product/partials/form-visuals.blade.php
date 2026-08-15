<style>
    .product-editor {
        --product-primary: var(--tblr-primary, #206bc4);
        --product-primary-rgb: var(--tblr-primary-rgb, 32, 107, 196);
        --product-ink: #182433;
        --product-muted: #667085;
        --product-line: #e7ebf0;
        --product-soft: #f6f8fb;
        --product-surface: #ffffff;
        position: relative;
        padding-bottom: 4rem;
    }

    .product-editor::before {
        position: absolute;
        z-index: 0;
        top: -1.5rem;
        right: 0;
        left: 0;
        height: 17rem;
        border-radius: 1.5rem;
        background:
            radial-gradient(circle at 8% 18%, rgba(var(--product-primary-rgb), .1), transparent 31%),
            radial-gradient(circle at 92% 4%, rgba(13, 148, 136, .08), transparent 26%);
        content: '';
        pointer-events: none;
    }

    .product-editor > * {
        position: relative;
        z-index: 1;
    }

    .product-editor .product-page-header {
        position: relative;
        overflow: hidden;
        padding: 1.4rem 1.5rem;
        border: 1px solid rgba(255, 255, 255, .12);
        border-radius: 1.15rem;
        background: linear-gradient(135deg, #17263b 0%, #203d62 55%, #1f5f78 100%);
        box-shadow: 0 16px 38px rgba(24, 36, 51, .16);
    }

    .product-editor .product-page-header::after {
        position: absolute;
        top: -6.5rem;
        right: -4.5rem;
        width: 17rem;
        height: 17rem;
        border: 1px solid rgba(255, 255, 255, .13);
        border-radius: 50%;
        background: rgba(255, 255, 255, .05);
        content: '';
        pointer-events: none;
    }

    .product-editor .product-page-heading {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .product-editor .product-page-icon {
        display: inline-flex;
        width: 3.25rem;
        height: 3.25rem;
        flex: 0 0 3.25rem;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(255, 255, 255, .18);
        border-radius: .9rem;
        background: rgba(255, 255, 255, .12);
        color: #fff;
        font-size: 1.55rem;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .16);
    }

    .product-editor .product-eyebrow {
        margin-bottom: .2rem;
        color: #8ed8e8;
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .product-editor .product-page-header .page-title {
        color: #fff;
        font-size: clamp(1.3rem, 2vw, 1.75rem);
        font-weight: 700;
        letter-spacing: -.025em;
    }

    .product-editor .product-page-header .product-page-description {
        max-width: 42rem;
        margin-top: .25rem;
        color: rgba(255, 255, 255, .68) !important;
        font-size: .86rem;
    }

    .product-editor .product-back-btn {
        position: relative;
        z-index: 1;
        display: inline-flex;
        min-height: 2.65rem;
        align-items: center;
        gap: .5rem;
        padding: .6rem .9rem;
        border-color: rgba(255, 255, 255, .25);
        border-radius: .75rem;
        background: rgba(255, 255, 255, .1);
        color: #fff;
        font-weight: 600;
        backdrop-filter: blur(8px);
    }

    .product-editor .product-back-btn:hover,
    .product-editor .product-back-btn:focus {
        border-color: rgba(255, 255, 255, .45);
        background: rgba(255, 255, 255, .18);
        color: #fff;
    }

    .product-editor .product-form > .row {
        --tblr-gutter-x: 1.5rem;
        --tblr-gutter-y: 1.5rem;
    }

    .product-editor .product-form .card {
        overflow: hidden;
        border: 1px solid var(--product-line);
        border-radius: 1rem;
        background: var(--product-surface);
        box-shadow: 0 8px 26px rgba(24, 36, 51, .055);
        transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
    }

    .product-editor .product-form > .row > div > .card:hover {
        border-color: #dbe2ea;
        box-shadow: 0 12px 32px rgba(24, 36, 51, .075);
    }

    .product-editor .product-form .card-header {
        min-height: auto;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--product-line);
        background: linear-gradient(180deg, #fff 0%, #fbfcfe 100%);
    }

    .product-editor .product-form .card-body {
        padding: 1.25rem;
    }

    .product-editor .product-section-heading {
        display: flex;
        align-items: center;
        gap: .75rem;
    }

    .product-editor .product-section-icon {
        display: inline-flex;
        width: 2.35rem;
        height: 2.35rem;
        flex: 0 0 2.35rem;
        align-items: center;
        justify-content: center;
        border-radius: .7rem;
        background: rgba(var(--product-primary-rgb), .1);
        color: var(--product-primary);
        font-size: 1.15rem;
    }

    .product-editor .card-title,
    .product-editor .product-section-title {
        margin: 0;
        color: var(--product-ink);
        font-size: .93rem;
        font-weight: 700;
        letter-spacing: -.01em;
    }

    .product-editor .product-section-description {
        margin: .12rem 0 0;
        color: #8a94a3;
        font-size: .75rem;
        line-height: 1.4;
    }

    .product-editor .form-label,
    .product-editor label[for="form-label"] {
        display: inline-block;
        margin-bottom: .48rem;
        color: #344054;
        font-size: .78rem;
        font-weight: 650;
        letter-spacing: .012em;
    }

    .product-editor .form-control,
    .product-editor .form-select,
    .product-editor .select2-container--default .select2-selection--single,
    .product-editor .select2-container--default .select2-selection--multiple {
        min-height: 2.75rem;
        border-color: #dce2e9;
        border-radius: .72rem;
        background-color: #fff;
        box-shadow: 0 1px 2px rgba(16, 24, 40, .025);
        transition: border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
    }

    .product-editor .form-control:hover,
    .product-editor .form-select:hover {
        border-color: #bfc9d5;
    }

    .product-editor .form-control:focus,
    .product-editor .form-select:focus,
    .product-editor .select2-container--focus .select2-selection,
    .product-editor .select2-container--open .select2-selection {
        border-color: rgba(var(--product-primary-rgb), .68) !important;
        box-shadow: 0 0 0 .22rem rgba(var(--product-primary-rgb), .11) !important;
    }

    .product-editor textarea.form-control {
        min-height: 8rem;
    }

    .product-editor .select2-container {
        width: 100% !important;
    }

    .product-editor .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 2.65rem;
    }

    .product-editor .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 2.65rem;
        right: .45rem;
    }

    .product-editor .select2-container--default .select2-selection--multiple {
        padding: .22rem .35rem;
    }

    .product-editor .tox-tinymce {
        overflow: hidden;
        border: 1px solid #dce2e9;
        border-radius: .78rem;
        box-shadow: 0 1px 2px rgba(16, 24, 40, .025);
    }

    .product-editor .form-check {
        min-height: 1.5rem;
    }

    .product-editor .form-check-input {
        border-color: #b8c2ce;
        box-shadow: none;
    }

    .product-editor .form-check-input:checked {
        border-color: var(--product-primary);
        background-color: var(--product-primary);
    }

    .product-editor .form-check-input:focus {
        border-color: var(--product-primary);
        box-shadow: 0 0 0 .2rem rgba(var(--product-primary-rgb), .12);
    }

    .product-editor .form-switch-3 {
        margin: 0;
        padding: .8rem .9rem .8rem 3.45rem;
        border: 1px solid var(--product-line);
        border-radius: .75rem;
        background: var(--product-soft);
    }

    .product-editor .form-switch-3 .form-check-input {
        margin-left: -2.55rem;
    }

    .product-editor .stock-management-box {
        padding: 1rem;
        border: 1px solid var(--product-line);
        border-radius: .8rem;
        background: var(--product-soft);
    }

    .product-editor .stock-status-card {
        margin-right: calc(var(--tblr-gutter-x) * .5);
        margin-left: calc(var(--tblr-gutter-x) * .5);
        box-shadow: none;
    }

    .product-editor .stock-status-options {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .75rem;
        margin: 0;
    }

    .product-editor .stock-status-options .form-check {
        margin: 0;
        padding: .75rem .75rem .75rem 2.25rem;
        border: 1px solid var(--product-line);
        border-radius: .7rem;
        background: var(--product-soft);
    }

    .product-editor .category-scroll {
        max-height: 25rem;
        overflow-y: auto;
        padding: 1.1rem;
        scrollbar-color: #c9d2dd transparent;
        scrollbar-width: thin;
    }

    .product-editor #category-search {
        position: sticky;
        z-index: 2;
        top: 0;
        padding-left: 2.5rem;
        background-color: #fff;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23828b98' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E");
        background-position: .8rem center;
        background-repeat: no-repeat;
    }

    .product-editor #category-tree {
        margin-bottom: 0;
    }

    .product-editor #category-tree ul {
        margin-left: .75rem !important;
        padding-left: .8rem;
        border-left: 1px dashed #d8dfe8;
    }

    .product-editor .category-wrapper {
        display: flex;
        align-items: center;
        gap: .52rem;
        margin: 0 0 .2rem;
        padding: .52rem .55rem;
        border-radius: .55rem;
        transition: background-color .15s ease;
    }

    .product-editor .category-wrapper:hover {
        background: #f2f5f9;
    }

    .product-editor .category-wrapper .form-check-input {
        flex: 0 0 auto;
        margin: 0;
    }

    .product-editor .category-label {
        color: #475467;
        font-size: .82rem;
    }

    .product-editor .product-label-options {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .65rem;
    }

    .product-editor .product-label-options .form-check {
        margin: 0;
        padding: .68rem .65rem .68rem 2.15rem;
        border: 1px solid var(--product-line);
        border-radius: .68rem;
        background: var(--product-soft);
    }

    .product-editor .dropzone {
        min-height: 11.5rem;
        margin-bottom: 0;
        padding: 2rem 1.25rem;
        border: 2px dashed #c7d2df;
        border-radius: .9rem;
        background:
            linear-gradient(#f8fafc, #f8fafc) padding-box,
            linear-gradient(135deg, rgba(var(--product-primary-rgb), .12), rgba(13, 148, 136, .08)) border-box;
        color: #687588;
        transition: border-color .2s ease, background-color .2s ease, transform .2s ease;
    }

    .product-editor .dropzone:hover,
    .product-editor .dropzone.dz-drag-hover {
        border-color: var(--product-primary);
        background: rgba(var(--product-primary-rgb), .045);
        transform: translateY(-1px);
    }

    .product-editor .dropzone .dz-message {
        margin: 2.15rem 0;
        font-weight: 600;
    }

    .product-editor .image-preview-container {
        grid-template-columns: repeat(auto-fill, minmax(132px, 1fr));
        gap: 1rem;
    }

    .product-editor .image-preview-item {
        overflow: visible;
        padding: .35rem;
        border: 1px solid var(--product-line);
        border-radius: .82rem;
        background: #fff;
        box-shadow: 0 6px 18px rgba(24, 36, 51, .07);
    }

    .product-editor .image-preview-item img {
        border-radius: .6rem;
    }

    .product-editor .image-preview-item .remove-image {
        display: inline-flex;
        top: .55rem;
        right: .55rem;
        width: 1.7rem;
        height: 1.7rem;
        align-items: center;
        justify-content: center;
        border: 2px solid #fff;
        background: #d63939;
        font-size: 1rem;
        line-height: 1;
        box-shadow: 0 4px 10px rgba(214, 57, 57, .28);
    }

    .product-editor .file-preview-container {
        display: grid;
        gap: .65rem;
        margin-top: 1rem;
    }

    .product-editor .file-preview-container .dz-preview {
        margin: 0;
        padding: .9rem 2.75rem .9rem 1rem;
        border-color: var(--product-line);
        border-radius: .75rem;
        background: var(--product-soft);
    }

    .product-editor .product-sidebar > .card,
    .product-editor .product-sidebar > .card > .card {
        margin-bottom: 1rem !important;
    }

    .product-editor .product-actions-card {
        position: sticky !important;
        z-index: 5;
        bottom: 1rem;
        top: auto !important;
        border-color: rgba(var(--product-primary-rgb), .2) !important;
        background: rgba(255, 255, 255, .94) !important;
        box-shadow: 0 14px 35px rgba(24, 36, 51, .13) !important;
        backdrop-filter: blur(12px);
    }

    .product-editor .product-actions-card .card-body {
        padding: .85rem;
    }

    .product-editor .product-submit-btn {
        display: inline-flex;
        min-height: 2.9rem;
        width: 100%;
        align-items: center;
        justify-content: center;
        gap: .55rem;
        margin: 0 !important;
        border: 0;
        border-radius: .72rem;
        background: linear-gradient(135deg, var(--product-primary), #1687a7);
        font-weight: 700;
        box-shadow: 0 8px 18px rgba(var(--product-primary-rgb), .22);
        transition: transform .18s ease, box-shadow .18s ease;
    }

    .product-editor .product-submit-btn:hover,
    .product-editor .product-submit-btn:focus {
        transform: translateY(-1px);
        box-shadow: 0 11px 23px rgba(var(--product-primary-rgb), .28);
    }

    .product-editor #product-attributes .accordion-item,
    .product-editor #product-variants .accordion-item {
        border-color: var(--product-line);
        border-radius: .8rem;
        box-shadow: none;
    }

    .product-editor #product-attributes .accordion-button,
    .product-editor #product-variants .accordion-button {
        font-weight: 650;
    }

    .product-editor .btn {
        border-radius: .65rem;
    }

    @media (min-width: 1200px) {
        .product-editor .product-sidebar {
            padding-left: .25rem;
        }
    }

    @media (max-width: 767.98px) {
        .product-editor {
            padding-bottom: 2rem;
        }

        .product-editor .product-page-header {
            padding: 1.15rem;
        }

        .product-editor .product-page-heading {
            align-items: flex-start;
        }

        .product-editor .product-page-icon {
            width: 2.75rem;
            height: 2.75rem;
            flex-basis: 2.75rem;
        }

        .product-editor .product-page-header .col-auto {
            width: 100%;
            margin-top: 1rem;
        }

        .product-editor .product-back-btn {
            width: 100%;
            justify-content: center;
        }

        .product-editor .product-form .card-body,
        .product-editor .product-form .card-header {
            padding: 1rem;
        }

        .product-editor .stock-status-options,
        .product-editor .product-label-options {
            grid-template-columns: 1fr;
        }

        .product-editor .product-actions-card {
            bottom: .5rem;
        }
    }
</style>
