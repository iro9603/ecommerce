<script src="https://cdn.tiny.cloud/1/{{ config('app.tinymce_key') }}/tinymce/8/tinymce.min.js" referrerpolicy="origin"
    crossorigin="anonymous"></script>

<script>
    tinymce.init({
        selector: 'textarea#editor',
        height: 500,
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'help', 'wordcount'
        ],
        toolbar: 'undo redo | blocks | ' +
            'bold italic backcolor | alignleft aligncenter ' +
            'alignright alignjustify | bullist numlist outdent indent | ' +
            'removeformat | help',
        content_style: 'body { font-family: Helvetica, Arial, sans-serif; font-size: 16px }'
    });
</script>
