<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $orders = Order::with(['user', 'products'])->get();
        return response()->json($orders);
    }

    /**
     * Get orders for the authenticated user.
     */
    public function getMyOrders()
    {
        $orders = Order::with(['products'])
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'orders' => $orders,
            'total_orders' => $orders->count()
        ]);
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
{
    $request->validate([
        'status' => 'in:' . implode(',', Order::STATUSES),
        'order_date' => 'required|date',
        'products' => 'required|array',
        'products.*.product_id' => 'required|exists:products,id',
        'products.*.quantity' => 'required|integer|min:1',
    ]);

    $totalAmount = 0;

    // 1. Vérification du stock + calcul du montant total
    foreach ($request->products as $productData) {
        $product = Product::find($productData['product_id']);
        if (!$product) {
            return response()->json(['error' => "Produit ID {$productData['product_id']} introuvable."], 404);
        }

        if ($product->stock < $productData['quantity']) {
            return response()->json([
                'error' => "Stock insuffisant pour le produit '{$product->product_name}'. Stock disponible : {$product->stock}, demandé : {$productData['quantity']}."
            ], 422);
        }

        // Calcul du prix avec promotion
        $price = $product->price;

        // Vérifier si le produit a une promotion active
        if ($product->promotion && $product->promotion > 0) {
            $currentDate = now();
            $isPromotionActive = (!$product->sale_start_date || $currentDate >= $product->sale_start_date) &&
                               (!$product->sale_end_date || $currentDate <= $product->sale_end_date);

            if ($isPromotionActive) {
                // Appliquer la promotion (réduction en pourcentage)
                $price = $product->price * (1 - $product->promotion / 100);
            }
        }

        // Ajouter au montant total
        $totalAmount += $price * $productData['quantity'];
    }

    // 2. Création de la commande avec le montant calculé
    $order = Order::create([
        'user_id' => Auth::id(),
        'total_amount' => round($totalAmount, 2),
        'status' => $request->status ?? 'pending',
        'order_date' => $request->order_date,
    ]);

    // 3. Attachement des produits + décrémentation du stock
    foreach ($request->products as $productData) {
        $product = Product::find($productData['product_id']);

        // Attachement à la commande
        $order->products()->attach($product->id, [
            'quantity' => $productData['quantity'],
        ]);

        // Mise à jour du stock
        $product->decrement('stock', $productData['quantity']);
    }

    return response()->json($order->load('products'), 201);
}


    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        return response()->json($order->load(['user', 'products']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order)
    {
        $request->validate([
            'user_id' => 'exists:users,id',
            'total_amount' => 'numeric|min:0',
            'status' => 'in:' . implode(',', Order::STATUSES),
            'order_date' => 'date',
        ]);

        $order->update($request->only(['user_id', 'total_amount', 'status', 'order_date']));
        return response()->json($order);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        $order->delete();
        return response()->json(['message' => 'Order deleted successfully']);
    }

    /**
     * Retourne le nombre total de commandes.
     */
    public function getOrderCount()
    {
        $count = \App\Models\Order::count();
        return response()->json([
            'success' => true,
            'order_count' => $count
        ]);
    }
}
