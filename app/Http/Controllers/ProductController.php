<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    // ✅ GET /api/products — Liste paginée des produits avec leurs médias
    public function index()
    {
        $products = Product::with(['medias', 'subcategory'])
            ->withCount(['ratings' => function($query) {
                $query->where('rateable_type', Product::class);
            }])
            ->withAvg(['ratings' => function($query) {
                $query->where('rateable_type', Product::class);
            }], 'evaluation')
            ->paginate(10);

        // Formater les données pour inclure les informations de rating
        $products->getCollection()->transform(function ($product) {
            $product->ratings_count = $product->ratings_count ?? 0;
            $product->ratings_average = $product->ratings_avg_evaluation ? round($product->ratings_avg_evaluation, 1) : 0;

            // Ajouter isWishlisted seulement si l'utilisateur est connecté
            $user = Auth::user();
            if ($user) {
                $product->isWishlisted = $product->favorites()
                    ->where('user_id', $user->id)
                    ->exists();
            }

            // Supprimer le champ technique Laravel
            unset($product->ratings_avg_evaluation);

            return $product;
        });

        return response()->json($products);
    }

    // ✅ POST /api/products — Création d’un produit + médias
   public function store(Request $request)
{
    // Validation de base, sans medias.*.file_path en string mais en fichiers
    $validated = $request->validate([
        'product_name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'price' => 'required|numeric|min:0',
        'stock' => 'required|integer|min:0',
        'promotion' => 'nullable|integer|min:0|max:100',
        'user_id' => 'required|exists:users,id',
        'subcategory_id' => 'nullable|exists:subcategories,id',
        'type' => 'nullable|string|max:100',
        'content_status' => 'nullable|in:active,inactive,draft',
        'sale_start_date' => 'nullable|date',
        'sale_end_date' => 'nullable|date|after_or_equal:sale_start_date',
        'medias' => 'nullable|array',
    ]);

    $product = Product::create(array_merge($validated, [
        'content_status' => $validated['content_status'] ?? 'active',
        'revenue' => ($validated['price'] * $validated['stock']), // Calcul automatique du revenue
    ]));

    // Gestion de l'upload des fichiers médias
    if ($request->hasFile('medias')) {
        foreach ($request->file('medias') as $file) {
            // Détection automatique du type media via extension mime
            $mimeType = $file->getMimeType();
            $mediaType = str_starts_with($mimeType, 'image/') ? 'image' : 'video';

            // Dossier selon type media
            $folder = $mediaType === 'image' ? 'products/images' : 'products/videos';

            // Stockage du fichier dans public disk
            $path = $file->store($folder, 'public');

            // Création de l'enregistrement media lié au produit
            $product->medias()->create([
                'file_path' => $path,
                'media_type' => $mediaType,
            ]);
        }
    }

    return response()->json([
        'message' => 'Produit créé avec succès.',
        'product' => $product->load(['medias', 'subcategory'])
    ], 201);
}

    // ✅ GET /api/products/{product} — Afficher un seul produit
    public function show(Product $product)
    {
        // Charger le produit avec toutes ses relations
        $product->load([
            'medias',
            'subcategory',
            'ratings' => function($query) {
                $query->with('user:id,first_name,last_name,email,profile_image')
                      ->orderBy('created_at', 'desc');
            }
        ]);

        // Calculer les statistiques de rating
        $ratingsCount = $product->ratings->count();
        $ratingsAverage = $ratingsCount > 0 ? round($product->ratings->avg('evaluation'), 1) : 0;

        // Formater les ratings pour la réponse
        $formattedRatings = $product->ratings->map(function ($rating) {
            return [
                'id' => $rating->id,
                'evaluation' => $rating->evaluation,
                'commentaire' => $rating->commentaire,
                'created_at' => $rating->created_at ? $rating->created_at->toISOString() : null,
                'user' => $rating->user ? [
                    'id' => $rating->user->id,
                    'first_name' => $rating->user->first_name,
                    'last_name' => $rating->user->last_name,
                    'full_name' => trim($rating->user->first_name . ' ' . $rating->user->last_name),
                    'email' => $rating->user->email,
                    'profile_image' => $rating->user->profile_image,
                ] : null,
            ];
        });

        // Préparer la réponse avec les statistiques de rating
        $productData = $product->toArray();
        $productData['ratings_count'] = $ratingsCount;
        $productData['ratings_average'] = $ratingsAverage;

        // Ajouter isWishlisted seulement si l'utilisateur est connecté
        $user = Auth::user();
        if ($user) {
            $productData['isWishlisted'] = $product->favorites()
                ->where('user_id', $user->id)
                ->exists();
        }

        $productData['ratings'] = $formattedRatings;

        // Supprimer les relations non formatées
        unset($productData['ratings']);
        $productData['ratings'] = $formattedRatings;

        return response()->json($productData);
    }

    // ✅ PUT/PATCH /api/products/{product} — Modifier un produit (pas les médias ici)
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'product_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'promotion' => 'nullable|integer|min:0|max:100',
            'subcategory_id' => 'nullable|exists:subcategories,id',
            'type' => 'nullable|string|max:100',
            'content_status' => 'nullable|in:active,inactive,draft',
            'sale_start_date' => 'nullable|date',
            'sale_end_date' => 'nullable|date|after_or_equal:sale_start_date',
        ]);

        // Recalculer le revenue si price ou stock changent
        if (isset($validated['price']) || isset($validated['stock'])) {
            $price = $validated['price'] ?? $product->price;
            $stock = $validated['stock'] ?? $product->stock;
            $validated['revenue'] = $price * $stock;
        }

        $product->update($validated);

        return response()->json([
            'message' => 'Produit mis à jour.',
            'product' => $product->load(['medias', 'subcategory'])
        ]);
    }

    // ✅ DELETE /api/products/{product} — Supprimer produit + médias
    public function destroy(Product $product)
    {
        $product->medias()->delete(); // Supprime les médias liés
        $product->delete();

        return response()->json([
            'message' => 'Produit et ses médias supprimés.'
        ]);
    }


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
     * Rechercher des produits par nom et description
     * Route: GET /api/Y/search/products
     */
    public function searchProducts(Request $request)
    {
        $query = $request->get('q', '');
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 10);

        // Valider les paramètres
        if (empty($query)) {
            return response()->json([
                'success' => false,
                'message' => 'Search query is required'
            ], 400);
        }

        if ($perPage > 50) {
            $perPage = 50; // Limiter à 50 résultats par page maximum
        }

        $user = Auth::user();

        // Recherche dans les produits par nom et description
        $products = Product::where(function($q) use ($query) {
                $q->where('product_name', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%");
            })
            ->where('content_status', 'published') // Seulement les produits publiés
            ->with(['medias', 'subcategory:id,name', 'tags'])
            ->withCount(['ratings' => function($ratingQuery) {
                $ratingQuery->where('rateable_type', Product::class);
            }])
            ->withAvg(['ratings' => function($ratingQuery) {
                $ratingQuery->where('rateable_type', Product::class);
            }], 'evaluation')
            ->orderBy('product_name', 'asc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Formater les données des produits
        $formattedProducts = $products->getCollection()->map(function ($product) use ($user) {
            // Vérifier si le produit est dans les favoris de l'utilisateur
            $isFavorite = false;
            if ($user) {
                $isFavorite = Favorite::where([
                    'user_id' => $user->id,
                    'favoriteable_id' => $product->id,
                    'favoriteable_type' => Product::class,
                ])->exists();
            }

            return [
                'id' => $product->id,
                'product_name' => $product->product_name,
                'description' => $product->description,
                'price' => $product->price,
                'promotion' => $product->promotion,
                'stock' => $product->stock,
                'content_status' => $product->content_status,
                'type' => $product->type,
                'revenue' => $product->revenue,
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
                'average_rating' => $product->ratings_avg_evaluation ? round($product->ratings_avg_evaluation, 1) : 0,
                'ratings_count' => $product->ratings_count ?? 0,
                'is_favorite' => $isFavorite,
                'sale_start_date' => $product->sale_start_date,
                'sale_end_date' => $product->sale_end_date,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'query' => $query,
                'products' => $formattedProducts,
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'total_pages' => $products->lastPage(),
                    'total_items' => $products->total(),
                    'per_page' => $products->perPage(),
                    'has_next' => $products->hasMorePages(),
                    'has_previous' => $products->currentPage() > 1,
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem()
                ]
            ]
        ]);
    }
}
