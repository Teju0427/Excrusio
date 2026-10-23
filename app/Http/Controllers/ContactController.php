<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function create(): View
    {
        return view('pages.contact');
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: real people never see or fill the "website" field.
        if (filled($request->input('website'))) {
            return redirect()->route('contact')->with('status', 'Thanks — your message has been sent.');
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ]);
        ContactMessage::create($data);

        return redirect()->route('contact')->with('status', 'Thanks — your message has been sent. The team reads every one.');
    }
}
