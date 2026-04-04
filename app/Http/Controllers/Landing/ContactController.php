<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landing\ContactRequest;
use App\Models\ContactMessage;

class ContactController extends Controller
{
    public function show()
    {
        return inertia('Landing/ContactPage');
    }

    public function store(ContactRequest $request)
    {
        if ($request->filled('_trap')) {
            abort(422);
        }

        $validated = $request->safe()->except(['_trap']);

        ContactMessage::create([
            ...$validated,
            'status' => 'new',
            'source' => 'landing-contact',
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Your message has been saved. Our team will contact you soon.');
    }
}
