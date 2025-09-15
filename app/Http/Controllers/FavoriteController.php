<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    /**
     * Ajouter un élément aux favoris (Post ou Product)
     * Route: POST /api/favorites
     */
    public function addToFavorites(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'favoriteable_id' => 'required|integer',
            'favoriteable_type' => 'required|string|in:post,product',
        ]);

        // Convertir le type en nom de classe complet
        $modelClass = $request->favoriteable_type === 'post' ? Post::class : Product::class;

        // Vérifier que l'élément existe
        $item = $modelClass::find($request->favoriteable_id);
        if (!$item) {
            return response()->json([
                'success' => false,
                'error' => ucfirst($request->favoriteable_type) . ' not found'
            ], 404);
        }

        // Vérifier si déjà en favori
        $existingFavorite = Favorite::where([
            'user_id' => $request->user_id,
            'favoriteable_id' => $request->favoriteable_id,
            'favoriteable_type' => $modelClass,
        ])->first();

        if ($existingFavorite) {
            return response()->json([
                'success' => false,
                'message' => 'Item already in favorites'
            ], 409);
        }

        // Créer le favori
        $favorite = Favorite::create([
            'user_id' => $request->user_id,
            'favoriteable_id' => $request->favoriteable_id,
            'favoriteable_type' => $modelClass,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Added to favorites successfully',
            'data' => $favorite
        ], 201);
    }

    /**
     * Retirer un élément des favoris
     * Route: DELETE /api/favorites
     */
    public function removeFromFavorites(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'favoriteable_id' => 'required|integer',
            'favoriteable_type' => 'required|string|in:post,product',
        ]);

        $modelClass = $request->favoriteable_type === 'post' ? Post::class : Product::class;

        $favorite = Favorite::where([
            'user_id' => $request->user_id,
            'favoriteable_id' => $request->favoriteable_id,
            'favoriteable_type' => $modelClass,
        ])->first();

        if (!$favorite) {
            return response()->json([
                'success' => false,
                'error' => 'Favorite not found'
            ], 404);
        }

        $favorite->delete();

        return response()->json([
            'success' => true,
            'message' => 'Removed from favorites successfully'
        ]);
    }

    /**
     * Obtenir tous les favoris d'un utilisateur
     * Route: GET /api/users/{userId}/favorites
     */
    public function getUserFavorites($userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'User not found'
            ], 404);
        }

        $favorites = Favorite::with('favoriteable')
            ->where('user_id', $userId)
            ->get()
            ->map(function ($favorite) {
                $item = $favorite->favoriteable;
                $type = $favorite->favoriteable_type === Post::class ? 'post' : 'product';

                $data = [
                    'id' => $favorite->id,
                    'type' => $type,
                    'item_id' => $favorite->favoriteable_id,
                    'added_at' => $favorite->created_at,
                ];

                if ($type === 'post') {
                    $data['item'] = [
                        'id' => $item->id,
                        'title' => $item->title,
                        'content' => $item->body ?? $item->content,
                        'author' => $item->user->first_name ?? 'Unknown',
                        'created_at' => $item->created_at,
                    ];
                } else {
                    $data['item'] = [
                        'id' => $item->id,
                        'name' => $item->product_name,
                        'description' => $item->description,
                        'price' => $item->price,
                        'stock' => $item->stock,
                        'created_at' => $item->created_at,
                    ];
                }

                return $data;
            });

        return response()->json([
            'success' => true,
            'data' => $favorites
        ]);
    }

    /**
     * Obtenir les favoris par type (posts ou products)
     * Route: GET /api/users/{userId}/favorites/{type}
     */
    public function getUserFavoritesByType($userId, $type)
    {
        if (!in_array($type, ['posts', 'products'])) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid type. Use posts or products'
            ], 400);
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'User not found'
            ], 404);
        }

        $modelClass = $type === 'posts' ? Post::class : Product::class;

        $favorites = Favorite::with('favoriteable')
            ->where('user_id', $userId)
            ->where('favoriteable_type', $modelClass)
            ->get()
            ->map(function ($favorite) use ($type) {
                $item = $favorite->favoriteable;

                if ($type === 'posts') {
                    return [
                        'favorite_id' => $favorite->id,
                        'post' => [
                            'id' => $item->id,
                            'title' => $item->title,
                            'content' => $item->body ?? $item->content,
                            'author' => $item->user->first_name ?? 'Unknown',
                            'likes' => $item->likes ?? 0,
                            'created_at' => $item->created_at,
                        ],
                        'added_at' => $favorite->created_at,
                    ];
                } else {
                    return [
                        'favorite_id' => $favorite->id,
                        'product' => [
                            'id' => $item->id,
                            'name' => $item->product_name,
                            'description' => $item->description,
                            'price' => $item->price,
                            'stock' => $item->stock,
                            'type' => $item->type,
                            'created_at' => $item->created_at,
                        ],
                        'added_at' => $favorite->created_at,
                    ];
                }
            });

        return response()->json([
            'success' => true,
            'data' => $favorites
        ]);
    }



    /**
     * Obtenir les utilisateurs qui ont mis un élément en favori
     * Route: GET /api/favorites/{type}/{id}/users
     */
    public function getItemFavoriteUsers($type, $id)
    {
        if (!in_array($type, ['post', 'product'])) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid type. Use post or product'
            ], 400);
        }

        $modelClass = $type === 'post' ? Post::class : Product::class;

        // Vérifier que l'élément existe
        $item = $modelClass::find($id);
        if (!$item) {
            return response()->json([
                'success' => false,
                'error' => ucfirst($type) . ' not found'
            ], 404);
        }

        $favorites = Favorite::with('user:id,first_name,last_name,email')
            ->where('favoriteable_id', $id)
            ->where('favoriteable_type', $modelClass)
            ->get()
            ->map(function ($favorite) {
                return [
                    'user' => [
                        'id' => $favorite->user->id,
                        'name' => trim($favorite->user->first_name . ' ' . $favorite->user->last_name),
                        'email' => $favorite->user->email,
                    ],
                    'added_at' => $favorite->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'item_type' => $type,
            'item_id' => $id,
            'favorites_count' => $favorites->count(),
            'data' => $favorites
        ]);
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

        // Utiliser firstOrCreate pour éviter les race conditions
        $favorite = Favorite::firstOrCreate([
            'user_id' => $user->id,
            'favoriteable_id' => $post->id,
            'favoriteable_type' => 'App\\Models\\Post',
        ]);

        // Si $favorite->wasRecentlyCreated est false, cela signifie qu'il existait déjà
        if (!$favorite->wasRecentlyCreated) {
            return response()->json([
                'success' => false,
                'message' => 'Ce post est déjà dans vos favoris.'
            ], 409);
        }

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

        // Utiliser firstOrCreate pour éviter les race conditions
        $favorite = Favorite::firstOrCreate([
            'user_id' => $user->id,
            'favoriteable_id' => $product->id,
            'favoriteable_type' => 'App\\Models\\Product',
        ]);

        // Si $favorite->wasRecentlyCreated est false, cela signifie qu'il existait déjà
        if (!$favorite->wasRecentlyCreated) {
            return response()->json([
                'success' => false,
                'message' => 'Ce produit est déjà dans vos favoris.'
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Le produit a été ajouté aux favoris avec succès.'
        ], 201);
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
}
