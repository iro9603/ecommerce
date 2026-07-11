<script>
    tinymce.init({
        selector: 'textarea#editor',


        //Necessary when TinyMCE is installed locally/self-hosted.
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

    tinymce.init({
        selector: 'textarea#short-editor',


        //Necessary when TinyMCE is installed locally/self-hosted.
        license_key: 'gpl',

        height: 300,

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

    $(function() {
        $('.delete-item').on('click', function(e) {
            e.preventDefault();
            const url = $(this).attr('href');

            Swal.fire({
                title: "Are you sure?",
                text: "You won't be able to revert this!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Yes, delete it!"
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        method: 'DELETE',
                        url: url,
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            if (response.status == 'success') {
                                window.location.reload();
                            }
                        },
                        error: function(xhr, status, error) {
                            console.log(error);

                        }
                    })
                }
            });
        })
    })

    // notyf init
    var notyf = new Notyf({
        duration: 3000
    });

    // select2 init
    $(document).ready(function() {
        $('.select2').select2();
        $('.js-example-basic-multiple').select2();
    });

    // datepicker init
    $(document).ready(function() {
        $(".selector-from").flatpickr();
        $(".selector-to").flatpickr();
    });
</script>
