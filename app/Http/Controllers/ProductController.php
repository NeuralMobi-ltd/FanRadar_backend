<?php

namespace App\Http\Controllers;

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
}
