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





    public function addFavoriteProduct($pProductId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Vérifier que le produit existe
        $product = Product::find($pProductId);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        // Vérifier si déjà en favori
        $existingFavorite = Favorite::where([
            'user_id' => $user->id,
            'favoriteable_id' => $product->id,
            'favoriteable_type' => 'App\\Models\\Product',
        ])->first();

        if ($existingFavorite) {
            return response()->json([
                'success' => false,
                'message' => 'Ce produit est déjà dans vos favoris.'
            ], 409);
        }

        // Créer le favori
        Favorite::create([
            'user_id' => $user->id,
            'favoriteable_id' => $product->id,
            'favoriteable_type' => 'App\\Models\\Product',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Le produit a été ajouté aux favoris avec succès.'
        ], 201);
    }

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

    public function addfavoritePost($postId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $post = Post::find($postId);
        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found'
            ], 404);
        }

        // Vérifier si déjà en favori
        $existingFavorite = Favorite::where([
            'user_id' => $user->id,
            'favoriteable_id' => $post->id,
            'favoriteable_type' => 'App\\Models\\Post',
        ])->first();

        if ($existingFavorite) {
            return response()->json([
                'success' => false,
                'message' => 'Ce post est déjà dans vos favoris.'
            ], 409);
        }

        // Créer le favori
        Favorite::create([
            'user_id' => $user->id,
            'favoriteable_id' => $post->id,
            'favoriteable_type' => 'App\\Models\\Post',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Le post a été ajouté aux favoris avec succès.'
        ], 201);
    }

    public function removefavoritePost($postId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $post = Post::find($postId);
        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found'
            ], 404);
        }

        // Vérifier si le favori existe
        $existingFavorite = Favorite::where([
            'user_id' => $user->id,
            'favoriteable_id' => $post->id,
            'favoriteable_type' => 'App\\Models\\Post',
        ])->first();

        if (!$existingFavorite) {
            return response()->json([
                'success' => false,
                'message' => 'Ce post n\'est pas dans vos favoris.'
            ], 404);
        }

        // Supprimer le favori
        $existingFavorite->delete();

        return response()->json([
            'success' => true,
            'message' => 'Le post a été retiré des favoris avec succès.'
        ], 200);
    }

    public function removeFavoriteProduct($pProductId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Vérifier que le produit existe
        $product = Product::find($pProductId);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        // Vérifier si le favori existe
        $existingFavorite = Favorite::where([
            'user_id' => $user->id,
            'favoriteable_id' => $product->id,
            'favoriteable_type' => 'App\\Models\\Product',
        ])->first();

        if (!$existingFavorite) {
            return response()->json([
                'success' => false,
                'message' => 'Ce produit n\'est pas dans vos favoris.'
            ], 404);
        }

        // Supprimer le favori
        $existingFavorite->delete();

        return response()->json([
            'success' => true,
            'message' => 'Le produit a été retiré des favoris avec succès.'
        ], 200);
    }

    /**
     * Obtenir le feed d'exploration
     * Route: GET /api/feed/explore
     */


    // ====================
    // SOCIAL / USER RELATIONS
    // ====================



    public function followUser($userId) {
        $authUser = Auth::user();
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $followerId = $authUser->id;

        // Vérifier que l'utilisateur à suivre existe
        $userToFollow = User::find($userId);
        if (!$userToFollow) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Empêcher qu'un utilisateur se suive lui-même
        if ($followerId == $userId) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot follow yourself'
            ], 400);
        }

        // Vérifier si la relation existe déjà
        $existingFollow = \App\Models\Follow::where([
            'follower_id' => $followerId,
            'following_id' => $userId,
        ])->first();

        if ($existingFollow) {
            return response()->json([
                'success' => false,
                'message' => 'Already following this user'
            ], 409);
        }

        // Créer la relation de follow
        \App\Models\Follow::create([
            'follower_id' => $followerId,
            'following_id' => $userId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User followed successfully'
        ], 201);
    }

    public function unfollowUser($userId) {
        $authUser = Auth::user();
        if (!$authUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $followerId = $authUser->id;

        // Vérifier que l'utilisateur existe
        $userToUnfollow = User::find($userId);
        if (!$userToUnfollow) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        // Supprimer la relation si elle existe
        $existingFollow = \App\Models\Follow::where([
            'follower_id' => $followerId,
            'following_id' => $userId,
        ])->first();

        if (!$existingFollow) {
            return response()->json(['success' => false, 'message' => 'Not following this user'], 409);
        }

        $existingFollow->delete();

        return response()->json(['success' => true, 'message' => 'User unfollowed successfully'], 200);
    }


    /**
     * Récupérer les posts sauvegardés d'un utilisateur
     * Route: GET /api/users/saved-posts
     */



    // ====================
    // POSTS
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

    /**
     * Récupérer les sous-catégories d'une catégorie
     * Route: GET /api/Y/categories/{category_id}/subcategories
     */
    public function getCategorySubcategories($category_id)
    {
        $category = Category::find($category_id);
        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        $subcategories = \App\Models\SubCategory::where('category_id', $category_id)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'image' => $category->image,
                    'description' => $category->description
                ],
                'subcategories' => $subcategories
            ]
        ]);
    }

    /**
     * Récupérer les fandoms d'une catégorie
     * Route: GET /api/Y/categories/{category_id}/fandoms
     */


     /**
     * Récupérer tous les fandoms
     * Route: GET /api/fandoms
     */




    /**
     * Rechercher des fandoms par q
     * Route: GET /api/fandoms/search?q=QUERY
     */



        /**
     * Récupérer les fandoms d'une sous-catégorie
     * Route: GET /api/subcategories/{subcategoryId}/fandoms
     */


    /**
     * Récupérer un fandom par id et inclure le statut du member de l'utilisateur authentifié
     * Route: GET /api/fandoms/{fandom_id} (route already registered)
     */


    /**
     * Permettre à un utilisateur authentifié de rejoindre un fandom
     * Route: POST /api/Y/fandoms/{fandom_id}/join
     */

