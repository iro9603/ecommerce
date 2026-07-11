<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <meta name="csrf-token" content = "{{ csrf_token() }}" />
    <title>Dashboard</title>
    <!-- BEGIN PAGE LEVEL STYLES -->
    {{--   <link href="./dist/libs/jsvectormap/dist/jsvectormap.css?1750026893" rel="stylesheet" /> --}}
    <!-- END PAGE LEVEL STYLES -->
    <!-- BEGIN GLOBAL MANDATORY STYLES -->
    <!-- Insert the blade containing the TinyMCE configuration and source script -->
    <link rel="stylesheet" href="{{ asset('assets/global/upload-preview/upload-preview.css') }}" />
    <link href="{{ asset('assets/admin/css/tabler.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/nestable2/1.6.0/jquery.nestable.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- END GLOBAL MANDATORY STYLES -->

    <!-- BEGIN CUSTOM FONT -->
    <style>
        @import url("https://rsms.me/inter/inter.css");
    </style>
    <!-- END CUSTOM FONT -->
    @stack('styles')
</head>

<body>
    <!-- BEGIN GLOBAL THEME SCRIPT -->
    <script src="{{ asset('assets/admin/js/tabler-theme.min.js?1750026893') }}"></script>
    <!-- END GLOBAL THEME SCRIPT -->
    <div class="page">
        <!--  BEGIN SIDEBAR  -->
        @include('admin.layouts.sidebar')
        <!--  END SIDEBAR  -->
        <div class="page-wrapper">

            <!-- BEGIN PAGE BODY -->
            <div class="page-body">
                @yield('contents')
            </div>
            <!-- END PAGE BODY -->

            <!--  BEGIN FOOTER  -->
            <footer class="footer footer-transparent d-print-none">
                <div class="container-xl">
                    <div class="row text-center align-items-center flex-row-reverse">

                        <div class="col-12 col-lg-auto mt-3 mt-lg-0">
                            <ul class="list-inline list-inline-dots mb-0">
                                <li class="list-inline-item">
                                    Copyright &copy; 2025
                                    <a href="javascript:void(0)" class="link-secondary">Ecommerce</a>. All rights
                                    reserved.
                                </li>
                                <li class="list-inline-item">
                                    <a href="javascript:void(0)" class="link-secondary" rel="noopener"> v1.1.0
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </footer>
            <!--  END FOOTER  -->
        </div>
    </div>

    <!-- BEGIN GLOBAL MANDATORY SCRIPTS -->
    <script src="https://code.jquery.com/jquery-4.0.0.min.js"
        integrity="sha256-OaVG6prZf4v69dPg6PhVattBXkcOWQB62pdZ3ORyrao=" crossorigin="anonymous"></script>
    <script src="{{ asset('assets/global/upload-preview/upload-preview.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('assets/admin/js/tinymce/tinymce.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/nestable2/1.6.0/jquery.nestable.min.js"></script>
    <script src="{{ asset('assets/admin/js/tabler.min.js') }}" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('assets/admin/libs/litepicker/dist/litepicker.js') }}" defer=""></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    @include('admin.layouts.script')
    @stack('scripts')
    <!-- END GLOBAL MANDATORY SCRIPTS -->


</body>

</html>
