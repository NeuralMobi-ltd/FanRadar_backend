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
                'bio'=>$user->bio,
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

    /**
     * Récupérer le profil complet d'un utilisateur par son ID
     * Route: GET /api/Y/users/{userId}/profile
     */
    public function getUserProfileById($userId, Request $request)
    {
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

        // Récupérer tous les champs de l'utilisateur
        $userProfile = $user->toArray();

        // Supprimer les champs sensibles
        unset($userProfile['password']);
        unset($userProfile['email_verified_at']);
        unset($userProfile['remember_token']);

        // Ajouter les statistiques de follow
        $followersCount = method_exists($user, 'followers') ? $user->followers()->count() : 0;
        $followingCount = method_exists($user, 'following') ? $user->following()->count() : 0;

        $userProfile['followers_count'] = $followersCount;
        $userProfile['following_count'] = $followingCount;

        // Récupérer les posts de l'utilisateur
        $posts = Post::where('user_id', $userId)
            ->with(['medias', 'tags'])
            ->latest()
            ->get();

        $formattedPosts = $posts->map(function ($post) {
            return [
                'id' => $post->id,
                'description' => $post->description,
                'content_status' => $post->content_status,
                'schedule_at' => $post->schedule_at,
                'category_id' => $post->category_id ?? null,
                'subcategory_id' => $post->subcategory_id ?? null,
                'fandom_id' => $post->fandom_id ?? null,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
                'media' => method_exists($post, 'medias') ? $post->medias->pluck('file_path')->toArray() : [],
                'tags' => method_exists($post, 'tags') ? $post->tags->pluck('tag_name')->toArray() : [],
            ];
        });

        $userProfile['posts'] = $formattedPosts;
        $userProfile['posts_count'] = $posts->count();

        // Vérifier si l'utilisateur authentifié suit cet utilisateur
        $authUser = Auth::user();
        $isFollowed = false;

        if ($authUser && $authUser->id !== $userId) {
            $isFollowed = \App\Models\Follow::where([
                'follower_id' => $authUser->id,
                'following_id' => $userId,
            ])->exists();
        }

        $userProfile['is_followed'] = $isFollowed;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $userProfile
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

        $posts = Post::with(['user', 'medias', 'tags'])
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        $formattedPosts = $posts->map(function ($post) {
            // Récupérer toutes les données du post
            $postData = $post->toArray();

            // Ajouter les compteurs
            $postData['likes_count'] = method_exists($post, 'favorites') ? $post->favorites()->count() : 0;
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
        try {
            // Récupérer tous les fandoms avec le nombre de membres et de posts, triés par nombre de membres décroissant
            $fandoms = \App\Models\Fandom::withCount(['members', 'posts'])
                ->orderByDesc('members_count')
                ->get();

            // Vérifier s'il y a des fandoms
            if ($fandoms->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No fandoms found',
                    'data' => [
                        'fandoms' => [],
                        'total' => 0
                    ]
                ]);
            }

            // Formater les fandoms pour inclure toutes les informations de la table + compteurs
            $formattedFandoms = $fandoms->map(function ($fandom) {
                // Récupérer toutes les données de la table fandom
                $fandomData = $fandom->toArray();

                // Ajouter les compteurs
                $fandomData['members_count'] = $fandom->members_count ?? 0;
                $fandomData['posts_count'] = $fandom->posts_count ?? 0;

                return $fandomData;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'fandoms' => $formattedFandoms,
                    'total' => $fandoms->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving trending fandoms: ' . $e->getMessage()
            ], 500);
        }
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
     * Supprimer un membre d'un fandom (admin seulement)
     * Route: DELETE /api/Y/fandoms/{fandom_id}/members/{user_id}
     */
    public function removeMemberFromFandom($fandom_id, $user_id)
    {
        $admin = Auth::user();
        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Vérifier que le fandom existe
        $fandom = \App\Models\Fandom::find($fandom_id);
        if (!$fandom) {
            return response()->json(['success' => false, 'message' => 'Fandom not found'], 404);
        }

        // Vérifier que l'admin est un administrateur de ce fandom
        $adminMembership = \App\Models\Member::where('user_id', $admin->id)
            ->where('fandom_id', $fandom_id)
            ->where('role', 'admin')
            ->first();

        if (!$adminMembership) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You must be an admin of this fandom.'
            ], 403);
        }

        // Vérifier que l'utilisateur cible existe
        $targetUser = \App\Models\User::find($user_id);
        if (!$targetUser) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        // Vérifier que l'utilisateur cible est membre du fandom
        $targetMembership = \App\Models\Member::where('user_id', $user_id)
            ->where('fandom_id', $fandom_id)
            ->first();

        if (!$targetMembership) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a member of this fandom'
            ], 404);
        }

        // Empêcher l'admin de se supprimer lui-même
        if ($admin->id == $user_id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot remove yourself from the fandom'
            ], 403);
        }

        // Compter le nombre d'admins restants
        $adminCount = \App\Models\Member::where('fandom_id', $fandom_id)
            ->where('role', 'admin')
            ->count();

        // Si l'utilisateur cible est admin et qu'il est le seul admin, empêcher la suppression
        if ($targetMembership->role === 'admin' && $adminCount <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove the last admin from the fandom'
            ], 403);
        }

        // Supprimer le membre
        $targetMembership->delete();

        return response()->json([
            'success' => true,
            'message' => 'Member removed successfully',
            'data' => [
                'removed_user_id' => $targetUser->id,
                'removed_username' => $targetUser->name,
                'removed_role' => $targetMembership->role,
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

        // Vérifier que le fandom existe
        $fandom = \App\Models\Fandom::find($fandom_id);
        if (!$fandom) {
            return response()->json(['success' => false, 'message' => 'Fandom not found'], 404);
        }

        // Vérifier que l'utilisateur est membre du fandom
        $membership = \App\Models\Member::where('user_id', $user->id)
            ->where('fandom_id', $fandom_id)
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You must be a member of this fandom to post.'
            ], 403);
        }

        $validated = $request->validate([
            'schedule_at' => 'nullable|date',
            'description' => 'nullable|string',
            'content_status' => 'required|in:draft,published,archived',
            'medias' => 'nullable|array',
            'medias.*' => 'file|mimes:jpg,jpeg,png,mp4,mov|max:20480',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:255',
        ]);

        $validated['user_id'] = $user->id;
        $validated['fandom_id'] = $fandom_id; // Utiliser fandom_id au lieu de subcategory_id
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
            'success' => true,
            'message' => 'Post créé avec succès dans le fandom.',
            'post' => [
                'id' => $post->id,
                'description' => $post->description,
                'fandom_id' => $post->fandom_id,
                'media' => method_exists($post, 'medias') ? $post->medias->pluck('file_path')->toArray() : [],
                'tags' => method_exists($post, 'tags') ? $post->tags->pluck('tag_name')->toArray() : [],
                'content_status' => $post->content_status,
                'schedule_at' => $post->schedule_at,
                'createdAt' => $post->created_at ? $post->created_at->toISOString() : null
            ]
        ], 201);
    }

    /**
     * Permettre aux membres d'un fandom de mettre à jour leur post
     * Route: PUT /api/Y/fandoms/{fandom_id}/posts/{post_id}
     */
    public function updatePostInFandom($fandom_id, $post_id, Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Vérifier que le fandom existe
        $fandom = \App\Models\Fandom::find($fandom_id);
        if (!$fandom) {
            return response()->json(['success' => false, 'message' => 'Fandom not found'], 404);
        }

        // Trouver le post
        $post = \App\Models\Post::where('id', $post_id)
            ->where('fandom_id', $fandom->id)
            ->first();

        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found in this fandom'], 404);
        }

        // Vérifier les permissions (propriétaire du post ou admin/moderator du fandom)
        $canEdit = false;
        if ($post->user_id === $user->id) {
            $canEdit = true; // Propriétaire du post
        } else {
            // Vérifier si l'utilisateur est admin ou moderator du fandom
            $membership = \App\Models\Member::where('user_id', $user->id)
                ->where('fandom_id', $fandom->id)
                ->whereIn('role', ['admin', 'moderator'])
                ->first();
            if ($membership) {
                $canEdit = true;
            }
        }

        if (!$canEdit) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only edit your own posts or you must be an admin/moderator.'
            ], 403);
        }

        $validated = $request->validate([
            'schedule_at' => 'nullable|date',
            'description' => 'nullable|string',
            'content_status' => 'sometimes|in:draft,published,archived',

            'tags' => 'nullable|array',
            'tags.*' => 'string|max:255',
        ]);

        // Mettre à jour les champs fournis
        $updateData = [];
        if ($request->has('description')) $updateData['description'] = $request->description;
        if ($request->has('content_status')) $updateData['content_status'] = $request->content_status;
        if ($request->has('schedule_at')) $updateData['schedule_at'] = $request->schedule_at;

        if (!empty($updateData)) {
            $post->update($updateData);
        }

        // Gérer les tags si fournis
        if ($request->has('tags')) {
            $tags = $validated['tags'] ?? [];
            // Supprimer les anciens tags
            $post->tags()->detach();
            // Ajouter les nouveaux tags
            if (!empty($tags)) {
                foreach ($tags as $tagName) {
                    $tag = \App\Models\Tag::firstOrCreate(['tag_name' => $tagName]);
                    $post->tags()->syncWithoutDetaching($tag->id);
                }
            }
        }

        // Gérer l'upload de nouveaux médias (ajoute, ne supprime pas les anciens)
        // Charger les tags pour la réponse
        $post->load('tags');
        return response()->json([
            'success' => true,
            'message' => 'Post créé avec succès dans le fandom.',
            'post' => [
                'id' => $post->id,
                'description' => $post->description,
                'fandom_id' => $post->fandom_id,
                'media' => method_exists($post, 'medias') ? $post->medias->pluck('file_path')->toArray() : [],
                'tags' => method_exists($post, 'tags') ? $post->tags->pluck('tag_name')->toArray() : [],
                'content_status' => $post->content_status,
                'schedule_at' => $post->schedule_at,
                'createdAt' => $post->created_at ? $post->created_at->toISOString() : null
            ]
        ], 201);
    }

    /**
     * Permettre aux membres d'un fandom de supprimer leur post
     * Route: DELETE /api/Y/fandoms/{fandom_id}/posts/{post_id}
     */
    public function deletePostInFandom($fandom_id, $post_id, Request $request)
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

        // Trouver le post
        $post = \App\Models\Post::where('id', $post_id)
            ->where('fandom_id', $fandom->id)
            ->first();

        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found in this fandom'], 404);
        }

        // Vérifier les permissions (propriétaire du post ou admin/moderator du fandom)
        $canDelete = false;
        if ($post->user_id === $user->id) {
            $canDelete = true; // Propriétaire du post
        } else {
            // Vérifier si l'utilisateur est admin ou moderator du fandom
            $membership = \App\Models\Member::where('user_id', $user->id)
                ->where('fandom_id', $fandom->id)
                ->whereIn('role', ['admin', 'moderator'])
                ->first();
            if ($membership) {
                $canDelete = true;
            }
        }

        if (!$canDelete) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only delete your own posts or you must be an admin/moderator.'
            ], 403);
        }

        // Supprimer les médias associés
        $post->medias()->delete();

        // Supprimer les tags associés
        $post->tags()->detach();

        // Supprimer le post
        $post->delete();

        return response()->json([
            'success' => true,
            'message' => 'Post deleted successfully'
        ], 200);
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

    public function getTrendingHashtags(Request $request) {
        $limit = min(50, max(1, (int) $request->get('limit', 10)));
        $days = max(1, (int) $request->get('days', 7)); // Trending sur les X derniers jours

        // Calculer la date de début
        $startDate = now()->subDays($days);

        // Récupérer les hashtags les plus utilisés dans les posts récents
        $trendingTags = \App\Models\Tag::withCount(['posts' => function($query) use ($startDate) {
                $query->where('posts.content_status', 'published')
                      ->where('posts.created_at', '>=', $startDate);
            }])
            ->having('posts_count', '>', 0)
            ->orderByDesc('posts_count')
            ->limit($limit)
            ->get();

        // Formater les hashtags simplement
        $formattedTags = $trendingTags->map(function($tag) {
            return [
                'id' => $tag->id,
                'tag_name' => $tag->tag_name,
                'posts_count' => $tag->posts_count,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'hashtags' => $formattedTags,
            ]
        ]);
    }

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



    private function getPostsByTag($tag, $page, $limit) {
        // Récupérer les posts associés à ce tag avec pagination
        $postsQuery = \App\Models\Post::whereHas('tags', function($query) use ($tag) {
                $query->where('tag_id', $tag->id);
            })
            ->where('content_status', 'published') // Seulement les posts publiés
            ->with(['user:id,first_name,last_name,email,profile_image,bio', 'medias', 'tags', 'fandom:id,name'])
            ->withCount(['favorites', 'comments']) // Compter les likes et commentaires
            ->orderBy('created_at', 'desc');

        $posts = $postsQuery->paginate($limit, ['*'], 'page', $page);

        // Compter le total de posts pour ce hashtag
        $totalPosts = \App\Models\Post::whereHas('tags', function($query) use ($tag) {
            $query->where('tag_id', $tag->id);
        })->where('content_status', 'published')->count();

        // Calculer la croissance (posts du mois dernier vs ce mois)
        $currentMonth = \App\Models\Post::whereHas('tags', function($query) use ($tag) {
            $query->where('tag_id', $tag->id);
        })
        ->where('content_status', 'published')
        ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
        ->count();

        $lastMonth = \App\Models\Post::whereHas('tags', function($query) use ($tag) {
            $query->where('tag_id', $tag->id);
        })
        ->where('content_status', 'published')
        ->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
        ->count();

        $growth = $lastMonth > 0 ? round((($currentMonth - $lastMonth) / $lastMonth) * 100, 1) : 0;
        $growthText = $growth > 0 ? "+{$growth}%" : "{$growth}%";

        // Déterminer la catégorie la plus fréquente pour ce hashtag
        $topCategory = \App\Models\Post::whereHas('tags', function($query) use ($tag) {
            $query->where('tag_id', $tag->id);
        })
        ->whereNotNull('subcategory_id')
        ->with('subcategory.category')
        ->get()
        ->groupBy('subcategory.category.name')
        ->sortByDesc(function($posts) {
            return $posts->count();
        })
        ->keys()
        ->first() ?? 'General';

        // Formater les posts
        $formattedPosts = collect($posts->items())->map(function ($post) {
            $user = $post->user;
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
                        'file_type' => $media->file_type,
                        'file_size' => $media->file_size,
                    ];
                })->toArray() : [],
                'tags' => $post->tags ? $post->tags->pluck('tag_name')->toArray() : [],
                'likes_count' => $post->favorites_count ?? 0,
                'comments_count' => $post->comments_count ?? 0,
                'feedback' => $post->feedback ?? 0,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'hashtag' => $tag->tag_name,
                'tag_id' => $tag->id,
                'posts' => $formattedPosts,
                'stats' => [
                    'totalPosts' => $totalPosts,
                    'growth' => $growthText,
                    'category' => $topCategory,
                    'currentMonth' => $currentMonth,
                    'lastMonth' => $lastMonth
                ],
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

    /**
     * Rechercher des utilisateurs par nom avec pagination
     * Route: GET /api/search/users
     */
    public function searchUsers(Request $request)
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

        // Recherche dans les utilisateurs par first_name, last_name ou email
        $users = \App\Models\User::where(function($q) use ($query) {
                $q->where('first_name', 'LIKE', "%{$query}%")
                  ->orWhere('last_name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->select('id', 'first_name', 'last_name', 'email', 'profile_image', 'bio', 'created_at')
            ->orderBy('first_name', 'asc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Formater les données pour inclure le nom complet
        $formattedUsers = $users->getCollection()->map(function ($user) {
            return [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'profile_image' => $user->profile_image,
                'bio' => $user->bio,
                'created_at' => $user->created_at
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'search_query' => $query,
                'users' => $formattedUsers,
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'total_pages' => $users->lastPage(),
                    'total_items' => $users->total(),
                    'per_page' => $users->perPage(),
                    'has_more' => $users->hasMorePages(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem()
                ]
            ]
        ], 200);
    }

    /**
     * Rechercher des posts par tags, description ou sous-catégorie avec pagination
     * Route: GET /api/Y/search/posts
     */
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
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Formater les données des posts
        $formattedPosts = $posts->getCollection()->map(function ($post) {
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
                'likes_count' => $post->favorites ? $post->favorites()->count() : 0,
                'comments_count' => $post->comments ? $post->comments()->count() : 0
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

    /**
     * Rechercher des fandoms avec pagination
     * Route: GET /api/Y/search/fandom
     */
    public function searchFandomsPaginated(Request $request)
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

        // Recherche dans les fandoms par nom, description
        $fandoms = \App\Models\Fandom::where(function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%");
            })
            ->with(['subcategory:id,name'])
            ->withCount(['posts', 'members'])
            ->orderBy('name', 'asc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Obtenir les rôles de l'utilisateur pour tous les fandoms s'il est authentifié
        $userMemberships = [];
        if ($user) {
            $fandomIds = $fandoms->pluck('id')->toArray();
            $memberships = \App\Models\Member::where('user_id', $user->id)
                ->whereIn('fandom_id', $fandomIds)
                ->get();
            foreach ($memberships as $membership) {
                $userMemberships[$membership->fandom_id] = $membership->role;
            }
        }

        // Formater les données des fandoms
        $formattedFandoms = $fandoms->getCollection()->map(function ($fandom) use ($userMemberships) {
            return [
                'id' => $fandom->id,
                'name' => $fandom->name,
                'description' => $fandom->description,
                'cover_image' => $fandom->cover_image,
                'logo_image' => $fandom->logo_image,
                'subcategory_id' => $fandom->subcategory_id,
                'subcategory' => $fandom->subcategory ? [
                    'id' => $fandom->subcategory->id,
                    'name' => $fandom->subcategory->name
                ] : null,
                'posts_count' => $fandom->posts_count ?? 0,
                'members_count' => $fandom->members_count ?? 0,
                'is_member' => isset($userMemberships[$fandom->id]),
                'member_role' => $userMemberships[$fandom->id] ?? null,
                'created_at' => $fandom->created_at
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'search_query' => $query,
                'fandoms' => $formattedFandoms,
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
        ], 200);
    }

    /**
     * Récupérer les posts d'une sous-catégorie avec leurs médias
     * Route: GET /api/Y/subcategories/{subcategory}/content
     */
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

    /**
     * Récupérer les fandoms d'une sous-catégorie
     * Route: GET /api/Y/subcategories/{subcategory_id}/fandoms
     */
    public function getSubcategoryFandoms($subcategoryId)
    {
        // Récupérer la sous-catégorie avec sa catégorie parent
        $subcategory = \App\Models\SubCategory::with(['category'])->find($subcategoryId);

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

    /**
     * Récupérer tous les favoris de l'utilisateur authentifié (posts et produits)
     * Route: GET /api/Y/favorites
     */


}
