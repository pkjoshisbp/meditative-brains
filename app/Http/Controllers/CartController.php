<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;

class CartController extends Controller
{
    public function index()
    {
        if (auth()->check()) {
            $cartItems = auth()->user()->cartItems()->with('product.category')->get();
        } else {
            $sessionCart = session()->get('cart', []);
            $cartItems = collect($sessionCart)->map(function ($item, $id) {
                $product = Product::with('category')->find($id);
                return $product ? (object)[
                    'product'    => $product,
                    'product_id' => $id,
                    'quantity'   => $item['quantity'] ?? 1,
                    'price'      => $item['price'] ?? $product->getCurrentPrice(),
                ] : null;
            })->filter();
        }

        $total = $cartItems->sum(function ($item) {
            return ($item->price ?? 0) * ($item->quantity ?? 1);
        });

        return view('cart', compact('cartItems', 'total'));
    }

    public function add(Request $request)
    {
        $request->validate(['product_id' => 'required|integer|exists:products,id']);

        $product = Product::findOrFail($request->product_id);

        if (auth()->check()) {
            auth()->user()->cartItems()->updateOrCreate(
                ['product_id' => $product->id],
                ['quantity' => 1, 'price' => $product->getCurrentPrice()]
            );
            $count = auth()->user()->cartItems()->count();
        } else {
            $cart = session()->get('cart', []);
            $cart[$product->id] = [
                'name'     => $product->name,
                'price'    => $product->getCurrentPrice(),
                'quantity' => 1,
            ];
            session()->put('cart', $cart);
            $count = count($cart);
        }

        return response()->json([
            'success' => true,
            'message' => "\"{$product->name}\" added to cart!",
            'cart_count' => $count,
        ]);
    }

    public function remove(Request $request)
    {
        $request->validate(['product_id' => 'required|integer']);

        if (auth()->check()) {
            auth()->user()->cartItems()->where('product_id', $request->product_id)->delete();
        } else {
            $cart = session()->get('cart', []);
            unset($cart[$request->product_id]);
            session()->put('cart', $cart);
        }

        return redirect()->route('cart')->with('message', 'Item removed from cart.');
    }

    public function clear()
    {
        if (auth()->check()) {
            auth()->user()->cartItems()->delete();
        } else {
            session()->forget('cart');
        }

        return redirect()->route('cart')->with('message', 'Cart cleared.');
    }
}
