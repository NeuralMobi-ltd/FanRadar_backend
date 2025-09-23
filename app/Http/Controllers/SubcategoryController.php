<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Http\Request;

class SubcategoryController extends Controller
{
    // Afficher toutes les sous-catégories
    public function index()
    {
        return response()->json(Subcategory::with('category')->get());
    }

    // Créer une nouvelle sous-catégorie
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
        ]);

        $subcategory = Subcategory::create($validated);

        return response()->json([
            'message' => 'Sous-catégorie créée avec succès.',
            'subcategory' => $subcategory
        ], 201);
    }

    // Afficher une seule sous-catégorie
    public function show($id)
    {
        $subcategory = Subcategory::with('category')->find($id);

        if (!$subcategory) {
            return response()->json(['message' => 'Sous-catégorie non trouvée'], 404);
        }

        return response()->json($subcategory);
    }

    // Mettre à jour une sous-catégorie
    public function update(Request $request, $id)
    {
        $subcategory = Subcategory::find($id);

        if (!$subcategory) {
            return response()->json(['message' => 'Sous-catégorie non trouvée'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'category_id' => 'sometimes|required|exists:categories,id',
        ]);

        $subcategory->update($validated);

        return response()->json([
            'message' => 'Sous-catégorie mise à jour avec succès.',
            'subcategory' => $subcategory
        ]);
    }

    // Supprimer une sous-catégorie
    public function destroy($id)
    {
        $subcategory = Subcategory::find($id);

        if (!$subcategory) {
            return response()->json(['message' => 'Sous-catégorie non trouvée'], 404);
        }

        $subcategory->delete();

        return response()->json(['message' => 'Sous-catégorie supprimée avec succès.']);
    }



    public function getSubcategoryContent($subcategoryId)
    {
        // Récupérer la sous-catégorie avec sa catégorie parent
        $subcategory = \App\Models\Subcategory::with(['category'])->find($subcategoryId);

        if (!$subcategory) {
            return response()->json([
                'success' => false,
                'message' => 'Sous-catégorie introuvable'
            ], 404);
        }

        // Récupérer tous les posts associés à cette sous-catégorie avec leurs relations
        $posts = $subcategory->posts()->with([
            'user:id,first_name,last_name,profile_image',
            'tags:id,tag_name',
            'medias:id,file_path,media_type,mediable_id,mediable_type' // Inclure les médias
        ])->get();

        // Formatter la réponse
        $response = [
            'success' => true,
            'data' => [
                'subcategory' => [
                    'id' => $subcategory->id,
                    'name' => $subcategory->name,
                    'category' => $subcategory->category ? [
                        'id' => $subcategory->category->id,
                        'name' => $subcategory->category->name
                    ] : null,
                    'created_at' => $subcategory->created_at,
                    'updated_at' => $subcategory->updated_at
                ],
                'posts' => $posts->map(function ($post) {
                    return [
                        'id' => $post->id,
                        'description' => $post->description,
                        'feedback' => $post->feedback,
                        'schedule_at' => $post->schedule_at,
                        'content_status' => $post->content_status,
                        'media' => $post->media, // Champ media (array)
                        'user' => $post->user ? [
                            'id' => $post->user->id,
                            'first_name' => $post->user->first_name,
                            'last_name' => $post->user->last_name,
                            'profile_image' => $post->user->profile_image
                        ] : null,
                        'tags' => $post->tags->pluck('tag_name')->toArray(), // Tableau simple des noms
                        'medias' => $post->medias->map(function ($media) {
                            return [
                                'id' => $media->id,
                                'file_path' => $media->file_path,
                                'media_type' => $media->media_type
                            ];
                        }), // Médias polymorphes
                         'likes_count' => method_exists($post, 'favorites') ? $post->favorites()->count() : 0,
                         'comments_count' => method_exists($post, 'comments') ? $post->comments()->count() : 0,
                        'created_at' => $post->created_at,
                        'updated_at' => $post->updated_at
                    ];
                }),
                'posts_count' => $posts->count()
            ]
        ];

        return response()->json($response, 200);
    }


public function getSubcategoryFandoms($subcategoryId)
    {
        // Récupérer la sous-catégorie avec sa catégorie parent
        $subcategory = \App\Models\Subcategory::with(['category'])->find($subcategoryId);

        if (!$subcategory) {
            return response()->json([
                'success' => false,
                'message' => 'Sous-catégorie introuvable'
            ], 404);
        }

        // Récupérer tous les fandoms associés à cette sous-catégorie avec le nombre de membres et de posts
        $fandoms = \App\Models\Fandom::where('subcategory_id', $subcategoryId)
            ->withCount(['members', 'posts'])
            ->get();

        // Formater les fandoms
        $formattedFandoms = $fandoms->map(function ($fandom) {
            // Récupérer toutes les données de la table fandom
            $fandomData = $fandom->toArray();

            // Ajouter les compteurs
            $fandomData['members_count'] = $fandom->members_count ?? 0;
            $fandomData['posts_count'] = $fandom->posts_count ?? 0;

            return $fandomData;
        });

        // Formatter la réponse
        $response = [
            'success' => true,
            'data' => [
                'subcategory' => [
                    'id' => $subcategory->id,
                    'name' => $subcategory->name,
                    'description' => $subcategory->description,
                    'category' => $subcategory->category ? [
                        'id' => $subcategory->category->id,
                        'name' => $subcategory->category->name
                    ] : null
                ],
                'fandoms' => $formattedFandoms,
                'fandoms_count' => $fandoms->count()
            ]
        ];

        return response()->json($response, 200);
    }



     public function getCategorySubcategories($category_id)
    {
        $category = Category::find($category_id);
        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        $subcategories = \App\Models\Subcategory::where('category_id', $category_id)->get();

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
     * Retourne le nombre de médias groupés par type (image, video).
     */
    public function getMediacount()
    {
        $counts = \App\Models\Media::select('media_type')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('media_type')
            ->get();

        return response()->json([
            'success' => true,
            'media_counts' => $counts
        ]);
    }

}
