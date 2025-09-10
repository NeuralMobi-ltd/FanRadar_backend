<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class UserController extends Controller
{
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

}
