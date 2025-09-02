<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Post;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class PersonnaliseController extends Controller
{
    // ====================
    // AUTHENTICATION
    // ====================

    /**
     * Connexion utilisateur
     * Route: POST /api/auth/login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Email et mot de passe requis',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Email ou mot de passe invalide'
                ]
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Include exact role string and arrays
        $roleNames = $user->getRoleNames();
        $permissionNames = $user->getPermissionNames();

        // Fallback: if user has no roles yet, assign default 'user'
        if ($roleNames->isEmpty()) {
            $user->assignRole('user');
            $roleNames = $user->getRoleNames();
        }



        // Récupérer les catégories préférées
        $preferredCategories = $user->preferredCategories()->pluck('category_id')->toArray();

        // Calcul dynamique des stats (followers, following, posts)
        $followersCount = method_exists($user, 'followers') ? $user->followers()->count() : 0;
        $followingCount = method_exists($user, 'following') ? $user->following()->count() : 0;
        $postsCount = method_exists($user, 'posts') ? $user->posts()->count() : (\App\Models\Post::where('user_id', $user->id)->count());

        return response()->json([
            'message' => 'Connexion réussie.',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'profile_image' => $user->profile_image,
                'background_image' => $user->background_image,
                'date_naissance' => $user->date_naissance,
                'gender' => $user->gender,
                'preferred_categories' => $preferredCategories,
                'role' => $roleNames->first() ?? null,
                'permissions' => $permissionNames->toArray(),
                'stats' => [
                    'followers' => $followersCount,
                    'following' => $followingCount,
                    'posts' => $postsCount
                ],
            ],
            'token' => $token,
        ], 200);
    }

    /**
     * Inscription utilisateur
     * Route: POST /api/auth/register
     */
    public function register(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:6',
            'date_naissance' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'bio' => 'nullable|string|max:2000',
            'preferred_categories' => 'nullable|array',
            'preferred_categories.*' => 'integer|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            // store null when no image provided (do not use default.png)
            'profile_image' => $profileImagePath ?? null,
            'background_image' => null,
            'date_naissance' => $request->date_naissance,
            'gender' => $request->gender,
            'bio' => $request->bio ?? null,
        ]);

        $user->assignRole('user');

        // Enregistrer les catégories préférées si fournies
        $preferredCategories = [];
        if ($request->has('preferred_categories')) {
            foreach ($request->preferred_categories as $catId) {
                $user->preferredCategories()->create(['category_id' => $catId]);
            }
            $preferredCategories = $user->preferredCategories()->pluck('category_id')->toArray();
        }

        // Création du token Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        // Ensure we return an exact role string and also full roles/permissions arrays
        $roleNames = $user->getRoleNames();
        $permissionNames = $user->getPermissionNames();

        // (Suppression du calcul de l'âge)

        return response()->json([
            'message' => 'Inscription réussie.',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'profile_image' => $user->profile_image,
                'background_image' => $user->background_image,
                'date_naissance' => $user->date_naissance,
                'gender' => $user->gender,
                'preferred_categories' => $preferredCategories,
                'role' => $roleNames->first() ?? null,
                'permissions' => $permissionNames->toArray(),
                'stats' => [
                    'followers' => 0,
                    'following' => 0,
                    'posts' => 0
                ],
            ],
            'token' => $token,
        ], 201);
    }

    // ====================
    // USER PROFILE
    // ====================

    /**
     * Obtenir le profil utilisateur
     * Route: GET /api/users/profile
     */
    public function getUserProfile()
    {
        $user = Auth::user();

        // Récupérer les catégories préférées
        $preferredCategories = $user->preferredCategories()->pluck('category_id')->toArray();

        // Calcul dynamique des stats (followers, following, posts)
        $followersCount = method_exists($user, 'followers') ? $user->followers()->count() : 0;
        $followingCount = method_exists($user, 'following') ? $user->following()->count() : 0;
        $postsCount = method_exists($user, 'posts') ? $user->posts()->count() : (\App\Models\Post::where('user_id', $user->id)->count());

        // Rôles et permissions
        $roleNames = $user->getRoleNames();
        $permissionNames = $user->getPermissionNames();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'bio' => $user->bio,
                'profile_image' => $user->profile_image,
                'background_image' => $user->background_image,
                'date_naissance' => $user->date_naissance,
                'gender' => $user->gender,
                'preferred_categories' => $preferredCategories,
                'role' => $roleNames->first() ?? null,
                'permissions' => $permissionNames->toArray(),
                'stats' => [
                    'followers' => $followersCount,
                    'following' => $followingCount,
                    'posts' => $postsCount
                ],
            ]
        ]);
    }

    /**
     * Mettre à jour le profil utilisateur
     * Route: PUT /api/users/profile
     */
    public function updateUserProfile(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'date_naissance' => 'sometimes|date',
            'gender' => 'sometimes|in:male,female,other',
            'bio' => 'sometimes|string|max:2000',
            'profile_image' => 'sometimes|file|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'background_image' => 'sometimes|file|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'preferred_categories' => 'sometimes|array',
            'preferred_categories.*' => 'integer|exists:categories,id',
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
        if ($request->has('first_name')) $updateData['first_name'] = $request->first_name;
        if ($request->has('last_name')) $updateData['last_name'] = $request->last_name;
        if ($request->has('date_naissance')) $updateData['date_naissance'] = $request->date_naissance;
    if ($request->has('gender')) $updateData['gender'] = $request->gender;
    if ($request->has('bio')) $updateData['bio'] = $request->bio;


        // Gérer l'upload de la photo de profil
        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            if ($file->isValid()) {
                $path = $file->store('profile', 'public');
                $updateData['profile_image'] = 'storage/' . $path;
            }
        }

        // Gérer l'upload de la photo de couverture
        if ($request->hasFile('background_image')) {
            $file = $request->file('background_image');
            if ($file->isValid()) {
                $path = $file->store('backgroundprofile', 'public');
                $updateData['background_image'] = 'storage/' . $path;
            }
        }

        $user->update($updateData);

        // Mettre à jour les catégories préférées si fournies
        if ($request->has('preferred_categories')) {
            if (method_exists($user, 'preferredCategories')) {
                $user->preferredCategories()->delete();
                foreach ($request->preferred_categories as $catId) {
                    $user->preferredCategories()->create(['category_id' => $catId]);
                }
            }
        }

        // Récupérer les catégories préférées
        $preferredCategories = method_exists($user, 'preferredCategories') ? $user->preferredCategories()->pluck('category_id')->toArray() : [];

        // Calcul dynamique des stats (followers, following, posts)
        $followersCount = method_exists($user, 'followers') ? $user->followers()->count() : 0;
        $followingCount = method_exists($user, 'following') ? $user->following()->count() : 0;
        $postsCount = method_exists($user, 'posts') ? $user->posts()->count() : (\App\Models\Post::where('user_id', $user->id)->count());

        // Rôles et permissions
        $roleNames = method_exists($user, 'getRoleNames') ? $user->getRoleNames() : collect();
        $permissionNames = method_exists($user, 'getPermissionNames') ? $user->getPermissionNames() : collect();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'profile_image' => $user->profile_image,
                'background_image' => $user->background_image,
                'date_naissance' => $user->date_naissance,
                'gender' => $user->gender,
                'preferred_categories' => $preferredCategories,
                'role' => $roleNames->first() ?? null,
                'permissions' => $permissionNames->toArray(),
                'stats' => [
                    'followers' => $followersCount,
                    'following' => $followingCount,
                    'posts' => $postsCount
                ],
            ]
        ]);
    }

    /**
     * Obtenir les posts d'un utilisateur
     * Route: GET /api/users/{userId}/posts
     */

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

    /**
     * Mettre à jour un post existant
     * Route: PUT /api/posts/{postId}
     */
    public function updatePost($postId, Request $request)
    {
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
            'post' => [
                'id' => $post->id,
                'body' => $post->body,
                'media' => method_exists($post, 'medias') ? $post->medias->pluck('file_path')->toArray() : [],
                'tags' => method_exists($post, 'tags') ? $post->tags->pluck('tag_name')->toArray() : [],
                'content_status' => $post->content_status,
                'schedule_at' => $post->schedule_at,
                'createdAt' => $post->created_at ? $post->created_at->toISOString() : null
            ]
        ], 200);
    }

      /**
     * Supprimer un post existant
     * Route: DELETE /api/posts/{postId}
     */
    public function deletePost($postId, Request $request)
    {
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

    public function getUserPosts($userId, Request $request)
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);


        $posts = Post::where('user_id', $userId)
            ->with(['medias', 'tags'])
            ->latest()
            ->paginate($limit, ['*'], 'page', $page);

        $formattedPosts = collect($posts->items())->map(function ($post) {
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

     public function getUserFollowers($userId) {
        $user = User::find($userId);
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Utilisateur non trouvé'
                ]
            ], 404);
        }

        // On suppose que la relation 'followers' existe sur le modèle User
        $followers = method_exists($user, 'followers') ? $user->followers()->get() : collect();

        $formattedFollowers = $followers->map(function ($follower) {
            $arr = $follower->toArray();
            unset($arr['pivot']);
            return $arr;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'followers' => $formattedFollowers,
                'total' => $formattedFollowers->count()
            ]
        ]);
    }

    // ====================
    // MAIN CONTENT
    // ====================

    /**
     * Obtenir le feed principal
     * Route: GET /api/feed/home
     */
    public function getHomeFeed(Request $request)
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 20);

        $posts = Post::with(['user', 'medias'])
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        $formattedPosts = $posts->map(function ($post) {
            $user = $post->user;
            // La relation favorites() sur le post ne contient que les favoris de ce post
            $likeCount = method_exists($post, 'favorites')
                ? $post->favorites()->count()
                : 0;
            $commentCount = method_exists($post, 'comments') ? $post->comments()->count() : 0;
            $media = method_exists($post, 'medias') ? $post->medias->pluck('file_path')->toArray() : [];
            return [
                'id' => $post->id,
                'content' => $post->content ?? $post->body ?? '',
                'media' => $media,
                'user' => $user ? $user->toArray() : null,
                'likes_count' => $likeCount,
                'comments_count' => $commentCount,
                'created_at' => $post->created_at ? $post->created_at->toISOString() : null
            ];
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

    /**
     * Obtenir le feed d'exploration
     * Route: GET /api/feed/explore
     */


    // ====================
    // SOCIAL / USER RELATIONS
    // ====================

    public function getUserFollowing($userId) {
        $user = User::find($userId);
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Utilisateur non trouvé'
                ]
            ], 404);
        }

        // On suppose que la relation 'following' existe sur le modèle User
        $following = method_exists($user, 'following') ? $user->following()->get() : collect();

        $formattedFollowing = $following->map(function ($followed) {
            $arr = $followed->toArray();
            unset($arr['pivot']);
            return $arr;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'following' => $formattedFollowing,
                'total' => $formattedFollowing->count()
            ]
        ]);
    }

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


    // ====================
    // POSTS
    // ====================
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
       //notimpemented
    }


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
                    'name' => $category->name
                ],
                'subcategories' => $subcategories
            ]
        ]);
    }

     /**
     * Récupérer tous les fandoms
     * Route: GET /api/fandoms
     */
    public function getFandoms()
    {
        $user = Auth::user();

        // Charger la relation subcategory et le nombre de posts/members pour éviter les N+1
        $fandoms = \App\Models\Fandom::with('subcategory')->withCount(['posts', 'members'])->get();

        // Obtenir les rôles de l'utilisateur pour tous les fandoms s'il est authentifié
        $userMemberships = [];
        if ($user) {
            $memberships = \App\Models\Member::where('user_id', $user->id)->get();
            foreach ($memberships as $membership) {
                $userMemberships[$membership->fandom_id] = $membership->role;
            }
        }

        // Retourner tous les champs du modèle Fandom (sans les lister manuellement)
        // on convertit chaque modèle en tableau puis on ajoute les compteurs et une sous-catégorie minimale
        $formatted = $fandoms->map(function ($f) use ($userMemberships) {
            $attrs = $f->toArray();
            // s'assurer que les compteurs sont présents
            $attrs['posts_count'] = $f->posts_count ?? 0;
            $attrs['members_count'] = $f->members_count ?? 0;

            // Ajouter les informations de membership
            $attrs['is_member'] = isset($userMemberships[$f->id]);
            $attrs['member_role'] = $userMemberships[$f->id] ?? null;

            // réduire la sous-catégorie à id/name pour éviter de renvoyer trop de données
            if (isset($attrs['subcategory']) && is_array($attrs['subcategory'])) {
                $attrs['subcategory'] = [
                    'id' => $attrs['subcategory']['id'] ?? null,
                    'name' => $attrs['subcategory']['name'] ?? null,
                ];
            }

            return $attrs;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'fandoms' => $formatted
            ]
        ]);
    }

    public function getTrendingFandoms()
    {
        $user = Auth::user();

        // Charger la relation subcategory et le nombre de posts/members pour éviter les N+1
        $fandoms = \App\Models\Fandom::with('subcategory')->withCount(['posts', 'members'])->get();

        // Obtenir les rôles de l'utilisateur pour tous les fandoms s'il est authentifié
        $userMemberships = [];
        if ($user) {
            $memberships = \App\Models\Member::where('user_id', $user->id)->get();
            foreach ($memberships as $membership) {
                $userMemberships[$membership->fandom_id] = $membership->role;
            }
        }

        // Retourner tous les champs du modèle Fandom (sans les lister manuellement)
        // on convertit chaque modèle en tableau puis on ajoute les compteurs et une sous-catégorie minimale
        $formatted = $fandoms->map(function ($f) use ($userMemberships) {
            $attrs = $f->toArray();
            // s'assurer que les compteurs sont présents
            $attrs['posts_count'] = $f->posts_count ?? 0;
            $attrs['members_count'] = $f->members_count ?? 0;

            // Ajouter les informations de membership
            $attrs['is_member'] = isset($userMemberships[$f->id]);
            $attrs['member_role'] = $userMemberships[$f->id] ?? null;

            // réduire la sous-catégorie à id/name pour éviter de renvoyer trop de données
            if (isset($attrs['subcategory']) && is_array($attrs['subcategory'])) {
                $attrs['subcategory'] = [
                    'id' => $attrs['subcategory']['id'] ?? null,
                    'name' => $attrs['subcategory']['name'] ?? null,
                ];
            }

            return $attrs;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'fandoms' => $formatted
            ]
        ]);
    }

    /**
     * Rechercher des fandoms par q
     * Route: GET /api/fandoms/search?q=QUERY
     */
    public function searchFandoms(Request $request)
    {
        $user = Auth::user();
        $q = $request->get('q', '');

        // base query: eager load subcategory and include counts
        $query = \App\Models\Fandom::with('subcategory')->withCount(['posts', 'members']);

        if (!empty($q)) {
            // recherche sur le nom et la description
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $fandoms = $query->get();

        // Obtenir les rôles de l'utilisateur pour tous les fandoms s'il est authentifié
        $userMemberships = [];
        if ($user) {
            $memberships = \App\Models\Member::where('user_id', $user->id)->get();
            foreach ($memberships as $membership) {
                $userMemberships[$membership->fandom_id] = $membership->role;
            }
        }

        $formatted = $fandoms->map(function ($f) use ($userMemberships) {
            $attrs = $f->toArray();
            $attrs['posts_count'] = $f->posts_count ?? 0;
            $attrs['members_count'] = $f->members_count ?? 0;

            // Ajouter les informations de membership
            $attrs['is_member'] = isset($userMemberships[$f->id]);
            $attrs['member_role'] = $userMemberships[$f->id] ?? null;

            if (isset($attrs['subcategory']) && is_array($attrs['subcategory'])) {
                $attrs['subcategory'] = [
                    'id' => $attrs['subcategory']['id'] ?? null,
                    'name' => $attrs['subcategory']['name'] ?? null,
                ];
            }
            return $attrs;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'fandoms' => $formatted,
                'query' => $q
            ]
        ]);
    }


        /**
     * Récupérer les fandoms d'une sous-catégorie
     * Route: GET /api/subcategories/{subcategoryId}/fandoms
     */


    /**
     * Récupérer un fandom par id et inclure le statut du member de l'utilisateur authentifié
     * Route: GET /api/fandoms/{fandom_id} (route already registered)
     */
    public function getfandombyId($fandomId, Request $request)
    {
        $user = Auth::user();

        $fandom = \App\Models\Fandom::with('subcategory')->withCount(['posts', 'members'])->find($fandomId);
        if (!$fandom) {
            return response()->json(['success' => false, 'message' => 'Fandom not found'], 404);
        }

        $attrs = $fandom->toArray();
        $attrs['posts_count'] = $fandom->posts_count ?? 0;
        $attrs['members_count'] = $fandom->members_count ?? 0;

        if (isset($attrs['subcategory']) && is_array($attrs['subcategory'])) {
            $attrs['subcategory'] = [
                'id' => $attrs['subcategory']['id'] ?? null,
                'name' => $attrs['subcategory']['name'] ?? null,
            ];
        }

        // Default membership info
        $attrs['is_member'] = false;
        $attrs['member_role'] = null;

        if ($user) {
            $member = \App\Models\Member::where('user_id', $user->id)->where('fandom_id', $fandom->id)->first();
            if ($member) {
                $attrs['is_member'] = true;
                $attrs['member_role'] = $member->role;
            }
        }

        return response()->json(['success' => true, 'data' => ['fandom' => $attrs]]);
    }

    /**
     * Permettre à un utilisateur authentifié de rejoindre un fandom
     * Route: POST /api/Y/fandoms/{fandom_id}/join
     */
    public function joinFandom($fandom_id, Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Résoudre le fandom par id
        $fandom = \App\Models\Fandom::find((int) $fandom_id);

        if (!$fandom) {
            return response()->json(['success' => false, 'message' => 'Fandom not found'], 404);
        }

        // Vérifier si l'utilisateur est déjà membre
        $existing = \App\Models\Member::where('user_id', $user->id)->where('fandom_id', $fandom->id)->first();
        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Vous êtes déjà membre de ce fandom'], 409);
        }

        // Créer l'enregistrement de member (role par défaut: member)
        $member = \App\Models\Member::create([
            'user_id' => $user->id,
            'fandom_id' => $fandom->id,
            'role' => 'member',
        ]);

        if (!$member) {
            return response()->json(['success' => false, 'message' => 'Impossible de rejoindre le fandom'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Vous avez rejoint le fandom avec succès.'], 201);
    }

    /**
     * Permettre à un utilisateur authentifié de quitter un fandom
     * Route: DELETE /api/Y/fandoms/{fandom_id}/leave
     */
    public function leaveFandom($fandom_id, Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Résoudre le fandom par id
        $fandom = \App\Models\Fandom::find((int) $fandom_id);

        if (!$fandom) {
            return response()->json(['success' => false, 'message' => 'Fandom not found'], 404);
        }

        // Vérifier si l'utilisateur est membre du fandom
        $existing = \App\Models\Member::where('user_id', $user->id)->where('fandom_id', $fandom->id)->first();
        if (!$existing) {
            return response()->json(['success' => false, 'message' => 'Vous n\'êtes pas membre de ce fandom'], 404);
        }

        // Empêcher l'admin de quitter son propre fandom s'il est le seul admin
        if ($existing->role === 'admin') {
            $adminCount = \App\Models\Member::where('fandom_id', $fandom->id)
                ->where('role', 'admin')
                ->count();

            if ($adminCount === 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de quitter le fandom. Vous êtes le seul administrateur. Transférez d\'abord les droits d\'administration à un autre membre.'
                ], 403);
            }
        }

        // Supprimer l'enregistrement de member
        $existing->delete();

        return response()->json(['success' => true, 'message' => 'Vous avez quitté le fandom avec succès.'], 200);
    }

    /**
     * Permettre à un administrateur de changer le rôle d'un membre dans un fandom
     * Route: PUT /api/Y/fandoms/{fandom_id}/members/{user_id}/role
     */
    public function changeMemberRole($fandom_id, $user_id, Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Validation des données
        $request->validate([
            'role' => 'required|string|in:member,moderator,admin'
        ]);

        $newRole = $request->input('role');

        // Résoudre le fandom par id
        $fandom = \App\Models\Fandom::find((int) $fandom_id);
        if (!$fandom) {
            return response()->json(['success' => false, 'message' => 'Fandom not found'], 404);
        }

        // Vérifier que l'utilisateur actuel est administrateur du fandom
        $currentUserMembership = \App\Models\Member::where('user_id', $user->id)
            ->where('fandom_id', $fandom->id)
            ->where('role', 'admin')
            ->first();

        if (!$currentUserMembership) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only administrators can change member roles.'
            ], 403);
        }

        // Vérifier que l'utilisateur cible existe et est membre du fandom
        $targetUser = \App\Models\User::find((int) $user_id);
        if (!$targetUser) {
            return response()->json(['success' => false, 'message' => 'Target user not found'], 404);
        }

        $targetMembership = \App\Models\Member::where('user_id', $targetUser->id)
            ->where('fandom_id', $fandom->id)
            ->first();

        if (!$targetMembership) {
            return response()->json([
                'success' => false,
                'message' => 'Target user is not a member of this fandom'
            ], 404);
        }

        // Empêcher l'admin de se rétrograder s'il est le seul admin
        if ($targetUser->id === $user->id && $targetMembership->role === 'admin' && $newRole !== 'admin') {
            $adminCount = \App\Models\Member::where('fandom_id', $fandom->id)
                ->where('role', 'admin')
                ->count();

            if ($adminCount === 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot demote yourself. You are the only administrator. Promote another member to admin first.'
                ], 403);
            }
        }

        // Mettre à jour le rôle
        $oldRole = $targetMembership->role;
        $targetMembership->role = $newRole;
        $targetMembership->save();

        return response()->json([
            'success' => true,
            'message' => "Member role updated successfully from '{$oldRole}' to '{$newRole}'",
            'data' => [
                'user_id' => $targetUser->id,
                'username' => $targetUser->name,
                'old_role' => $oldRole,
                'new_role' => $newRole,
                'fandom_id' => $fandom->id,
                'fandom_name' => $fandom->name
            ]
        ], 200);
    }

    /**
     * Permettre aux membres d'un fandom d'ajouter un post dans ce fandom
     * Route: POST /api/Y/fandoms/{fandom_id}/posts
     */
    public function addPostToFandom($fandom_id, Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Validation des données
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'schedule_at' => 'nullable|date',
            'description' => 'nullable|string',
            'content_status' => 'required|in:draft,published,archived',
            'medias' => 'nullable|array',
            'medias.*' => 'file|mimes:jpg,jpeg,png,mp4,mov|max:20480',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:255',
            'image' => 'nullable|string', // URL ou chemin de l'image
        ]);

        // Résoudre le fandom par id
        $fandom = \App\Models\Fandom::find((int) $fandom_id);
        if (!$fandom) {
            return response()->json(['success' => false, 'message' => 'Fandom not found'], 404);
        }

        // Vérifier que l'utilisateur est membre du fandom
        $membership = \App\Models\Member::where('user_id', $user->id)
            ->where('fandom_id', $fandom->id)
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You must be a member of this fandom to post.'
            ], 403);
        }

        // Créer le post
        $post = new \App\Models\Post();
        $post->title = $request->input('title');
        $post->content = $request->input('content');
        $post->user_id = $user->id;
        $post->fandom_id = $fandom->id;
        $post->content_status = $request->input('content_status');

        if ($request->has('description') && !empty($request->input('description'))) {
            $post->description = $request->input('description');
        }

        if ($request->has('schedule_at') && !empty($request->input('schedule_at'))) {
            $post->schedule_at = $request->input('schedule_at');
        }

        if ($request->has('image') && !empty($request->input('image'))) {
            $post->image = $request->input('image');
        }

        $post->save();

        // Gérer les médias uploadés
        if ($request->has('medias') && is_array($request->file('medias'))) {
            foreach ($request->file('medias') as $mediaFile) {
                // Stocker le fichier
                $path = $mediaFile->store('medias', 'public');

                // Déterminer le type de média
                $mimeType = $mediaFile->getMimeType();
                $type = 'document'; // par défaut
                if (str_starts_with($mimeType, 'image/')) {
                    $type = 'image';
                } elseif (str_starts_with($mimeType, 'video/')) {
                    $type = 'video';
                } elseif (str_starts_with($mimeType, 'audio/')) {
                    $type = 'audio';
                }

                // Créer l'enregistrement média
                $media = new \App\Models\Media();
                $media->url = $path;
                $media->type = $type;
                $media->post_id = $post->id;
                $media->save();
            }
        }

        // Ajouter les tags si fournis
        if ($request->has('tags') && is_array($request->input('tags'))) {
            $tags = $request->input('tags');
            foreach ($tags as $tagName) {
                if (!empty(trim($tagName))) {
                    // Trouver ou créer le tag
                    $tag = \App\Models\Tag::firstOrCreate(['name' => trim($tagName)]);

                    // Attacher le tag au post (éviter les doublons)
                    if (!$post->tags()->where('tag_id', $tag->id)->exists()) {
                        $post->tags()->attach($tag->id);
                    }
                }
            }
        }

        // Charger les relations pour la réponse
        $post->load(['user:id,name,email', 'fandom:id,name', 'tags:id,name', 'medias:id,url,type,post_id']);

        return response()->json([
            'success' => true,
            'message' => 'Post added to fandom successfully',
            'data' => [
                'post' => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'content' => $post->content,
                    'description' => $post->description,
                    'content_status' => $post->content_status,
                    'schedule_at' => $post->schedule_at,
                    'image' => $post->image,
                    'created_at' => $post->created_at,
                    'updated_at' => $post->updated_at,
                    'user' => $post->user,
                    'fandom' => $post->fandom,
                    'tags' => $post->tags->pluck('name')->toArray(),
                    'medias' => $post->medias->map(function($media) {
                        return [
                            'id' => $media->id,
                            'url' => asset('storage/' . $media->url),
                            'type' => $media->type
                        ];
                    })->toArray()
                ]
            ]
        ], 201);
    }

    /**
     * Obtenir les posts d'un fandom
     * Route: GET /api/fandoms/{fandom_id}/posts
     */
    public function getFandomPosts($fandomId, Request $request)
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));

        $fandom = \App\Models\Fandom::find($fandomId);
        if (!$fandom) {
            return response()->json(['success' => false, 'message' => 'Fandom not found'], 404);
        }

        $posts = \App\Models\Post::where('fandom_id', $fandomId)
            ->with(['medias', 'tags', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        $formatted = collect($posts->items())->map(function ($post) {
            return [
                'id' => $post->id,
                'description' => $post->description,
                'media' => method_exists($post, 'medias') ? $post->medias->pluck('file_path')->toArray() : [],
                'tags' => method_exists($post, 'tags') ? $post->tags->pluck('tag_name')->toArray() : [],
                'user' => $post->user ? $post->user->toArray() : null,
                'likes_count' => method_exists($post, 'favorites') ? $post->favorites()->count() : 0,
                'comments_count' => method_exists($post, 'comments') ? $post->comments()->count() : 0,
                'created_at' => $post->created_at ? $post->created_at->toISOString() : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'posts' => $formatted,
                'pagination' => [
                    'page' => $posts->currentPage(),
                    'per_page' => $posts->perPage(),
                    'total' => $posts->total(),
                    'last_page' => $posts->lastPage(),
                    'has_more' => $posts->hasMorePages(),
                ]
            ]
        ]);
    }

    /**
     * Obtenir les membres d'un fandom
     * Route: GET /api/fandoms/{fandom_id}/members
     */
    public function getFandomMembers($fandomId, Request $request)
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 20)));

        $fandom = \App\Models\Fandom::find($fandomId);
        if (!$fandom) {
            return response()->json(['success' => false, 'message' => 'Fandom not found'], 404);
        }

        // join members with users to get user info and role
        $membersQuery = \App\Models\Member::where('fandom_id', $fandomId)->with('user');

        $paginator = $membersQuery->paginate($limit, ['*'], 'page', $page);

        $members = collect($paginator->items())->map(function ($m) {
            $userArr = $m->user ? $m->user->toArray() : null;
            return [
                'member_id' => $m->id,
                'user' => $userArr,
                'member_role' => $m->role,
                'joined_at' => $m->created_at ? $m->created_at->toISOString() : null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'members' => $members,
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'has_more' => $paginator->hasMorePages(),
                ]
            ]
        ]);
    }
    public function getMyFandoms(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);
        $role = $request->get('role'); // Filtrer par rôle si fourni

        // Récupérer les membres (fandoms) de l'utilisateur avec pagination
        $membersQuery = \App\Models\Member::where('user_id', $user->id)
            ->with(['fandom.subcategory'])
            ->orderBy('created_at', 'desc');

        // Filtrer par rôle si fourni
        if ($role) {
            $membersQuery->where('role', $role);
        }

        $paginator = $membersQuery->paginate($limit, ['*'], 'page', $page);

        // Formater les données
        $userFandoms = collect($paginator->items())->map(function ($member) {
            $fandom = $member->fandom;
            if (!$fandom) {
                return null;
            }

            return [
                'membership_id' => $member->id,
                'role' => $member->role,
                'joined_at' => $member->created_at ? $member->created_at->toISOString() : null,
                'fandom' => [
                    'id' => $fandom->id,
                    'name' => $fandom->name,
                    'description' => $fandom->description,
                    'cover_image' => $fandom->cover_image,
                    'logo_image' => $fandom->logo_image,
                    'subcategory' => $fandom->subcategory ? [
                        'id' => $fandom->subcategory->id,
                        'name' => $fandom->subcategory->name,
                    ] : null,
                    'posts_count' => $fandom->posts()->count(),
                    'members_count' => $fandom->members()->count(),
                    'created_at' => $fandom->created_at ? $fandom->created_at->toISOString() : null,
                ]
            ];
        })->filter()->values(); // Filtrer les nulls

        return response()->json([
            'success' => true,
            'data' => [
                'fandoms' => $userFandoms,
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'has_more' => $paginator->hasMorePages(),
                ]
            ]
        ]);
    }


    /**
     * Créer un nouveau fandom
     * Route: POST /api/Y/fandoms
     */
    public function createFandom(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'subcategory_id' => 'required|integer|exists:subcategories,id',
            // cover_image may be either an uploaded file or an external URL string
            'cover_image' => ['nullable', function ($attribute, $value, $fail) use ($request) {
                // If a file was uploaded under this key, validate it here
                if ($request->hasFile('cover_image')) {
                    $file = $request->file('cover_image');
                    if (!$file->isValid()) {
                        return $fail('Le fichier '.$attribute.' est invalide.');
                    }
                    $ext = strtolower($file->getClientOriginalExtension());
                    $allowed = ['jpg','jpeg','png','gif','webp'];
                    if (!in_array($ext, $allowed)) {
                        return $fail('Le fichier '.$attribute.' doit être une image (jpg,jpeg,png,gif,webp).');
                    }
                    // max size ~8MB
                    if ($file->getSize() > 8192 * 1024) {
                        return $fail('Le fichier '.$attribute.' est trop volumineux.');
                    }
                } else {
                    // if not a file, allow null or a valid URL string
                    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                        return $fail('Le champ '.$attribute.' doit être une URL valide ou un fichier image.');
                    }
                }
            }],
            // logo_image validation
            'logo_image' => ['nullable', function ($attribute, $value, $fail) use ($request) {
                // If a file was uploaded under this key, validate it here
                if ($request->hasFile('logo_image')) {
                    $file = $request->file('logo_image');
                    if (!$file->isValid()) {
                        return $fail('Le fichier '.$attribute.' est invalide.');
                    }
                    $ext = strtolower($file->getClientOriginalExtension());
                    $allowed = ['jpg','jpeg','png','gif','webp'];
                    if (!in_array($ext, $allowed)) {
                        return $fail('Le fichier '.$attribute.' doit être une image (jpg,jpeg,png,gif,webp).');
                    }
                    // max size ~8MB
                    if ($file->getSize() > 8192 * 1024) {
                        return $fail('Le fichier '.$attribute.' est trop volumineux.');
                    }
                } else {
                    // if not a file, allow null or a valid URL string
                    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                        return $fail('Le champ '.$attribute.' doit être une URL valide ou un fichier image.');
                    }
                }
            }],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // If a cover image file is uploaded, store it in fandom_cover_image and prefer it
        if ($request->hasFile('cover_image') && $request->file('cover_image')->isValid()) {
            $path = $request->file('cover_image')->store('fandom_cover_image', 'public');
            // store a web-accessible path like 'storage/...'
            $data['cover_image'] = 'storage/' . $path;
        }

        // If caller provided a URL string in cover_image (and no file), keep it as-is
        if (empty($data['cover_image']) && $request->filled('cover_image')) {
            $data['cover_image'] = $request->input('cover_image');
        }

        // Handle logo_image upload
        if ($request->hasFile('logo_image') && $request->file('logo_image')->isValid()) {
            $path = $request->file('logo_image')->store('fandom_logo_image', 'public');
            // store a web-accessible path like 'storage/...'
            $data['logo_image'] = 'storage/' . $path;
        }

        // If caller provided a URL string in logo_image (and no file), keep it as-is
        if (empty($data['logo_image']) && $request->filled('logo_image')) {
            $data['logo_image'] = $request->input('logo_image');
        }

        $fandom = \App\Models\Fandom::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'subcategory_id' => $data['subcategory_id'] ?? null,
            'cover_image' => $data['cover_image'] ?? null,
            'logo_image' => $data['logo_image'] ?? null,
        ]);

        if (!$fandom) {
            return response()->json(['success' => false, 'message' => 'Impossible de créer le fandom'], 500);
        }

        // Ajouter le créateur comme membre avec le rôle admin
        try {
            $existingMember = \App\Models\Member::where('user_id', $user->id)->where('fandom_id', $fandom->id)->first();
            if (!$existingMember) {
                \App\Models\Member::create([
                    'user_id' => $user->id,
                    'fandom_id' => $fandom->id,
                    'role' => 'admin',
                ]);
            }
        } catch (\Exception $e) {
            // ne pas empêcher la création du fandom si l'ajout en membre échoue
            // silent catch (no logging as requested)
        }

        return response()->json(['success' => true, 'message' => 'Fandom créé avec succès', 'data' => ['fandom' => $fandom]], 201);
    }

    /**
     * Mettre à jour un fandom existant
     * Route: POST /api/Y/fandoms/{idOrHandle}
     */
    public function updateFandom($fandom_id, Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Résoudre le fandom par id
        $fandom = \App\Models\Fandom::find((int) $fandom_id);

        if (!$fandom) {
            return response()->json(['success' => false, 'message' => 'Fandom not found'], 404);
        }

        // Only allow admins of the fandom to update
        $member = \App\Models\Member::where('user_id', $user->id)->where('fandom_id', $fandom->id)->first();
        if (!$member || ($member->role ?? '') !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'subcategory_id' => 'sometimes|integer|exists:subcategories,id',
            'cover_image' => ['nullable', function ($attribute, $value, $fail) use ($request) {
                if ($request->hasFile('cover_image')) {
                    $file = $request->file('cover_image');
                    if (!$file->isValid()) return $fail('Invalid file.');
                    $ext = strtolower($file->getClientOriginalExtension());
                    $allowed = ['jpg','jpeg','png','gif','webp'];
                    if (!in_array($ext, $allowed)) return $fail('Invalid image type.');
                    if ($file->getSize() > 8192 * 1024) return $fail('File too large.');
                } else {
                    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                        return $fail('The cover_image must be a valid URL or file.');
                    }
                }
            }],
            'logo_image' => ['nullable', function ($attribute, $value, $fail) use ($request) {
                if ($request->hasFile('logo_image')) {
                    $file = $request->file('logo_image');
                    if (!$file->isValid()) return $fail('Invalid file.');
                    $ext = strtolower($file->getClientOriginalExtension());
                    $allowed = ['jpg','jpeg','png','gif','webp'];
                    if (!in_array($ext, $allowed)) return $fail('Invalid image type.');
                    if ($file->getSize() > 8192 * 1024) return $fail('File too large.');
                } else {
                    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                        return $fail('The logo_image must be a valid URL or file.');
                    }
                }
            }],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Handle uploaded cover image: store and delete old if present and was stored locally
        if ($request->hasFile('cover_image') && $request->file('cover_image')->isValid()) {
            $path = $request->file('cover_image')->store('fandom_cover_image', 'public');
            $newUrl = 'storage/' . $path;
            // if previous cover image was a storage path, delete the old file
            if ($fandom->cover_image && str_starts_with($fandom->cover_image, 'storage/')) {
                $oldPath = substr($fandom->cover_image, strlen('storage/'));
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
            $data['cover_image'] = $newUrl;
        }

        // If cover_image provided as URL string and not a file, use it
        if (empty($data['cover_image']) && $request->filled('cover_image')) {
            $data['cover_image'] = $request->input('cover_image');
        }

        // Handle uploaded logo image: store and delete old if present and was stored locally
        if ($request->hasFile('logo_image') && $request->file('logo_image')->isValid()) {
            $path = $request->file('logo_image')->store('fandom_logo_image', 'public');
            $newUrl = 'storage/' . $path;
            // if previous logo image was a storage path, delete the old file
            if ($fandom->logo_image && str_starts_with($fandom->logo_image, 'storage/')) {
                $oldPath = substr($fandom->logo_image, strlen('storage/'));
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
            $data['logo_image'] = $newUrl;
        }

        // If logo_image provided as URL string and not a file, use it
        if (empty($data['logo_image']) && $request->filled('logo_image')) {
            $data['logo_image'] = $request->input('logo_image');
        }

        $fandom->update($data);

        return response()->json(['success' => true, 'message' => 'Fandom mis à jour avec succès', 'data' => ['fandom' => $fandom]], 200);
    }

    public function getAllCategories() {
        $categories = Category::all();
        return response()->json($categories);
    }


    // ====================
    // HASHTAGS
    // ====================
    public function getHashtagPosts($hashtag) {
        return response()->json([
            'success' => true,
            'data' => [
                'posts' => [],
                'stats' => [
                    'totalPosts' => 0,
                    'growth' => '+0%',
                    'category' => 'General'
                ]
            ]
        ]);
    }

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
                'category' => $cat->name,
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
     * Sauvegarder un post
     * Route: POST /api/posts/{postId}/save
     */
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

    /**
     * Retirer un post des sauvegardés
     * Route: DELETE /api/posts/{postId}/unsave
     */
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

    /**
     * Basculer l'état de sauvegarde d'un post (sauvegarder ou désauvegarder)
     * Route: POST /api/posts/{postId}/toggle-save
     */

}
