<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Post;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PostController extends Controller
{
    // Lister les posts avec user et médias
    public function index()
    {
        $posts = Post::with(['user', 'medias'])->paginate(10);
        return response()->json($posts);
    }

    // Créer un nouveau post + médias
   public function store(Request $request)
{
    $validated = $request->validate([
        'schedule_at' => 'nullable|date',
        'description' => 'nullable|string',
        'subcategory_id' => 'nullable|integer|exists:subcategories,id',
        'content_status' => 'required|in:draft,published,archived',
        'user_id' => 'required|exists:users,id',
        'medias' => 'nullable|array',
        'medias.*' => 'file|mimes:jpg,jpeg,png,mp4,mov|max:20480',
    ]);

    $post = Post::create($validated);

    if ($request->hasFile('medias')) {
        foreach ($request->file('medias') as $file) {
            $extension = strtolower($file->getClientOriginalExtension());

            // Détecte type média selon extension
            $imageExtensions = ['jpg', 'jpeg', 'png'];
            $videoExtensions = ['mp4', 'mov'];

            if (in_array($extension, $imageExtensions)) {
                $mediaType = 'image';
                $folder = 'posts/images';
            } elseif (in_array($extension, $videoExtensions)) {
                $mediaType = 'video';
                $folder = 'posts/videos';
            } else {
                // Extension non supportée (ne devrait pas arriver à cause de la validation)
                continue;
            }

            $path = $file->store($folder, 'public');

            $post->medias()->create([
                'file_path' => $path,
                'media_type' => $mediaType,
            ]);
        }
    }

    return response()->json([
        'message' => 'Post créé avec succès.',
        'post' => $post->load('medias', 'user')
    ], 201);
}



    // Afficher un post spécifique avec relations
    public function show(Post $post)
    {



        $post->load('user', 'medias', 'tags');
        $post->loadCount(['favorites', 'comments']);

        // Charger les commentaires avec les utilisateurs
        $post->load(['comments' => function($query) {
            $query->with('user:id,first_name,last_name,profile_image')
                  ->orderBy('created_at', 'desc');
        }]);

        // Vérifier si le post est en favoris pour l'utilisateur authentifié
        $authUser = Auth::user();

        $isFavorite = false;
        if ($authUser) {
            $isFavorite = Favorite::where([
                'user_id' => $authUser->id,
                'favoriteable_type' => 'App\\Models\\Post',
                'favoriteable_id' => $post->id
            ])->exists();
        }

        // Formater les commentaires
        $formattedComments = $post->comments->map(function ($comment) {
            return [
                'id' => $comment->id,
                'content' => $comment->content,
                'created_at' => $comment->created_at ? $comment->created_at->toISOString() : null,
                'updated_at' => $comment->updated_at ? $comment->updated_at->toISOString() : null,
                'user' => $comment->user ? [
                    'id' => $comment->user->id,
                    'first_name' => $comment->user->first_name,
                    'last_name' => $comment->user->last_name,
                    'full_name' => trim($comment->user->first_name . ' ' . $comment->user->last_name),
                    'profile_image' => $comment->user->profile_image,
                ] : null,
            ];
        });

        // Formater la réponse
        $postData = [
            'id' => $post->id,
            'description' => $post->description,
            'content_status' => $post->content_status,
            'schedule_at' => $post->schedule_at,
            'category_id' => $post->category_id ?? null,
            'subcategory_id' => $post->subcategory_id ?? null,
            'fandom_id' => $post->fandom_id ?? null,
            'created_at' => $post->created_at,
            'updated_at' => $post->updated_at,
            'user' => $post->user ? [
                'id' => $post->user->id,
                'first_name' => $post->user->first_name,
                'last_name' => $post->user->last_name,
                'email' => $post->user->email,
                'profile_image' => $post->user->profile_image,
            ] : null,
            'media' => $post->medias ? $post->medias->pluck('file_path')->toArray() : [],
            'tags' => $post->tags ? $post->tags->pluck('tag_name')->toArray() : [],
            'likes_count' => $post->favorites_count ?? 0,
            'comments_count' => $post->comments_count ?? 0,
            'is_favorite' => $isFavorite,
            'comments' => $formattedComments,
        ];

        return response()->json([
            'success' => true,
            'data' => $postData
        ]);
    }

    // Mettre à jour un post (sans changer les médias ici)
    public function update(Request $request, Post $post)
    {
        $validated = $request->validate([
            'schedule_at' => 'nullable|date',
            'description' => 'nullable|string',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
            'content_status' => 'required|in:draft,published,archived',
        ]);

        $post->update($validated);

        return response()->json([
            'message' => 'Post mis à jour.',
            'post' => $post->load('medias', 'user')
        ]);
    }

    // Supprimer un post (et ses médias)
    public function destroy(Post $post)
    {
        foreach ($post->medias as $media) {
            Storage::disk('public')->delete($media->file_path);
        }
        $post->medias()->delete();
        $post->delete();

        return response()->json([
            'message' => 'Post et ses médias supprimés.'
        ]);
    }




    public function getUserPosts($userId, Request $request)
        {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);

            $authUser = Auth::user();

            $posts = Post::where('user_id', $userId)
                ->with(['medias', 'tags'])
                ->withCount(['favorites', 'comments'])
                ->latest()
                ->paginate($limit, ['*'], 'page', $page);

            $formattedPosts = collect($posts->items())->map(function ($post) use ($authUser) {
                $isFavorite = false;
                if ($authUser) {
                    $isFavorite = Favorite::where([
                        'user_id' => $authUser->id,
                        'favoriteable_type' => 'App\\Models\\Post',
                        'favoriteable_id' => $post->id
                    ])->exists();
                }

                return [
                    'id' => $post->id,
                    'description' => $post->description,
                    'content_status' => $post->content_status,
                    'schedule_at' => $post->schedule_at,
                    'category_id' => $post->category_id ?? null,
                    'created_at' => $post->created_at,
                    'updated_at' => $post->updated_at,
                    'media' => method_exists($post, 'medias') ? $post->medias->pluck('file_path')->toArray() : [],
                    'tags' => method_exists($post, 'tags') ? $post->tags->pluck('tag_name')->toArray() : [],
                    'likes_count' => $post->favorites_count ?? 0,
                    'comments_count' => $post->comments_count ?? 0,
                    'is_favorite' => $isFavorite,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'posts' => $formattedPosts,
                    'pagination' => [
                        'page' => $posts->currentPage(),
                        'limit' => $posts->perPage(),
                        'total' => $posts->total(),
                        'pages' => $posts->lastPage()
                    ]
                ]
            ]);
        }


    public function createPost(Request $request)
    {
        $validated = $request->validate([

            'schedule_at' => 'nullable|date',
            'description' => 'nullable|string',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
            'content_status' => 'required|in:draft,published,archived',
            'medias' => 'nullable|array',
            'medias.*' => 'file|mimes:jpg,jpeg,png,mp4,mov|max:20480',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:255',
        ]);

        $user = Auth::user();
        $validated['user_id'] = $user->id;
        $tags = $validated['tags'] ?? [];
        unset($validated['tags']);
        $post = Post::create($validated);

        // Associer les tags si fournis
        if (!empty($tags)) {
            foreach ($tags as $tagName) {
                $tag = \App\Models\Tag::firstOrCreate(['tag_name' => $tagName]);
                $post->tags()->syncWithoutDetaching($tag->id);
            }
        }

        $mediaFiles = $request->file('medias');
        if (is_iterable($mediaFiles)) {
            foreach ($mediaFiles as $file) {
                $extension = strtolower($file->getClientOriginalExtension());

                // Détecte type média selon extension
                $imageExtensions = ['jpg', 'jpeg', 'png'];
                $videoExtensions = ['mp4', 'mov'];

                if (in_array($extension, $imageExtensions)) {
                    $mediaType = 'image';
                    $folder = 'posts/images';
                } elseif (in_array($extension, $videoExtensions)) {
                    $mediaType = 'video';
                    $folder = 'posts/videos';
                } else {
                    // Extension non supportée (ne devrait pas arriver à cause de la validation)
                    continue;
                }

                $path = $file->store($folder, 'public');

                // Vérifier si le fichier est bien enregistré dans storage
                if (Storage::disk('public')->exists($path)) {
                    $post->medias()->create([
                        'file_path' => $path,
                        'media_type' => $mediaType,
                    ]);
                } else {
                    // Optionnel: log ou ajouter un message d'erreur si besoin
                    // \Log::error("Le fichier média n'a pas été enregistré: $path");
                    continue;
                }
            }
        }

        // Charger les tags pour la réponse
        $post->load('tags');
        return response()->json([
            'message' => 'Post créé avec succès.',
            'post' => [
                'id' => $post->id,
                'body' => $post->body,
                'subcategory_id' => $post->subcategory_id ?? null,
                'media' => method_exists($post, 'medias') ? $post->medias->pluck('file_path')->toArray() : [],
                'tags' => method_exists($post, 'tags') ? $post->tags->pluck('tag_name')->toArray() : [],
                'content_status' => $post->content_status,
                'schedule_at' => $post->schedule_at,
                'createdAt' => $post->created_at ? $post->created_at->toISOString() : null
            ]
        ], 201);
}


