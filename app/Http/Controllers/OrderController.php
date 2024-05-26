<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::query();

        if ($request->has('search')) {
            $query->whereHas('customer', function ($q) use ($request) {
                $q->where('name', 'LIKE', '%' . $request->search . '%')
                    ->orWhere('email', 'LIKE', '%' . $request->search . '%');
            })->orWhere('order_status', 'LIKE', '%' . $request->search . '%');
        }

        return $query->with('customer', 'products')->paginate();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
        ]);

        $total_amount = 0;

        foreach ($validated['products'] as $product) {
            $productModel = Product::find($product['product_id']);
            if ($productModel) {
                $total_amount += $productModel->price * $product['quantity'];
            }
        }

        $order = Order::create([
            'customer_id' => $validated['customer_id'],
            'total_amount' => $total_amount,
            'order_status' => 'pending',
        ]);

        foreach ($validated['products'] as $product) {
            $order->products()->attach($product['product_id'], ['quantity' => $product['quantity']]);
        }

        return $order->load('customer', 'products');
    }

    public function show(Order $order)
    {
        return $order->load('customer', 'products');
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'order_status' => 'required|in:pending,processing,completed',
        ]);

        $order->update($validated);

        return $order->load('customer', 'products');
    }

    public function destroy(Order $order)
    {
        $order->delete();

        return response()->noContent();
    }
}
