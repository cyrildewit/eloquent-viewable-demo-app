<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoLoginController extends Controller
{
    public function login(Request $request, User $user): RedirectResponse
    {
        Auth::login($user);
        $request->session()->regenerate();

        return back()->with('status', "You are reading as <strong>{$user->name}</strong>. Views you record now carry <code>viewer_type = user</code> and <code>viewer_id = {$user->id}</code>.");
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->regenerate();

        return back()->with('status', 'You are browsing as a guest. Your views are tied together by the visitor cookie only.');
    }
}
