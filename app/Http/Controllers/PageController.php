<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PageController extends Controller
{
    public function about(): View
    {
        return view('pages.about');
    }

    public function terms(): View
    {
        return view('pages.terms');
    }

    public function privacy(): View
    {
        return view('pages.privacy');
    }

    public function credits(): View
    {
        $credits = is_file($path = public_path('images/listings/credits.json'))
            ? json_decode(file_get_contents($path), true) : [];

        return view('pages.credits', compact('credits'));
    }
}
