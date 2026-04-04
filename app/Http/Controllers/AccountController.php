<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Order;
use App\Models\Subscription;

class AccountController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function dashboard()
    {
        $user = Auth::user();
        $orderCount   = $user->orders()->count();
        $activeSubscription = $user->subscriptions()->where('status', 'active')->latest()->first();
        $recentOrders = $user->orders()->latest()->limit(3)->get();

        return view('account.dashboard', compact('user', 'orderCount', 'activeSubscription', 'recentOrders'));
    }

    public function library()
    {
        $user = Auth::user();
        $purchasedProducts = $user->getPurchasedProducts();
        $activeSubscription = $user->subscriptions()->where('status', 'active')->latest()->first();

        return view('account.library', compact('user', 'purchasedProducts', 'activeSubscription'));
    }

    public function orders()
    {
        $user = Auth::user();
        $orders = $user->orders()->latest()->paginate(10);

        return view('account.orders', compact('user', 'orders'));
    }

    public function profile()
    {
        $user = Auth::user();
        return view('account.profile', compact('user'));
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update([
            'name'  => $request->name,
            'email' => $request->email,
        ]);

        return redirect()->route('account.profile')->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password'      => 'required',
            'password'              => 'required|min:8|confirmed',
            'password_confirmation' => 'required',
        ]);

        $user = Auth::user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return redirect()->route('account.profile')->with('success', 'Password changed successfully.');
    }
}