public function getHashtagPosts($hashtagId, Request $request) {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));

        // Rechercher le tag par ID
        $tag = \App\Models\Tag::find($hashtagId);

        if (!$tag) {
            return response()->json([
                'success' => false,
                'message' => 'Hashtag not found',
                'data' => [
                    'posts' => [],
                    'stats' => [
                        'totalPosts' => 0,
                        'growth' => '+0%',
                        'category' => 'General'
                    ]
                ]
            ], 404);
        }

        return $this->getPostsByTag($tag, $page, $limit);
    }
    /**
     * Permettre à un utilisateur authentifié de quitter un fandom
     * Route: DELETE /api/Y/fandoms/{fandom_id}/leave
     */


    /**
     * Permettre à un administrateur de changer le rôle d'un membre dans un fandom
     * Route: PUT /api/Y/fandoms/{fandom_id}/members/{user_id}/role
     */


    /**
     * Supprimer un membre d'un fandom (admin seulement)
     * Route: DELETE /api/Y/fandoms/{fandom_id}/members/{user_id}
     */


    /**
     * Permettre aux membres d'un fandom d'ajouter un post dans ce fandom
     * Route: POST /api/Y/fandoms/{fandom_id}/posts
     */


    /**
     * Permettre aux membres d'un fandom de mettre à jour leur post
     * Route: PUT /api/Y/fandoms/{fandom_id}/posts/{post_id}
     */


    /**
     * Permettre aux membres d'un fandom de supprimer leur post
     * Route: DELETE /api/Y/fandoms/{fandom_id}/posts/{post_id}
     */


    /**
     * Obtenir les posts d'un fandom
     * Route: GET /api/fandoms/{fandom_id}/posts
     */


    /**
     * Obtenir les membres d'un fandom
     * Route: GET /api/fandoms/{fandom_id}/members
     */




    /**
     * Créer un nouveau fandom
     * Route: POST /api/Y/fandoms
     */


    /**
     * Mettre à jour un fandom existant
     * Route: POST /api/Y/fandoms/{idOrHandle}
     */


    public function getAllCategories(Request $request) {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(50, max(1, (int) $request->get('limit', 10)));

        // Récupérer les catégories avec pagination
        $categories = Category::paginate($limit, ['*'], 'page', $page);

        // Formater les données
        $formattedCategories = $categories->getCollection()->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug ?? null,
                'description' => $category->description ?? null,
                'image' => $category->image ?? null,
                'created_at' => $category->created_at ? $category->created_at->toISOString() : null,
                'updated_at' => $category->updated_at ? $category->updated_at->toISOString() : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $formattedCategories,
                'pagination' => [
                    'current_page' => $categories->currentPage(),
                    'last_page' => $categories->lastPage(),
                    'per_page' => $categories->perPage(),
                    'total' => $categories->total(),
                    'has_more' => $categories->hasMorePages(),
                ]
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

    /**
     * Route: POST /api/upload/image
     * Upload an image and return its URL
     */
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
        $url = asset('storage/' . $path);

        return response()->json([
            'success' => true,
            'url' => $url
        ]);
    }

    /**
     * Obtenir le contenu d'une catégorie
     * Route: GET /api/categories/{category}/content
     */
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

    /**
     * Obtenir la liste des produits du store
     * Route: GET /api/store/products
     */
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

    /**
     * Obtenir les produits drag (édition limitée)
     * Route: GET /api/Y/products/drag
     */
    public function getDragProducts(Request $request)
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(50, max(1, (int) $request->get('limit', 10)));
        $status = $request->get('status'); // 'upcoming', 'active', 'expired', 'all'

        $now = now();
        $query = Product::with(['medias', 'tags', 'subcategory', 'ratings'])
            ->whereNotNull('sale_start_date')
            ->whereNotNull('sale_end_date')
            ->where('content_status', 'published');

        // Filtrer par statut si spécifié
        switch ($status) {
            case 'upcoming':
                // Produits qui n'ont pas encore commencé leur vente
                $query->where('sale_start_date', '>', $now);
                break;
            case 'active':
                // Produits en vente actuellement
                $query->where('sale_start_date', '<=', $now)
                      ->where('sale_end_date', '>=', $now);
                break;
            case 'expired':
                // Produits dont la vente est terminée
                $query->where('sale_end_date', '<', $now);
                break;
            case 'all':
            default:
                // Tous les produits drag
                break;
        }

        // Trier par urgence (produits se terminant bientôt en premier)
        $query->orderByRaw('
            CASE
                WHEN sale_end_date < NOW() THEN 3
                WHEN sale_start_date > NOW() THEN 2
                ELSE 1
            END
        ')
        ->orderBy('sale_end_date', 'asc');

        $products = $query->paginate($limit, ['*'], 'page', $page);

        // Formater les données des produits
        $formattedProducts = $products->getCollection()->map(function ($product) use ($now) {
            // Calculer le statut du produit
            $status = 'expired';
            $timeRemaining = null;
            $daysUntilStart = null;

            if ($product->sale_start_date > $now) {
                $status = 'upcoming';
                $daysUntilStart = $now->diffInDays($product->sale_start_date);
            } elseif ($product->sale_start_date <= $now && $product->sale_end_date >= $now) {
                $status = 'active';
                $timeRemaining = $now->diffInDays($product->sale_end_date);
            }

            // Calculer le pourcentage de stock restant
            $stockPercentage = $product->stock > 0 ? min(100, ($product->stock / 1000) * 100) : 0;

            // Calculer la note moyenne
            $averageRating = $product->ratings->count() > 0 ? round($product->ratings->avg('evaluation'), 1) : 0;

            return [
                'id' => $product->id,
                'product_name' => $product->product_name,
                'description' => $product->description,
                'price' => (float) $product->price,
                'original_price' => $product->promotion ? (float) ($product->price / (1 - $product->promotion / 100)) : (float) $product->price,
                'promotion' => $product->promotion,
                'stock' => $product->stock,
                'stock_percentage' => $stockPercentage,
                'sale_start_date' => $product->sale_start_date ? $product->sale_start_date->toISOString() : null,
                'sale_end_date' => $product->sale_end_date ? $product->sale_end_date->toISOString() : null,
                'status' => $status,
                'time_remaining_days' => $timeRemaining,
                'days_until_start' => $daysUntilStart,
                'is_limited' => true,
                'urgency_level' => $timeRemaining !== null && $timeRemaining <= 3 ? 'high' : ($timeRemaining <= 7 ? 'medium' : 'low'),
                'subcategory' => $product->subcategory ? [
                    'id' => $product->subcategory->id,
                    'name' => $product->subcategory->name,
                ] : null,
                'media' => $product->medias ? $product->medias->map(function($media) {
                    return [
                        'id' => $media->id,
                        'file_path' => $media->file_path,
                        'media_type' => $media->media_type,
                    ];
                })->toArray() : [],
                'tags' => $product->tags ? $product->tags->pluck('tag_name')->toArray() : [],
                'average_rating' => $averageRating,
                'ratings_count' => $product->ratings->count(),
                'favorites_count' => $product->favorites()->count(),
                'created_at' => $product->created_at ? $product->created_at->toISOString() : null,
            ];
        });

        // Statistiques générales
        $totalDragProducts = Product::whereNotNull('sale_start_date')
            ->whereNotNull('sale_end_date')
            ->where('content_status', 'published')
            ->count();

        $activeDragProducts = Product::whereNotNull('sale_start_date')
            ->whereNotNull('sale_end_date')
            ->where('content_status', 'published')
            ->where('sale_start_date', '<=', $now)
            ->where('sale_end_date', '>=', $now)
            ->count();

        $upcomingDragProducts = Product::whereNotNull('sale_start_date')
            ->where('content_status', 'published')
            ->where('sale_start_date', '>', $now)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $formattedProducts,
                'statistics' => [
                    'total_drag_products' => $totalDragProducts,
                    'active_products' => $activeDragProducts,
                    'upcoming_products' => $upcomingDragProducts,
                    'expired_products' => $totalDragProducts - $activeDragProducts - $upcomingDragProducts,
                ],
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'has_more' => $products->hasMorePages(),
                ]
            ]
        ]);
    }

    /**
     * Sauvegarder un post
     * Route: POST /api/posts/{postId}/save
     */


    /**
     * Retirer un post des sauvegardés
     * Route: DELETE /api/posts/{postId}/unsave
     */


    /**
     * Basculer l'état de sauvegarde d'un post (sauvegarder ou désauvegarder)
     * Route: POST /api/posts/{postId}/toggle-save
     */

    /**
     * Rechercher des utilisateurs par nom avec pagination
     * Route: GET /api/search/users
     */


    /**
     * Rechercher des posts par tags, description ou sous-catégorie avec pagination
     * Route: GET /api/Y/search/posts
     */


    /**
     * Rechercher des fandoms avec pagination
     * Route: GET /api/Y/search/fandom
     */


    /**
     * Récupérer les posts d'une sous-catégorie avec leurs médias
     * Route: GET /api/Y/subcategories/{subcategory}/content
     */


    /**
     * Récupérer les fandoms d'une sous-catégorie
     * Route: GET /api/Y/subcategories/{subcategory_id}/fandoms
     */


    /**
     * Récupérer les posts trending basés sur le nombre de likes et commentaires
     * Route: GET /api/Y/posts/trending/top
     */
    public function getTrendingPosts(Request $request)
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 20);
        $days = max(1, (int) $request->get('days', 7)); // Trending sur les X derniers jours

        // Calculer la date de début
        $startDate = now()->subDays($days);

        // Récupérer les posts avec le count des favorites et commentaires
        $trendingPosts = \App\Models\Post::with(['user', 'medias', 'tags'])
            ->where('content_status', 'published')
            ->where('created_at', '>=', $startDate)
            ->withCount(['favorites', 'comments'])
            ->orderBy('favorites_count', 'desc')
            ->orderBy('comments_count', 'desc')
            ->take($limit)
            ->get();

        // Formater les posts EXACTEMENT comme getHomeFeed
        $formattedPosts = $trendingPosts->map(function ($post) {
            // Récupérer toutes les données du post
            $postData = $post->toArray();

            // Ajouter les compteurs
            $postData['comments_count'] = method_exists($post, 'comments') ? $post->comments()->count() : 0;

            // Ajouter les médias
            $postData['media'] = method_exists($post, 'medias') ? $post->medias->pluck('file_path')->toArray() : [];

            // Ajouter les tags
            $postData['tags'] = method_exists($post, 'tags') ? $post->tags->pluck('tag_name')->toArray() : [];

            // Supprimer le champ medias (garder seulement media)
            unset($postData['medias']);

            // Formater l'utilisateur
            if (isset($postData['user']) && is_array($postData['user'])) {
                // Supprimer les champs sensibles de l'utilisateur
                unset($postData['user']['password']);
                unset($postData['user']['email_verified_at']);
                unset($postData['user']['remember_token']);
            }

            return $postData;
        });

        // Même structure de réponse que getHomeFeed
        return response()->json([
            'success' => true,
            'data' => [
                'posts' => $formattedPosts->values(),
                'pagination' => [
                    'page' => 1,
                    'limit' => $limit,
                    'hasNext' => false
                ]
            ]
        ]);
    }

    /**
     * Récupérer les commentaires d'un post avec les informations des utilisateurs
     * Route: GET /api/Y/posts/{postId}/comments
     */
    public function getPostComments($postId, Request $request)
    {
        $page = $request->get('page', 1);
        $limit = min(100, max(1, (int) $request->get('limit', 20)));

        // Vérifier que le post existe
        $post = \App\Models\Post::find($postId);
        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found'
            ], 404);
        }

        // Récupérer les commentaires avec pagination
        $comments = \App\Models\Comment::where('post_id', $postId)
            ->with(['user:id,first_name,last_name,email,profile_image,bio'])
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        // Formater les commentaires
        $formattedComments = collect($comments->items())->map(function ($comment) {
            $user = $comment->user;
            return [
                'id' => $comment->id,
                'content' => $comment->content,
                'created_at' => $comment->created_at ? $comment->created_at->toISOString() : null,
                'updated_at' => $comment->updated_at ? $comment->updated_at->toISOString() : null,
                'user' => $user ? [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => trim($user->first_name . ' ' . $user->last_name),
                    'email' => $user->email,
                    'profile_image' => $user->profile_image,
                    'bio' => $user->bio,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'post_id' => $postId,
                'comments' => $formattedComments,
                'comments_count' => $comments->total(),
                'pagination' => [
                    'current_page' => $comments->currentPage(),
                    'total_pages' => $comments->lastPage(),
                    'total_items' => $comments->total(),
                    'per_page' => $comments->perPage(),
                    'has_more' => $comments->hasMorePages(),
                    'from' => $comments->firstItem(),
                    'to' => $comments->lastItem()
                ]
            ]
        ]);
    }

    /**
     * Récupérer le feed des posts des utilisateurs suivis (following)
     * Route: GET /api/Y/feed/following
     */


    /**
     * Récupérer tous les posts d'une catégorie (via toutes ses sous-catégories)
     * Route: GET /api/Y/categories/{category_id}/posts
     */
    public function getCategoryPosts($categoryId, Request $request)
    {
        $page = $request->get('page', 1);
        $limit = min(100, max(1, (int) $request->get('limit', 20)));

        // Vérifier que la catégorie existe
        $category = \App\Models\Category::find($categoryId);
        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        // Récupérer toutes les sous-catégories de cette catégorie
        $subcategories = \App\Models\SubCategory::where('category_id', $categoryId)->get();
        $subcategoryIds = $subcategories->pluck('id')->toArray();

        if (empty($subcategoryIds)) {
            // Si la catégorie n'a pas de sous-catégories, retourner un résultat vide
            return response()->json([
                'success' => true,
                'data' => [
                    'category' => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'description' => $category->description,
                    ],
                    'subcategories' => [],
                    'posts' => [],
                    'posts_count' => 0,
                    'pagination' => [
                        'current_page' => 1,
                        'total_pages' => 0,
                        'total_items' => 0,
                        'per_page' => $limit,
                        'has_more' => false,
                        'from' => null,
                        'to' => null
                    ]
                ]
            ]);
        }

        // Récupérer les posts de toutes les sous-catégories avec pagination
        $posts = \App\Models\Post::whereIn('subcategory_id', $subcategoryIds)
            ->where('content_status', 'published')
            ->with([
                'user:id,first_name,last_name,email,profile_image,bio',
                'medias',
                'tags',
                'subcategory:id,name,category_id',
                'fandom:id,name'
            ])
            ->withCount(['favorites', 'comments'])
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        // Formater les posts
        $formattedPosts = collect($posts->items())->map(function ($post) {
            $user = $post->user;
            $subcategory = $post->subcategory;

            return [
                'id' => $post->id,
                'description' => $post->description,
                'content_status' => $post->content_status,
                'schedule_at' => $post->schedule_at,
                'created_at' => $post->created_at ? $post->created_at->toISOString() : null,
                'updated_at' => $post->updated_at ? $post->updated_at->toISOString() : null,
                'user' => $user ? [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => trim($user->first_name . ' ' . $user->last_name),
                    'email' => $user->email,
                    'profile_image' => $user->profile_image,
                    'bio' => $user->bio,
                ] : null,
                'subcategory' => $subcategory ? [
                    'id' => $subcategory->id,
                    'name' => $subcategory->name,
                    'category_id' => $subcategory->category_id,
                ] : null,
                'fandom' => $post->fandom ? [
                    'id' => $post->fandom->id,
                    'name' => $post->fandom->name,
                ] : null,
                'media' => $post->medias ? $post->medias->map(function($media) {
                    return [
                        'id' => $media->id,
                        'file_path' => $media->file_path,
                        'media_type' => $media->media_type,
                    ];
                })->toArray() : [],
                'tags' => $post->tags ? $post->tags->pluck('tag_name')->toArray() : [],
                'likes_count' => $post->favorites_count ?? 0,
                'comments_count' => $post->comments_count ?? 0,
                'feedback' => $post->feedback ?? 0,
            ];
        });

        // Formater les sous-catégories pour la réponse
        $formattedSubcategories = $subcategories->map(function ($subcategory) {
            return [
                'id' => $subcategory->id,
                'name' => $subcategory->name,
                'description' => $subcategory->description,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                ],
                'subcategories' => $formattedSubcategories,
                'posts' => $formattedPosts,
                'posts_count' => $posts->total(),
                'pagination' => [
                    'current_page' => $posts->currentPage(),
                    'total_pages' => $posts->lastPage(),
                    'total_items' => $posts->total(),
                    'per_page' => $posts->perPage(),
                    'has_more' => $posts->hasMorePages(),
                    'from' => $posts->firstItem(),
                    'to' => $posts->lastItem()
                ]
            ]
        ]);
    }

    /**
     * Récupérer tous les fandoms d'une catégorie (via toutes ses sous-catégories)
     * Route: GET /api/Y/categories/{category_id}/fandoms
     */
    public function getCategoryFandoms($categoryId, Request $request)
    {
        $page = $request->get('page', 1);
        $limit = min(100, max(1, (int) $request->get('limit', 20)));

        // Vérifier que la catégorie existe
        $category = \App\Models\Category::find($categoryId);
        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        // Récupérer toutes les sous-catégories de cette catégorie
        $subcategories = \App\Models\SubCategory::where('category_id', $categoryId)->get();
        $subcategoryIds = $subcategories->pluck('id')->toArray();

        if (empty($subcategoryIds)) {
            // Si la catégorie n'a pas de sous-catégories, retourner un résultat vide
            return response()->json([
                'success' => true,
                'data' => [
                    'category' => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'description' => $category->description,
                    ],
                    'subcategories' => [],
                    'fandoms' => [],
                    'fandoms_count' => 0,
                    'pagination' => [
                        'current_page' => 1,
                        'total_pages' => 0,
                        'total_items' => 0,
                        'per_page' => $limit,
                        'has_more' => false,
                        'from' => null,
                        'to' => null
                    ]
                ]
            ]);
        }

        // Récupérer les fandoms de toutes les sous-catégories avec pagination
        $fandoms = \App\Models\Fandom::whereIn('subcategory_id', $subcategoryIds)
            ->with([
                'subcategory:id,name,category_id'
            ])
            ->withCount(['members', 'posts'])
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        // Obtenir les rôles de l'utilisateur authentifié pour tous les fandoms s'il est connecté
        $user = Auth::user();
        $userMemberships = [];
        if ($user) {
            $fandomIds = collect($fandoms->items())->pluck('id')->toArray();
            $memberships = \App\Models\Member::where('user_id', $user->id)
                ->whereIn('fandom_id', $fandomIds)
                ->get();
            foreach ($memberships as $membership) {
                $userMemberships[$membership->fandom_id] = $membership->role;
            }
        }

        // Formater les fandoms
        $formattedFandoms = collect($fandoms->items())->map(function ($fandom) use ($userMemberships) {
            $subcategory = $fandom->subcategory;

            return [
                'id' => $fandom->id,
                'name' => $fandom->name,
                'description' => $fandom->description,
                'cover_image' => $fandom->cover_image,
                'logo_image' => $fandom->logo_image,
                'created_at' => $fandom->created_at ? $fandom->created_at->toISOString() : null,
                'updated_at' => $fandom->updated_at ? $fandom->updated_at->toISOString() : null,
                'subcategory' => $subcategory ? [
                    'id' => $subcategory->id,
                    'name' => $subcategory->name,
                    'category_id' => $subcategory->category_id,
                ] : null,
                'members_count' => $fandom->members_count ?? 0,
                'posts_count' => $fandom->posts_count ?? 0,
                'is_member' => isset($userMemberships[$fandom->id]),
                'member_role' => $userMemberships[$fandom->id] ?? null,
            ];
        });

        // Formater les sous-catégories pour la réponse
        $formattedSubcategories = $subcategories->map(function ($subcategory) {
            return [
                'id' => $subcategory->id,
                'name' => $subcategory->name,
                'description' => $subcategory->description,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                ],
                'subcategories' => $formattedSubcategories,
                'fandoms' => $formattedFandoms,
                'fandoms_count' => $fandoms->total(),
                'pagination' => [
                    'current_page' => $fandoms->currentPage(),
                    'total_pages' => $fandoms->lastPage(),
                    'total_items' => $fandoms->total(),
                    'per_page' => $fandoms->perPage(),
                    'has_more' => $fandoms->hasMorePages(),
                    'from' => $fandoms->firstItem(),
                    'to' => $fandoms->lastItem()
                ]
            ]
        ]);
    }

    /**
     * Récupérer tous les posts favoris de l'utilisateur authentifié
     * Route: GET /api/Y/favorites/posts
     */
    public function getFavoritePosts(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));

        // Récupérer les favoris de type Post avec pagination
        $favorites = Favorite::where('user_id', $user->id)
            ->where('favoriteable_type', 'App\\Models\\Post')
            ->with(['favoriteable.user', 'favoriteable.medias', 'favoriteable.tags'])
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        // Formater les posts favoris
        $formattedPosts = collect($favorites->items())->map(function ($favorite) {
            $post = $favorite->favoriteable;
            if (!$post) return null; // Post supprimé

            return [
                'id' => $post->id,
                'description' => $post->description,
                'content_status' => $post->content_status,
                'schedule_at' => $post->schedule_at,
                'category_id' => $post->category_id ?? null,
                'subcategory_id' => $post->subcategory_id ?? null,
                'fandom_id' => $post->fandom_id ?? null,
                'created_at' => $post->created_at ? $post->created_at->toISOString() : null,
                'updated_at' => $post->updated_at ? $post->updated_at->toISOString() : null,
                'favorited_at' => $favorite->created_at ? $favorite->created_at->toISOString() : null,
                'media' => method_exists($post, 'medias') ? $post->medias->pluck('file_path')->toArray() : [],
                'tags' => method_exists($post, 'tags') ? $post->tags->pluck('tag_name')->toArray() : [],
                'user' => $post->user ? [
                    'id' => $post->user->id,
                    'first_name' => $post->user->first_name,
                    'last_name' => $post->user->last_name,
                    'profile_image' => $post->user->profile_image,
                ] : null,
                'likes_count' => method_exists($post, 'favorites') ? $post->favorites()->count() : 0,
                'comments_count' => method_exists($post, 'comments') ? $post->comments()->count() : 0,
            ];
        })->filter(); // Supprimer les posts null

        return response()->json([
            'success' => true,
            'data' => [
                'posts' => $formattedPosts->values(),
                'pagination' => [
                    'current_page' => $favorites->currentPage(),
                    'total_pages' => $favorites->lastPage(),
                    'total_items' => $favorites->total(),
                    'per_page' => $favorites->perPage(),
                    'has_more' => $favorites->hasMorePages(),
                    'from' => $favorites->firstItem(),
                    'to' => $favorites->lastItem()
                ]
            ]
        ]);
    }

    /**
     * Récupérer tous les produits favoris de l'utilisateur authentifié
     * Route: GET /api/Y/favorites/products
     */
    public function getFavoriteProducts(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));

        // Récupérer les favoris de type Product avec pagination
        $favorites = Favorite::where('user_id', $user->id)
            ->where('favoriteable_type', 'App\\Models\\Product')
            ->with(['favoriteable'])
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        // Formater les produits favoris
        $formattedProducts = collect($favorites->items())->map(function ($favorite) {
            $product = $favorite->favoriteable;
            if (!$product) return null; // Produit supprimé

            return [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'image' => $product->image,
                'category_id' => $product->category_id ?? null,
                'subcategory_id' => $product->subcategory_id ?? null,
                'stock_quantity' => $product->stock_quantity ?? 0,
                'created_at' => $product->created_at ? $product->created_at->toISOString() : null,
                'updated_at' => $product->updated_at ? $product->updated_at->toISOString() : null,
                'favorited_at' => $favorite->created_at ? $favorite->created_at->toISOString() : null,
                'rating_average' => method_exists($product, 'ratings') ? round($product->ratings()->avg('evaluation') ?? 0, 2) : 0,
                'rating_count' => method_exists($product, 'ratings') ? $product->ratings()->count() : 0,
            ];
        })->filter(); // Supprimer les produits null

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $formattedProducts->values(),
                'pagination' => [
                    'current_page' => $favorites->currentPage(),
                    'total_pages' => $favorites->lastPage(),
                    'total_items' => $favorites->total(),
                    'per_page' => $favorites->perPage(),
                    'has_more' => $favorites->hasMorePages(),
                    'from' => $favorites->firstItem(),
                    'to' => $favorites->lastItem()
                ]
            ]
        ]);
    }



}
