<script>
    tinymce.init({
        selector: 'textarea#editor',

        // Necesario cuando TinyMCE está instalado localmente/self-hosted
        license_key: 'gpl',

        height: 500,

        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'help', 'wordcount'
        ],

        toolbar: 'undo redo | blocks | ' +
            'bold italic backcolor | alignleft aligncenter ' +
            'alignright alignjustify | bullist numlist outdent indent | ' +
            'link image media table | ' +
            'removeformat | code fullscreen preview | help',

        content_style: 'body { font-family: Helvetica, Arial, sans-serif; font-size: 16px }'
    });
</script>
