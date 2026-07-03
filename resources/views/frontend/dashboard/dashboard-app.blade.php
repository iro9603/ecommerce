@extends('frontend.layouts.app')

@section('contents')
    <x-frontend.breadcrumb :items="[
        [
            'label' => 'Home',
            'url' => '/',
        ],
        [
            'label' => 'Dashboard',
        ],
    ]" />

    <div class="page-content pt-70 pb-60">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="dashboard-menu">
                                <ul class="nav flex-column" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" href=""><i
                                                class="fi-rs-settings-sliders mr-10"></i>Dashboard</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href=""><i
                                                class="fi-rs-shopping-bag mr-10"></i>Orders</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href=""><i
                                                class="fi-rs-shopping-cart-check mr-10"></i>Track Your Order</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href=""><i class="fi-rs-marker mr-10"></i>My Address</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="{{ route('profile') }}"><i
                                                class="fi-rs-user mr-10"></i>Account
                                            details</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href=""><i class="fi-rs-heart mr-10"></i> Wishlist</a>
                                    </li>
                                    <li class="nav-item">
                                        <form id="logout-form" method="POST" action="{{ route('logout') }}"
                                            style="display: none;">
                                            @csrf
                                        </form>
                                        <a class="nav-link" href="{{ route('logout') }}"
                                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();"><i
                                                class="fi-rs-sign-out mr-10"></i>Logout</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="tab-content account dashboard-content pl-50">
                                @yield('dashboard_contents')
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