public function updatePost($postId, Request $request)
    {

        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Utilisateur non authentifié'
                ]
            ], 401);
        }

        $post = Post::find($postId);
        if (!$post) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Post non trouvé'
                ]
            ], 404);
        }

        // Seul l'admin ou le propriétaire du post peut modifier
        if (!$user->isAdmin() && !$user->ownsPost($post)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Vous n\'êtes pas autorisé à modifier ce post'
                ]
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'description' => 'nullable|string',
            'content_status' => 'sometimes|in:draft,published,archived',
            'schedule_at' => 'nullable|date',
            'medias' => 'nullable|array',
            'medias.*' => 'file|mimes:jpg,jpeg,png,mp4,mov|max:20480',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Données invalides',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        $updateData = [];
        if ($request->has('body')) $updateData['body'] = $request->body;
        if ($request->has('description')) $updateData['description'] = $request->description;
        if ($request->has('content_status')) $updateData['content_status'] = $request->content_status;
        if ($request->has('schedule_at')) $updateData['schedule_at'] = $request->schedule_at;

        $post->update($updateData);

        // Gérer l'upload de nouveaux médias (ajoute, ne supprime pas les anciens)
        $mediaFiles = $request->file('medias');
        if (is_iterable($mediaFiles)) {
            foreach ($mediaFiles as $file) {
                $extension = strtolower($file->getClientOriginalExtension());
                $imageExtensions = ['jpg', 'jpeg', 'png'];
                $videoExtensions = ['mp4', 'mov'];
                if (in_array($extension, $imageExtensions)) {
                    $mediaType = 'image';
                    $folder = 'posts/images';
                } elseif (in_array($extension, $videoExtensions)) {
                    $mediaType = 'video';
                    $folder = 'posts/videos';
                } else {
                    continue;
                }
                $path = $file->store($folder, 'public');
                // Vérifier si le fichier est bien enregistré dans storage
                if (Storage::disk('public')->exists($path)) {
                    $post->medias()->create([
                        'file_path' => $path,
                        'media_type' => $mediaType,
                    ]);
                } else {
                    // Optionnel: log ou ajouter un message d'erreur si besoin
                    // \Log::error("Le fichier média n'a pas été enregistré: $path");
                    continue;
                }
            }
        }

        // Rafraîchir les relations pour la réponse
        $post->load('medias', 'tags', 'user');

        return response()->json([
            'message' => 'Post mis à jour avec succès.',
            'post' => $post
        ], 200);
    }


     public function deletePost($postId, Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Utilisateur non authentifié'
                ]
            ], 401);
        }

        $post = Post::with('medias')->find($postId);
        if (!$post) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Post non trouvé'
                ]
            ], 404);
        }

        // Seul l'admin ou le propriétaire du post peut supprimer
        if (!$user->isAdmin() && !$user->ownsPost($post)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Vous n\'êtes pas autorisé à supprimer ce post'
                ]
            ], 403);
        }

        // Supprimer les fichiers médias associés
        foreach ($post->medias as $media) {
            if (isset($media->file_path) && Storage::disk('public')->exists($media->file_path)) {
                Storage::disk('public')->delete($media->file_path);
            }
            $media->delete();
        }

        // Détacher les tags associés dans la table taggables
        if (method_exists($post, 'tags')) {
            $post->tags()->detach();
        }

        $post->delete();

        return response()->json([
            'success' => true,
            'message' => 'Post supprimé avec succès.'
        ], 200);
    }


    public function addCommentToPost($postId, Request $request) {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $post = Post::find($postId);
        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found'
            ], 404);
        }

        $post->comments()->create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'content' => $request->content,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commentaire ajouté avec succès.',
        ], 201);
    }


     public function sharePost($postId, Request $request) {
       //
    }


   public function savePost(Request $request)
    {
        $request->validate([
            'post_id' => 'required|exists:posts,id',
        ]);

        $user = $request->user();
        $postId = $request->post_id;

        // Vérifier si le post existe
        $post = Post::find($postId);
        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found',
            ], 404);
        }

        // Vérifier si le post est déjà sauvegardé
        if ($user->hasSavedPost($postId)) {
            return response()->json([
                'success' => false,
                'message' => 'Post is already saved',
            ], 409);
        }

        // Sauvegarder le post
        $user->savedPosts()->attach($postId);

        return response()->json([
            'success' => true,
            'message' => 'Post saved successfully',
        ]);
    }


    public function unsavePost(Request $request)
    {
        $request->validate([
            'post_id' => 'required|exists:posts,id',
        ]);

        $user = $request->user();
        $postId = $request->post_id;

        // Vérifier si le post existe
        $post = Post::find($postId);
        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found',
            ], 404);
        }

        // Vérifier si le post est sauvegardé
        if (!$user->hasSavedPost($postId)) {
            return response()->json([
                'success' => false,
                'message' => 'Post is not saved',
            ], 404);
        }

        // Retirer le post des sauvegardés
        $user->savedPosts()->detach($postId);

        return response()->json([
            'success' => true,
            'message' => 'Post unsaved successfully',
        ]);
    }

    public function getSavedPosts(Request $request)
    {
        $user = $request->user();
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);

        // Récupérer les posts sauvegardés avec pagination
        $savedPosts = $user->savedPosts()
            ->with(['user', 'medias', 'tags'])
            ->orderBy('saved_posts.created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        // Formater les posts
        $formattedPosts = $savedPosts->map(function ($post) {
            $likeCount = method_exists($post, 'favorites') ? $post->favorites()->count() : 0;
            $commentCount = method_exists($post, 'comments') ? $post->comments()->count() : 0;
            $media = method_exists($post, 'medias') ? $post->medias->pluck('file_path')->toArray() : [];
            $tags = method_exists($post, 'tags') ? $post->tags->pluck('tag_name')->toArray() : [];

            // Le post est forcément en favoris si l'utilisateur l'a sauvegardé (à confirmer selon votre logique)
            // Vérifier si le post est en favoris pour l'utilisateur authentifié
            $authUser = Auth::user();
            $isFavorite = false;
            if ($authUser) {
                $isFavorite = Favorite::where([
                    'user_id' => $authUser->id,
                    'favoriteable_type' => 'App\\Models\\Post',
                    'favoriteable_id' => $post->id
                ])->exists();
            }

            return [
                'id' => $post->id,
                'description' => $post->description,
                'content' => $post->content ?? $post->body ?? '',
                'content_status' => $post->content_status,
                'schedule_at' => $post->schedule_at,
                'category_id' => $post->category_id ?? null,
                'subcategory_id' => $post->subcategory_id ?? null,
                'fandom_id' => $post->fandom_id ?? null,
                'media' => $media,
                'tags' => $tags,
                'user' => $post->user ? [
                    'id' => $post->user->id,
                    'first_name' => $post->user->first_name,
                    'last_name' => $post->user->last_name,
                    'profile_image' => $post->user->profile_image,
                ] : null,
                'likes_count' => $likeCount,
                'comments_count' => $commentCount,
                'is_favorite' => $isFavorite,
                'saved_at' => $post->pivot->created_at ?? null,
                'created_at' => $post->created_at ? $post->created_at->toISOString() : null
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'posts' => $formattedPosts,
                'pagination' => [
                    'page' => $savedPosts->currentPage(),
                    'limit' => $savedPosts->perPage(),
                    'total' => $savedPosts->total(),
                    'pages' => $savedPosts->lastPage(),
                    'hasNext' => $savedPosts->hasMorePages()
                ]
            ]
        ]);
    }




    public function getFollowingFeed(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - User must be logged in'
            ], 401);
        }

        $page = $request->get('page', 1);
        $limit = min(50, max(1, (int) $request->get('limit', 20)));

        // Récupérer les IDs des utilisateurs que l'utilisateur actuel suit
        $followingUserIds = \App\Models\Follow::where('follower_id', $user->id)
            ->pluck('following_id')
            ->toArray();

        if (empty($followingUserIds)) {
            // Si l'utilisateur ne suit personne, retourner un feed vide
            return response()->json([
                'success' => true,
                'data' => [
                    'posts' => [],
                    'following_count' => 0,
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

        // Récupérer les posts des utilisateurs suivis avec pagination
        $posts = \App\Models\Post::whereIn('user_id', $followingUserIds)
            ->where('content_status', 'published')
            ->with(['user:id,first_name,last_name,email,profile_image,bio', 'medias', 'tags', 'fandom:id,name'])
            ->withCount(['favorites', 'comments'])
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        // Formater les posts
        $formattedPosts = collect($posts->items())->map(function ($post) {
            $user = $post->user;

            // Vérifier si le post est en favoris pour l'utilisateur authentifié
            $authUser = Auth::user();
            $isFavorite = false;
            if ($authUser) {
                $isFavorite = Favorite::where([
                    'user_id' => $authUser->id,
                    'favoriteable_type' => 'App\\Models\\Post',
                    'favoriteable_id' => $post->id
                ])->exists();
            }

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
                'is_favorite' => $isFavorite,
                'feedback' => $post->feedback ?? 0,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'posts' => $formattedPosts,
                'following_count' => count($followingUserIds),
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


public function getHomeFeed(Request $request)
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 20);

        $authUser = Auth::user();

        $posts = Post::with(['user', 'medias', 'tags'])
            ->withCount(['favorites', 'comments'])
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        $formattedPosts = $posts->map(function ($post) use ($authUser) {
            // Récupérer toutes les données du post
            $postData = $post->toArray();

            // Vérifier si le post est en favoris
            $isFavorite = false;
            if ($authUser) {
                $isFavorite = Favorite::where([
                    'user_id' => $authUser->id,
                    'favoriteable_type' => 'App\\Models\\Post',
                    'favoriteable_id' => $post->id
                ])->exists();
            }

            // Ajouter les compteurs et le statut favoris
            $postData['likes_count'] = $post->favorites_count ?? 0;
            $postData['comments_count'] = $post->comments_count ?? 0;
            $postData['is_favorite'] = $isFavorite;

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

        return response()->json([
            'success' => true,
            'data' => [
                'posts' => $formattedPosts,
                'pagination' => [
                    'page' => $posts->currentPage(),
                    'limit' => $posts->perPage(),
                    'hasNext' => $posts->hasMorePages()
                ]
            ]
        ]);
    }

    public function getExploreFeed(Request $request)
     {
        //not implemented yet
     }


    public function searchPosts(Request $request)
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

        $authUser = Auth::user();

        // Recherche dans les posts par description, tags ou sous-catégorie
        $posts = \App\Models\Post::where(function($q) use ($query) {
                // Recherche dans la description du post
                $q->where('description', 'LIKE', "%{$query}%")
                  // Recherche dans les tags associés au post
                  ->orWhereHas('tags', function($tagQuery) use ($query) {
                      $tagQuery->where('tag_name', 'LIKE', "%{$query}%");
                  })
                  // Recherche dans la sous-catégorie du post
                  ->orWhereHas('subcategory', function($subQuery) use ($query) {
                      $subQuery->where('name', 'LIKE', "%{$query}%");
                  });
            })
            ->where('content_status', 'published') // Seulement les posts publiés
            ->with(['user:id,first_name,last_name,email,profile_image', 'medias', 'tags', 'subcategory:id,name'])
            ->withCount(['favorites', 'comments'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Formater les données des posts
        $formattedPosts = $posts->getCollection()->map(function ($post) use ($authUser) {
            $isFavorite = false;
            if ($authUser) {
                $isFavorite = Favorite::where([
                    'user_id' => $authUser->id,
                    'favoriteable_type' => 'App\\Models\\Post',
                    'favoriteable_id' => $post->id
                ])->exists();
            }

            return [
                'id' => $post->id,
                'description' => $post->description,
                'content_status' => $post->content_status,
                'schedule_at' => $post->schedule_at,
                'created_at' => $post->created_at,
                'user' => $post->user ? [
                    'id' => $post->user->id,
                    'first_name' => $post->user->first_name,
                    'last_name' => $post->user->last_name,
                    'full_name' => $post->user->first_name . ' ' . $post->user->last_name,
                    'email' => $post->user->email,
                    'profile_image' => $post->user->profile_image
                ] : null,
                'media' => $post->medias ? $post->medias->pluck('file_path')->toArray() : [],
                'tags' => $post->tags ? $post->tags->pluck('tag_name')->toArray() : [],
                'subcategory' => $post->subcategory ? [
                    'id' => $post->subcategory->id,
                    'name' => $post->subcategory->name
                ] : null,
                'likes_count' => $post->favorites_count ?? 0,
                'comments_count' => $post->comments_count ?? 0,
                'is_favorite' => $isFavorite,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'search_query' => $query,
                'posts' => $formattedPosts,
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
        ], 200);
    }




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

            // Vérifier si le post est en favoris pour l'utilisateur authentifié
            $authUser = Auth::user();
            $isFavorite = false;
            if ($authUser) {
                $isFavorite = Favorite::where([
                    'user_id' => $authUser->id,
                    'favoriteable_type' => 'App\\Models\\Post',
                    'favoriteable_id' => $post->id
                ])->exists();
            }

            // Ajouter les compteurs et le statut favoris
            $postData['likes_count'] = $post->favorites_count ?? 0;
            $postData['comments_count'] = $post->comments_count ?? 0;
            $postData['is_favorite'] = $isFavorite;

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

            // Vérifier si le post est en favoris pour l'utilisateur authentifié
            $authUser = Auth::user();
            $isFavorite = false;
            if ($authUser) {
                $isFavorite = Favorite::where([
                    'user_id' => $authUser->id,
                    'favoriteable_type' => 'App\\Models\\Post',
                    'favoriteable_id' => $post->id
                ])->exists();
            }

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
                'is_favorite' => $isFavorite,
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

            // Vérifier si le post est toujours en favoris (il l'est forcément ici)
            $isFavorite = true;

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
                'is_favorite' => $isFavorite,
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





}
