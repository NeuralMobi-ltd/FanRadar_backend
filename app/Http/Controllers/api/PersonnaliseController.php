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
use Carbon\Carbon;
use App\Models\Fandom;
use App\Models\Member;

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
                    // Optionnel
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
    public function getFandoms(Request $request)
     {
        // Charger members + sous-catégorie + catégorie pour retourner les noms
        $fandoms = Fandom::with(['members', 'subcategory.category'])->orderBy('created_at', 'desc')->get();

        $items = $fandoms->map(function ($fandom) {
            // Trouver le membre modérateur ou admin
            $moderatorMember = $fandom->members->firstWhere('role', 'moderator') ?? $fandom->members->firstWhere('role', 'admin') ?? $fandom->members->first();

            $moderator = null;
            if ($moderatorMember) {
                $moderator = [
                    'user_id' => $moderatorMember->user_id ?? null,
                    'role' => $moderatorMember->role ?? null,
                ];
            }

            // Counts optionnels
            $num_members = null;
            $num_posts = null;
            if (method_exists($fandom, 'members')) {
                try { $num_members = $fandom->members()->count(); } catch (\Throwable $e) { $num_members = null; }
            }
            if (method_exists($fandom, 'posts')) {
                try { $num_posts = $fandom->posts()->count(); } catch (\Throwable $e) { $num_posts = null; }
            }

            return [
                'id' => $fandom->id,
                'name' => $fandom->name,
                // Retourner les noms au lieu des ids
                'category' => $fandom->subcategory && $fandom->subcategory->category ? $fandom->subcategory->category->name : null,
                'subcategory' => $fandom->subcategory ? $fandom->subcategory->name : null,
                'description' => $fandom->description ?? null,
                'image' => $this->makeImageUrl($fandom->cover_image ?? $fandom->logo_image ?? null),
                'date' => $fandom->created_at ? $fandom->created_at->toISOString() : null,
                'moderator' => $moderator,
                'num_members' => $num_members,
                'num_posts' => $num_posts,
            ];
        })->toArray();

        return response()->json(['success' => true, 'data' => ['fandoms' => $items]]);
    }

    /**
     * Récupérer un fandom par id (simplifié)
     * Route: GET /api/fandoms/{id}
     */
    public function showFandom($idOrHandle)
    {
        // Accepter soit un id numérique soit un nom/handle (ex: "Test")
        $key = urldecode($idOrHandle);

        // Essayer par id d'abord (gère les valeurs numériques)
        $fandom = Fandom::with(['members', 'subcategory.category'])->find($key);

        // Si non trouvé, tenter par nom (insensible à la casse)
        if (!$fandom) {
            $fandom = Fandom::with(['members', 'subcategory.category'])
                ->whereRaw('LOWER(name) = ?', [strtolower($key)])
                ->orWhere('name', $key)
                ->first();
        }

        if (!$fandom) {
            return response()->json(['success' => false, 'message' => 'Fandom non trouvé'], 404);
        }

        $moderatorMember = $fandom->members->firstWhere('role', 'moderator') ?? $fandom->members->firstWhere('role', 'admin') ?? $fandom->members->first();
        $moderator = null;
        if ($moderatorMember) {
            $moderator = [
                'user_id' => $moderatorMember->user_id ?? null,
                'role' => $moderatorMember->role ?? null,
            ];
        }

        $num_members = null;
        $num_posts = null;
        if (method_exists($fandom, 'members')) {
            try { $num_members = $fandom->members()->count(); } catch (\Throwable $e) { $num_members = null; }
        }
        if (method_exists($fandom, 'posts')) {
            try { $num_posts = $fandom->posts()->count(); } catch (\Throwable $e) { $num_posts = null; }
        }

        $out = [
            'id' => $fandom->id,
            'name' => $fandom->name,
            'category' => $fandom->subcategory && $fandom->subcategory->category ? $fandom->subcategory->category->name : null,
            'subcategory' => $fandom->subcategory ? $fandom->subcategory->name : null,
            'description' => $fandom->description ?? null,
            'image' => $this->makeImageUrl($fandom->cover_image ?? $fandom->logo_image ?? null),
            'date' => $fandom->created_at ? $fandom->created_at->toISOString() : null,
            'moderator' => $moderator,
            'num_members' => $num_members,
            'num_posts' => $num_posts,
        ];

        return response()->json(['success' => true, 'fandom' => $out]);
    }

    /**
     * Créer un fandom. Le body peut fournir uniquement moderator_user_id (integer).
     * Route: POST /api/fandoms
     * Required body: name
     * Optional: description, cover_image (file ou URL), category_id, subcategory_id, date, user_id, moderator_user_id, role
     */
    public function createFandom(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:8192',
            'category_id' => 'nullable|integer|exists:categories,id',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
            'date' => 'nullable|date',
            'user_id' => 'nullable|integer|exists:users,id',
            'moderator_user_id' => 'nullable|integer|exists:users,id',
            'role' => 'nullable|string|in:moderator,admin',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $moderatorUserId = $request->input('user_id') ?? $request->input('moderator_user_id');
        $role = $request->input('role', 'moderator');

        $moderatorUser = null;
        if ($moderatorUserId) {
            $moderatorUser = \App\Models\User::find($moderatorUserId);
            if (!$moderatorUser) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }
        } else {
            $moderatorUser = Auth::user();
        }

        // Ne pas inclure category_id dans les données envoyées à la table (pas de colonne dans la BDD)
        $data = [
            'name' => $request->input('name'),
            'description' => $request->input('description') ?? null,
            'subcategory_id' => $request->input('subcategory_id') ?? null,
        ];

        // Gérer l'image (upload ou URL) dans le champ 'image' -> stocker en base dans 'cover_image'
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            try {
                $path = \Illuminate\Support\Facades\Storage::disk('public')->putFile('fandoms/images', $request->file('image'));
                if ($path) {
                    $data['cover_image'] = 'storage/' . $path;
                } else {
                    return response()->json(['success' => false, 'message' => 'Unable to store uploaded image'], 500);
                }
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => 'Image upload error', 'error' => $e->getMessage()], 500);
            }
        } elseif ($request->filled('image')) {
            $data['cover_image'] = $request->input('image');
        }

        if ($request->filled('date')) {
            try {
                $data['created_at'] = \Carbon\Carbon::parse($request->input('date'));
            } catch (\Throwable $e) {}
        }

        try {
            \DB::beginTransaction();
            $fandom = Fandom::create($data);
            if ($moderatorUser) {
                $existing = \App\Models\Member::where('user_id', $moderatorUser->id)
                    ->where('fandom_id', $fandom->id)
                    ->first();
                if ($existing) {
                    if (($existing->role ?? '') !== $role) {
                        $existing->role = $role;
                        $existing->save();
                    }
                } else {
                    \App\Models\Member::create([
                        'user_id' => $moderatorUser->id,
                        'fandom_id' => $fandom->id,
                        'role' => $role,
                    ]);
                }
            }
            \DB::commit();
        } catch (\Throwable $e) {
            \DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Unable to create fandom', 'error' => $e->getMessage()], 500);
        }

        // Recharger relations pour pouvoir retourner les noms
        $fandom->load(['subcategory.category', 'members']);

        $moderatorMember = $fandom->members()->where('role', 'moderator')->orWhere('role', 'admin')->where('user_id', $moderatorUser?->id ?? null)->first();
        if (!$moderatorMember && $moderatorUser) {
            $moderatorMember = $fandom->members()->where('user_id', $moderatorUser->id)->first();
        }
        $moderator = null;
        if ($moderatorMember) {
            $moderator = [
                'user_id' => $moderatorMember->user_id ?? null,
                'role' => $moderatorMember->role ?? null,
            ];
        }

        $out = [
            'id' => $fandom->id,
            'name' => $fandom->name,
            // Retourner noms au lieu des ids
            'category' => $fandom->subcategory && $fandom->subcategory->category ? $fandom->subcategory->category->name : null,
            'subcategory' => $fandom->subcategory ? $fandom->subcategory->name : null,
            'description' => $fandom->description ?? null,
            'image' => $this->makeImageUrl($fandom->cover_image ?? $fandom->logo_image ?? null),
            'date' => $fandom->created_at ? $fandom->created_at->toISOString() : null,
            'moderator' => $moderator,
        ];

        return response()->json(['success' => true, 'fandom' => $out], 201);
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

    /**
     * Supprimer un fandom (admin seulement)
     * Route: DELETE /api/Y/fandoms/{id}
     */
    public function deleteFandom($id)
    {
        $f = \App\Models\Fandom::find($id);
        if (!$f) return response()->json(['success' => false, 'error' => 'Fandom not found'], 404);
        $f->delete();
        return response()->json(['success' => true, 'message' => 'Fandom deleted']);
    }
    /**
     * Convertit un chemin d'image en URL complète si nécessaire
     */
    protected function makeImageUrl($path)
    {
        if (empty($path)) return null;
        // Si c'est déjà une URL complète, la retourner telle quelle
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }
        // Si le chemin commence par 'storage/' ou '/' on construit l'URL complète
        return url($path);
    }
}
