<?php

namespace App\Http\Controllers;

use App\Models\Rating;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RatingController extends Controller
{
    /**
     * Ajouter ou mettre à jour une évaluation
     * Route: POST /api/ratings
     */
    public function addOrUpdateRating(Request $request)
    {
        $request->validate([
            'rateable_id' => 'required|integer',
            'rateable_type' => 'nullable|string|in:post,product',
            'evaluation' => 'required|integer|min:0|max:5',
            'commentaire' => 'nullable|string|max:1000',
        ]);

        // Récupérer l'utilisateur authentifié
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'User not authenticated'
            ], 401);
        }

        // Définir le type par défaut comme Product et convertir en nom de classe complet
        $rateableType = $request->rateable_type ?? 'product';
        $modelClass = $rateableType === 'post' ? Post::class : Product::class;

        // Vérifier que l'élément existe
        $item = $modelClass::find($request->rateable_id);
        if (!$item) {
            return response()->json([
                'success' => false,
                'error' => ucfirst($rateableType) . ' not found'
            ], 404);
        }

        // Rechercher ou créer le rating
        $rating = Rating::updateOrCreate(
            [
                'user_id' => $user->id,
                'rateable_id' => $request->rateable_id,
                'rateable_type' => $modelClass,
            ],
            [
                'evaluation' => $request->evaluation,
                'commentaire' => $request->commentaire,
            ]
        );

        // Calculer les statistiques
        $averageRating = Rating::where('rateable_id', $request->rateable_id)
            ->where('rateable_type', $modelClass)
            ->avg('evaluation');

        $totalRatings = Rating::where('rateable_id', $request->rateable_id)
            ->where('rateable_type', $modelClass)
            ->count();

        return response()->json([
            'success' => true,
            'message' => $rating->wasRecentlyCreated ? 'Rating added successfully' : 'Rating updated successfully',
            'data' => [
                'rating' => $rating,
                'statistics' => [
                    'average_rating' => round($averageRating, 1),
                    'total_ratings' => $totalRatings,
                ]
            ]
        ], $rating->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Supprimer une évaluation
     * Route: DELETE /api/ratings
     */
    public function deleteRating(Request $request)
    {
        $request->validate([
            'rateable_id' => 'required|integer',
            'rateable_type' => 'nullable|string|in:post,product',
        ]);

        // Récupérer l'utilisateur authentifié
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'User not authenticated'
            ], 401);
        }

        // Définir le type par défaut comme Product
        $rateableType = $request->rateable_type ?? 'product';
        $modelClass = $rateableType === 'post' ? Post::class : Product::class;

        $rating = Rating::where([
            'user_id' => $user->id,
            'rateable_id' => $request->rateable_id,
            'rateable_type' => $modelClass,
        ])->first();

        if (!$rating) {
            return response()->json([
                'success' => false,
                'error' => 'Rating not found'
            ], 404);
        }

        $rating->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rating deleted successfully'
        ]);
    }

    /**
     * Obtenir toutes les évaluations d'un élément
     * Route: GET /api/ratings/{type}/{id}
     */
    public function getItemRatings($type, $id, Request $request)
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

        // Paramètres de pagination
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);

        // Récupérer les ratings avec pagination
        $ratings = Rating::with('user:id,first_name,last_name,profile_image')
            ->where('rateable_id', $id)
            ->where('rateable_type', $modelClass)
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        // Calculer les statistiques
        $statistics = $this->calculateRatingStatistics($id, $modelClass);

        return response()->json([
            'success' => true,
            'data' => [
                'ratings' => $ratings->map(function ($rating) {
                    return [
                        'id' => $rating->id,
                        'evaluation' => $rating->evaluation,
                        'commentaire' => $rating->commentaire,
                        'user' => [
                            'id' => $rating->user->id,
                            'name' => trim($rating->user->first_name . ' ' . $rating->user->last_name),
                            'avatar' => $rating->user->profile_image,
                        ],
                        'created_at' => $rating->created_at,
                    ];
                }),
                'statistics' => $statistics,
                'pagination' => [
                    'current_page' => $ratings->currentPage(),
                    'last_page' => $ratings->lastPage(),
                    'per_page' => $ratings->perPage(),
                    'total' => $ratings->total(),
                ]
            ]
        ]);
    }

    /**
     * Obtenir les évaluations d'un utilisateur
     * Route: GET /api/users/{userId}/ratings
     */
    public function getUserRatings($userId, Request $request)
    {
        $user = User::find($userId);
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'User not found'
            ], 404);
        }

        $type = $request->get('type'); // post, product ou null pour tous

        $query = Rating::with('rateable')
            ->where('user_id', $userId);

        if ($type && in_array($type, ['post', 'product'])) {
            $modelClass = $type === 'post' ? Post::class : Product::class;
            $query->where('rateable_type', $modelClass);
        }

        $ratings = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'ratings' => $ratings->map(function ($rating) {
                    $item = $rating->rateable;
                    $type = $rating->rateable_type === Post::class ? 'post' : 'product';

                    return [
                        'id' => $rating->id,
                        'evaluation' => $rating->evaluation,
                        'commentaire' => $rating->commentaire,
                        'type' => $type,
                        'item' => [
                            'id' => $item->id,
                            'title' => $type === 'post' ? $item->title : $item->product_name,
                            'description' => $type === 'post' ? ($item->body ?? $item->content) : $item->description,
                        ],
                        'created_at' => $rating->created_at,
                    ];
                }),
                'total' => $ratings->count(),
            ]
        ]);
    }

    /**
     * Obtenir les statistiques de rating d'un élément
     * Route: GET /api/ratings/{type}/{id}/statistics
     */
    public function getItemRatingStatistics($type, $id)
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

        $statistics = $this->calculateRatingStatistics($id, $modelClass);

        return response()->json([
            'success' => true,
            'data' => $statistics
        ]);
    }

    /**
     * Calculer les statistiques de rating
     */
    private function calculateRatingStatistics($itemId, $modelClass)
    {
        $ratings = Rating::where('rateable_id', $itemId)
            ->where('rateable_type', $modelClass)
            ->get();

        $total = $ratings->count();
        $average = $total > 0 ? $ratings->avg('evaluation') : 0;

        // Distribution par étoiles
        $distribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $count = $ratings->where('evaluation', $i)->count();
            $distribution[$i] = [
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0
            ];
        }

        return [
            'average_rating' => round($average, 1),
            'total_ratings' => $total,
            'distribution' => $distribution,
            'summary' => [
                'excellent' => $ratings->where('evaluation', 5)->count(), // 5 étoiles
                'good' => $ratings->where('evaluation', 4)->count(),      // 4 étoiles
                'average' => $ratings->where('evaluation', 3)->count(),   // 3 étoiles
                'poor' => $ratings->where('evaluation', 2)->count(),      // 2 étoiles
                'terrible' => $ratings->where('evaluation', 1)->count(),  // 1 étoile
            ]
        ];
    }





}
