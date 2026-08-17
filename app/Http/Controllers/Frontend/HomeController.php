<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $newArrivals = Product::query()
            ->published()
            ->with(['primaryImage', 'primaryVariant', 'store'])
            ->latest()
            ->limit(12)
            ->get();

        return view('frontend.home.index', compact('newArrivals'));
    }
}
