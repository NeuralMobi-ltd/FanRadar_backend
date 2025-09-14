<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Mail\OTPMail;
use App\Models\User;
use App\Models\Post;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Product;
use BcMath\Number;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;

use function Laravel\Prompts\password;

class PersonnaliseController extends Controller
{



    // ====================
    // MAIN CONTENT
    // ====================

    /**
     * Obtenir le feed principal
     * Route: GET /api/feed/home
     */







    public function addFavorite($type, $itemId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Valider le type
        if (!in_array($type, ['post', 'product'])) {
            return response()->json([
                'success' => false,
                'message' => 'Type invalide. Utilisez "post" ou "product".'
            ], 400);
        }

        // Déterminer le modèle à utiliser
        $modelClass = $type === 'post' ? Post::class : Product::class;
        $modelName = $type === 'post' ? 'Post' : 'Product';

        // Vérifier que l'élément existe
        $item = $modelClass::find($itemId);
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => $modelName . ' not found'
            ], 404);
        }

        // Vérifier si déjà en favori
        $existingFavorite = Favorite::where([
            'user_id' => $user->id,
            'favoriteable_id' => $item->id,
            'favoriteable_type' => $modelClass,
        ])->first();

        if ($existingFavorite) {
            return response()->json([
                'success' => false,
                'message' => 'Cet élément est déjà dans vos favoris.'
            ], 409);
        }

        // Créer le favori
        Favorite::create([
            'user_id' => $user->id,
            'favoriteable_id' => $item->id,
            'favoriteable_type' => $modelClass,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'L\'élément a été ajouté aux favoris avec succès.'
        ], 201);
    }







    /**
     * Obtenir le feed d'exploration
     * Route: GET /api/feed/explore
     */


    // ====================
    // SOCIAL / USER RELATIONS
    // ====================










    public function getCategories()
    {
        $categories = Category::all();
        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories
            ]
        ]);
    }






    // ====================
    // HASHTAGS
    // ====================









    // ====================
    // STORE / E-COMMERCE
    // ====================
    public function getStoreCategories() {
        return response()->json([
            'success' => true,
            'data' => [
                'categories' => []
            ]
        ]);
    }
    public function getStoreBrands() {
        return response()->json([
            'success' => true,
            'data' => [
                'brands' => []
            ]
        ]);
    }
    public function addToCart(Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'Product added to cart successfully',
            'data' => [
                'cartItem' => [
                    'id' => 1,
                    'productId' => $request->productId ?? 1,
                    'quantity' => $request->quantity ?? 1,
                    'size' => $request->size ?? 'L',
                    'color' => $request->color ?? 'Black',
                    'price' => 45.99,
                    'subtotal' => 45.99
                ],
                'cartTotal' => 45.99,
                'itemsCount' => 1
            ]
        ]);
    }
    public function addToWishlist($productId) {
        return response()->json([
            'success' => true,
            'message' => 'Product added to wishlist',
            'data' => [
                'wishlistId' => 1,
                'productId' => $productId,
                'addedAt' => now()->toISOString()
            ]
        ]);
    }
    public function getCart() {
        return response()->json([
            'success' => true,
            'data' => [
                'items' => [],
                'summary' => [
                    'subtotal' => 0,
                    'shipping' => 0,
                    'tax' => 0,
                    'total' => 0,
                    'itemsCount' => 0
                ],
                'estimatedDelivery' => now()->addDays(5)->toDateString()
            ]
        ]);
    }
    public function updateCartItem($itemId, Request $request) {
        return response()->json([
            'success' => true,
            'data' => [
                'item' => [
                    'id' => $itemId,
                    'quantity' => $request->quantity ?? 1,
                    'subtotal' => 45.99
                ],
                'cartTotal' => 45.99
            ]
        ]);
    }
    public function removeCartItem($itemId) {
        return response()->json([
            'success' => true,
            'message' => 'Item removed from cart',
            'data' => [
                'removedItemId' => $itemId,
                'cartTotal' => 0,
                'itemsCount' => 0
            ]
        ]);
    }
    public function createOrder(Request $request) {
        return response()->json([
            'success' => true,
            'data' => [
                'order' => [
                    'id' => 'ORD-2024-001',
                    'status' => 'processing',
                    'total' => 45.99,
                    'estimatedDelivery' => now()->addDays(7)->toDateString(),
                    'createdAt' => now()->toISOString()
                ]
            ]
        ]);
    }
    public function getOrders() {
        return response()->json([
            'success' => true,
            'data' => [
                'orders' => [],
                'pagination' => [
                    'page' => 1,
                    'limit' => 10,
                    'total' => 0
                ]
            ]
        ]);
    }
    public function cancelOrder($orderId, Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully',
            'data' => [
                'orderId' => $orderId,
                'status' => 'cancelled',
                'refundAmount' => 45.99,
                'refundEstimate' => '3-5 business days'
            ]
        ]);
    }
    public function getOrderDetails($orderId) {
        return response()->json([
            'success' => true,
            'data' => [
                'order' => [
                    'id' => $orderId,
                    'status' => 'delivered',
                    'total' => 45.99,
                    'orderDate' => now()->subDays(5)->toISOString(),
                    'deliveredDate' => now()->toISOString(),
                    'trackingNumber' => 'TN123456789',
                    'estimatedDelivery' => now()->toISOString(),
                    'items' => []
                ]
            ]
        ]);
    }
    public function reviewOrder($orderId, Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully',
            'data' => [
                'reviewId' => 1,
                'rating' => $request->rating ?? 5,
                'submittedAt' => now()->toISOString()
            ]
        ]);
    }

   public function uploadImage(Request $request)
    {
        if (!$request->hasFile('image')) {
            return response()->json([
                'success' => false,
                'message' => 'No image file provided.'
            ], 422);
        }

        $file = $request->file('image');
        if (!$file->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid image file.'
            ], 422);
        }

        $path = $file->store('uploads', 'public');
        $url = 'storage/' . $path;

        return response()->json([
            'success' => true,
            'url' => $url
        ]);
    }


    public function getCategoryContent($category, Request $request)
    {
        // Recherche la catégorie par ID ou slug
        $cat = Category::where('id', $category)->orWhere('slug', $category)->first();
        if (!$cat) {
            return response()->json([
                'success' => false,
                'error' => 'Catégorie non trouvée'
            ], 404);
        }
        // Exemple: retourne les posts associés à la catégorie
        $posts = Post::where('category_id', $cat->id)->get();
        return response()->json([
            'success' => true,
            'data' => [
                'category' => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'image' => $cat->image,
                    'description' => $cat->description
                ],
                'posts' => $posts
            ]
        ]);
    }

    public function getStoreProducts(Request $request)
    {
        // Exemple: retourne tous les produits
        $products = Product::all();
        return response()->json([
            'success' => true,
            'data' => [
                'products' => $products
            ]
        ]);
    }




}
