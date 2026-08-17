<?php

return [
    'digital_upload' => [
        'max_file_size_kb' => (int) env('PRODUCT_MAX_DIGITAL_FILE_SIZE_KB', 262144),
        'max_chunk_size_kb' => (int) env('PRODUCT_MAX_DIGITAL_CHUNK_SIZE_KB', 10240),
        'max_chunks' => (int) env('PRODUCT_MAX_DIGITAL_CHUNKS', 4096),
        'max_active_uploads_per_uploader' => (int) env('PRODUCT_MAX_ACTIVE_DIGITAL_UPLOADS', 3),
        'incomplete_upload_ttl_hours' => (int) env('PRODUCT_DIGITAL_UPLOAD_TTL_HOURS', 24),
        'max_files_per_product' => (int) env('PRODUCT_MAX_DIGITAL_FILES_PER_PRODUCT', 20),
        'max_total_size_per_product_kb' => (int) env('PRODUCT_MAX_DIGITAL_SIZE_PER_PRODUCT_KB', 1048576),
        'max_total_size_per_store_kb' => (int) env('PRODUCT_MAX_DIGITAL_SIZE_PER_STORE_KB', 5242880),
    ],

    'variants' => [
        'max_values_per_attribute' => (int) env('PRODUCT_MAX_VALUES_PER_ATTRIBUTE', 50),
        'max_attribute_groups' => (int) env('PRODUCT_MAX_ATTRIBUTE_GROUPS', 6),
        'max_combinations' => (int) env('PRODUCT_MAX_VARIANT_COMBINATIONS', 500),
    ],
];
