<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Schema;

class M_Controller extends Controller
{
    /**
     * Exemple de méthode d'API
     */


    /**
     * Obtenir tous les rôles et permissions
     * Route: GET /api/roles-permissions
     */
    public function getAllRolesAndPermissions()
    {
        $roles = \Spatie\Permission\Models\Role::with('permissions')->get();
        $result = $roles->map(function($role) {
            // Correction : $role->permissions peut être une string (json) ou une relation
            $permissions = is_iterable($role->permissions) ? collect($role->permissions)->pluck('name')->toArray() : [];
            $permissions_array = is_string($role->permissions) ? json_decode($role->permissions, true) : [];
            return [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'description' => $role->description,
                'permissions' => $permissions,
                'permissions_array' => $permissions_array,
            ];
        });
        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    /**
     * a. Get all users (tous les champs de la table users)
     * Route: GET /api/users
     */
    public function getAllUsers()
    {
        $users = \App\Models\User::all()->map(function($user) {
            return [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'profile_image' => $user->profile_image,
                'background_image' => $user->background_image,
                'date_naissance' => $user->date_naissance,
                'bio' => $user->bio,
                'gender' => $user->gender,
                'role' => $user->getRoleNames()->first() ?? 'user', // Premier rôle ou 'user' par défaut
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                // Statistiques supplémentaires
                'posts_count' => $user->posts()->count(),
                'followers_count' => $user->followers()->count(),
                'following_count' => $user->following()->count(),
                'fandoms_count' => $user->members()->count(),
            ];
        });
        return response()->json(['success' => true, 'data' => $users]);
    }

    /**
     * b. Get user by id, email (tous les champs de la table users)
     * Route: GET /api/user/{value}
     */
    public function getUser($value)
    {
        $user = \App\Models\User::where('id', $value)->orWhere('email', $value)->first();
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'User not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'profile_image' => $user->profile_image,
                'background_image' => $user->background_image,
                'date_naissance' => $user->date_naissance,
                'bio' => $user->bio,
                'gender' => $user->gender,
                'role' => $user->getRoleNames()->first() ?? 'user', // Premier rôle ou 'user' par défaut
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                // Statistiques supplémentaires
                'posts_count' => $user->posts()->count(),
                'followers_count' => $user->followers()->count(),
                'following_count' => $user->following()->count(),
                'fandoms_count' => $user->members()->count(),
            ]
        ]);
    }

    /**
     * c. Add user (tous les champs disponibles) - Seul admin peut créer des utilisateurs
     * Route: POST /api/users
     */
    public function addUser(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            // Champs optionnels
            'profile_image' => 'nullable|string|max:500',
            'background_image' => 'nullable|string|max:500',
            'date_naissance' => 'nullable|date',
            'bio' => 'nullable|string|max:1000',
            'gender' => 'nullable|string|in:male,female,other',
            'role' => 'nullable|in:user,admin,writer',
        ]);

        $user = \App\Models\User::create([
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'profile_image' => $request->profile_image,
            'background_image' => $request->background_image,
            'date_naissance' => $request->date_naissance,
            'bio' => $request->bio,
            'gender' => $request->gender,
        ]);

        // Assigner le rôle avec Spatie Permissions
        if ($request->role) {
            $user->assignRole($request->role);
        } else {
            $user->assignRole('user'); // Rôle par défaut
        }

        // Recharger l'utilisateur avec les rôles
        $user = $user->fresh();
        $userData = $user->toArray();
        $userData['role'] = $user->getRoleNames()->first() ?? 'user'; // Premier rôle ou 'user' par défaut

        return response()->json(['success' => true, 'data' => $userData], 201);
    }

    /**
     * d. Update user (id)
     * Route: PUT /api/users/{id}
     */
    public function updateUser(Request $request, $id)
    {
        $user = \App\Models\User::find($id);
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'User not found'], 404);
        }

        $request->validate([
            'email' => 'nullable|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'date_naissance' => 'nullable|date',
            'bio' => 'nullable|string|max:1000',
            'gender' => 'nullable|string|in:male,female,other',
            'role' => 'nullable|in:user,admin,writer',
        ]);

        // Met à jour tous les champs envoyés dans la requête
        $updatable = ['first_name', 'last_name', 'email', 'profile_image', 'background_image', 'date_naissance', 'bio', 'gender'];
        foreach ($updatable as $field) {
            if ($request->has($field)) {
                $user->$field = $request->$field;
            }
        }

        // Gérer le mot de passe séparément
        if ($request->has('password')) {
            $user->password = bcrypt($request->password);
        }

        $user->save();

        // Mettre à jour le rôle avec Spatie Permissions si fourni
        if ($request->has('role')) {
            $user->syncRoles([$request->role]); // syncRoles remplace tous les rôles existants
        }

        // Recharger l'utilisateur avec les rôles
        $user = $user->fresh();
        $userData = $user->toArray();
        $userData['role'] = $user->getRoleNames()->first() ?? 'user'; // Premier rôle ou 'user' par défaut

        return response()->json(['success' => true, 'data' => $userData]);
    }

    /**
     * e. Delete user (id)
     * Route: DELETE /api/M/users/{id}
     */
    public function deleteUser($id)
    {
        $user = \App\Models\User::find($id);
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'User not found'], 404);
        }

        // Supprimer l'utilisateur
        $user->delete();

        return response()->json(['success' => true, 'message' => 'User deleted successfully']);
    }

    /**
     * f. Get categories (id, name)
     * URL: GET /api/categories-simple
     */
    public function getCategoriesSimple()
    {
        $categories = \App\Models\Category::select('id', 'name')->get();
        return response()->json(['success' => true, 'data' => $categories]);
    }

    /**
     * g. Get subcategories (id, cat_id, name)
     * URL: GET /api/subcategories-simple
     */
    public function getSubcategoriesSimple()
    {
        $subcategories = \App\Models\SubCategory::select('id', 'category_id as cat_id', 'name')->get();
        return response()->json(['success' => true, 'data' => $subcategories]);
    }

    /**
     * h. Get categories with sub category (id_cat, name, sub : [id, name])
     * URL: GET /api/categories-with-subs
     */
    public function getCategoriesWithSubs()
    {
        $categories = \App\Models\Category::with(['subcategories:id,category_id,name'])->get(['id','name']);
        $result = $categories->map(function($cat) {
            return [
                'id_cat' => $cat->id,
                'name' => $cat->name,
                'sub' => $cat->subcategories->map(function($sub) {
                    return [
                        'id' => $sub->id,
                        'name' => $sub->name
                    ];
                })
            ];
        });
        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * i. Add category (name)
     * URL: POST /api/categories-simple
     */
    public function addCategorySimple(Request $request)
    {
        $request->validate(['name' => 'required|string']);
        $cat = \App\Models\Category::create(['name' => $request->name]);
        return response()->json(['success' => true, 'data' => $cat], 201);
    }

    /**
     * j. Add subcategory (name, id_cat)
     * URL: POST /api/subcategories-simple
     */
    public function addSubcategorySimple(Request $request)
    {
        $request->validate(['name' => 'required|string', 'id_cat' => 'required|integer|exists:categories,id']);
        $sub = \App\Models\SubCategory::create(['name' => $request->name, 'category_id' => $request->id_cat]);
        return response()->json(['success' => true, 'data' => $sub], 201);
    }

    /**
     * k. Delete category (id), delete subcategory (id)
     * URL: DELETE /api/categories-simple/{id}, DELETE /api/subcategories-simple/{id}
     */
    public function deleteCategorySimple($id)
    {
        $cat = \App\Models\Category::find($id);
        if (!$cat) return response()->json(['success' => false, 'error' => 'Category not found'], 404);
        $cat->delete();
        return response()->json(['success' => true, 'message' => 'Category deleted']);
    }
    public function deleteSubcategorySimple($id)
    {
        $sub = \App\Models\SubCategory::find($id);
        if (!$sub) return response()->json(['success' => false, 'error' => 'Subcategory not found'], 404);
        $sub->delete();
        return response()->json(['success' => true, 'message' => 'Subcategory deleted']);
    }

    /**
     * Get all tags (id, tag)
     * Route: GET /api/tags-simple
     */
    public function getAllTagsSimple()
    {
        $tags = \App\Models\Tag::select('id', 'tag_name as tag')->get();
        return response()->json(['success' => true, 'data' => $tags]);
    }

    /**
     * Add tag (tag)
     * Route: POST /api/tags-simple
     */
    public function addTagSimple(Request $request)
    {
        $request->validate(['tag' => 'required|string|unique:tags,tag_name']);
        $tag = \App\Models\Tag::create(['tag_name' => $request->tag]);
        return response()->json(['success' => true, 'data' => $tag], 201);
    }

    /**
     * POSTS
     */
    // a. Get posts (id, author, fandom, date, likes, category, title, content, media)
    public function getAllPostsSimple()
    {
        $posts = \App\Models\Post::with(['user:id,first_name', 'category:id,name', 'medias'])
            ->get()
            ->map(function($post) {
                return [
                    'id' => $post->id,
                    'author' => $post->user ? trim($post->user->first_name . ' ' . ($post->user->last_name ?? '')) : null,
                    'fandom' => $post->fandom ? $post->fandom->name : 'General',
                    'date' => $post->created_at,
                    'likes' => $post->likes ?? 0,
                    'category' => $post->category->name ?? null,
                    // provide title/content from DB description if title/content columns not present
                    'title' => $post->title ?? (is_string($post->description) ? (mb_strimwidth($post->description, 0, 50, '...')) : null),
                    'content' => $post->content ?? $post->description ?? null,
                    'media' => $post->medias->filter(function($media) {
                        return !empty($media->file_path);
                    })->map(function($media) {
                        return asset('storage/' . $media->file_path);
                    })->values()->toArray(),
                ];
            });
        return response()->json(['success' => true, 'data' => $posts]);
    }

    // b. Add post
    public function addPostSimple(Request $request)
    {
        $request->validate([
            'user_id' => 'required_without:author_id|integer|exists:users,id',
            'author_id' => 'required_without:user_id|integer|exists:users,id',
            'title' => 'nullable|string',
            'content' => 'nullable|string',
        ]);

        $authorId = $request->user_id ?? $request->author_id;

        // Build data only with columns that actually exist in the posts table
        $columns = Schema::getColumnListing('posts');
        $data = [];
        if (in_array('user_id', $columns)) $data['user_id'] = $authorId;
        // Map incoming title/content into description column if title/content not present
        if (in_array('title', $columns)) $data['title'] = $request->title ?? null;
        if (in_array('content', $columns)) $data['content'] = $request->content ?? null;
        if (in_array('description', $columns)) $data['description'] = $request->content ?? $request->title ?? $request->description ?? null;
        if (in_array('category_id', $columns) && $request->filled('category_id')) $data['category_id'] = $request->category_id;
        if (in_array('subcategory_id', $columns) && $request->filled('subcategory_id')) $data['subcategory_id'] = $request->subcategory_id;

        $post = \App\Models\Post::create($data);

        // 2. Puis upload les fichiers et lie-les au post
        if ($request->hasFile('media')) {
            $files = $request->file('media');
            if (!is_array($files)) $files = [$files];
            foreach ($files as $file) {
                if ($file && $file->isValid()) {
                    $path = $file->store('images', 'public');
                    $post->medias()->create([
                        'file_path' => $path,
                        'media_type' => $file->getClientMimeType(),
                    ]);
                }
            }
        }

        // Recharge le post avec les relations utiles
        $post = \App\Models\Post::with(['medias','user','category','subcategory','fandom'])->find($post->id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $post->id,
                'author' => $post->user ? trim($post->user->first_name . ' ' . ($post->user->last_name ?? '')) : null,
                'fandom' => $post->fandom ? $post->fandom->name : 'General',
                'category_id' => in_array('category_id', $columns) ? ($post->category_id ?? null) : null,
                'subcategory_id' => in_array('subcategory_id', $columns) ? ($post->subcategory_id ?? null) : null,
                'title' => $post->title ?? (is_string($post->description) ? (mb_strimwidth($post->description, 0, 50, '...')) : null),
                'content' => $post->content ?? $post->description ?? null,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
                'media' => $post->medias->filter(function($m) { return !empty($m->file_path); })->map(function($m) { return asset('storage/' . $m->file_path); })->values()->toArray(),
            ]
        ], 201);
    }

    // c. Delete post
    public function deletePostSimple($id)
    {
        $post = \App\Models\Post::find($id);
        if (!$post) return response()->json(['success' => false, 'error' => 'Post not found'], 404);
        $post->delete();
        return response()->json(['success' => true, 'message' => 'Post deleted']);
    }

    // d. Update post
    public function updatePostSimple(Request $request, $id)
    {
        $post = \App\Models\Post::find($id);
        if (!$post) return response()->json(['success' => false, 'error' => 'Post not found'], 404);

        // Build update data only with columns that actually exist
        $columns = Schema::getColumnListing('posts');
        $updatable = array_intersect(['title', 'content', 'category_id', 'subcategory_id', 'fandom_id'], $columns);

        $updateData = $request->only($updatable);
        if ($request->has('media')) {
            $updateData['media'] = $request->media;
        }

        $post->update($updateData);

        // If description exists but title/content columns do not, update description from request
        if (in_array('description', $columns) && ($request->filled('content') || $request->filled('title'))) {
            $post->description = $request->content ?? $request->title ?? $post->description;
            $post->save();
        }

        // Update tags if provided
        if ($request->has('tags') && is_array($request->tags)) {
            $post->tags()->detach();
            foreach ($request->tags as $tagName) {
                $tag = \App\Models\Tag::firstOrCreate(['tag_name' => $tagName]);
                $post->tags()->attach($tag->id);
            }
        }

        // Reload post with useful relations
        $post = \App\Models\Post::with(['medias','user','category','subcategory','fandom'])->find($post->id);

        return response()->json(['success' => true, 'data' => [
            'id' => $post->id,
            'author' => $post->user ? trim($post->user->first_name . ' ' . ($post->user->last_name ?? '')) : null,
            'fandom' => $post->fandom ? $post->fandom->name : 'General',
            'title' => $post->title ?? (is_string($post->description) ? (mb_strimwidth($post->description, 0, 50, '...')) : null),
            'content' => $post->content ?? $post->description ?? null,
            'media' => $post->medias->filter(function($m) { return !empty($m->file_path); })->map(function($m) { return asset('storage/' . $m->file_path); })->values()->toArray(),
            'updated_at' => $post->updated_at,
        ]]);
    }

    // e. Get posts by tag
    public function getPostsByTagSimple($tag)
    {
        $posts = \App\Models\Post::whereHas('tags', function($q) use ($tag) {
            $q->where('tag_name', $tag);
        })
        ->with(['user:id,first_name', 'category:id,name', 'medias'])
        ->get()
        ->map(function($post) {
            return [
                'id' => $post->id,
                'author' => $post->user ? trim($post->user->first_name . ' ' . ($post->user->last_name ?? '')) : null,
                'fandom' => $post->fandom ? $post->fandom->name : 'General',
                'date' => $post->created_at,
                'likes' => $post->likes ?? 0,
                'category' => $post->category->name ?? null,
                'title' => $post->title ?? (is_string($post->description) ? (mb_strimwidth($post->description, 0, 50, '...')) : null),
                'content' => $post->content ?? $post->description ?? null,
                'media' => $post->medias->filter(function($media) {
                    return !empty($media->file_path);
                })->map(function($media) {
                    return asset('storage/' . $media->file_path);
                })->values()->toArray(),
            ];
        });
        return response()->json(['success' => true, 'data' => $posts]);
    }

    // f. Get posts by category, subcategory
    public function getPostsByCategorySubSimple(Request $request)
    {
        $query = \App\Models\Post::query();
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->has('subcategory_id')) {
            $query->where('subcategory_id', $request->subcategory_id);
        }
        $posts = $query->with(['user:id,first_name', 'category:id,name', 'medias'])
            ->get()
            ->map(function($post) {
                return [
                    'id' => $post->id,
                    'author' => $post->user ? trim($post->user->first_name . ' ' . ($post->user->last_name ?? '')) : null,
                    'fandom' => $post->fandom ? $post->fandom->name : 'General',
                    'date' => $post->created_at,
                    'likes' => $post->likes ?? 0,
                    'category' => $post->category->name ?? null,
                    'title' => $post->title ?? (is_string($post->description) ? (mb_strimwidth($post->description, 0, 50, '...')) : null),
                    'content' => $post->content ?? $post->description ?? null,
                    'media' => $post->medias->filter(function($media) {
                        return !empty($media->file_path);
                    })->map(function($media) {
                        return asset('storage/' . $media->file_path);
                    })->values()->toArray(),
                ];
            });
        return response()->json(['success' => true, 'data' => $posts]);
    }

    // g. Get posts by category only
    public function getPostsByCategorySimple($category_id)
    {
        $posts = \App\Models\Post::where('category_id', $category_id)
            ->with(['user:id,first_name', 'category:id,name', 'medias'])
            ->get()
            ->map(function($post) {
                return [
                    'id' => $post->id,
                    'author' => $post->user ? trim($post->user->first_name . ' ' . ($post->user->last_name ?? '')) : null,
                    'fandom' => $post->fandom ? $post->fandom->name : 'General',
                    'date' => $post->created_at,
                    'likes' => $post->likes ?? 0,
                    'category' => $post->category->name ?? null,
                    'title' => $post->title ?? (is_string($post->description) ? (mb_strimwidth($post->description, 0, 50, '...')) : null),
                    'content' => $post->content ?? $post->description ?? null,
                    'media' => $post->medias->filter(function($media) {
                        return !empty($media->file_path);
                    })->map(function($media) {
                        return asset('storage/' . $media->file_path);
                    })->values()->toArray(),
                ];
            });
        return response()->json(['success' => true, 'data' => $posts]);
    }

    // h. Get posts by subcategory only
    public function getPostsBySubcategorySimple($subcategory_id)
    {
        $posts = \App\Models\Post::where('subcategory_id', $subcategory_id)
            ->with(['user:id,first_name', 'category:id,name', 'medias'])
            ->get()
            ->map(function($post) {
                return [
                    'id' => $post->id,
                    'author' => $post->user ? trim($post->user->first_name . ' ' . ($post->user->last_name ?? '')) : null,
                    'fandom' => $post->fandom ? $post->fandom->name : 'General',
                    'date' => $post->created_at,
                    'likes' => $post->likes ?? 0,
                    'category' => $post->category->name ?? null,
                    'title' => $post->title ?? (is_string($post->description) ? (mb_strimwidth($post->description, 0, 50, '...')) : null),
                    'content' => $post->content ?? $post->description ?? null,
                    'media' => $post->medias->filter(function($media) {
                        return !empty($media->file_path);
                    })->map(function($media) {
                        return asset('storage/' . $media->file_path);
                    })->values()->toArray(),
                ];
            });
        return response()->json(['success' => true, 'data' => $posts]);
    }

    // === VERSIONS CUSTOM DES POSTS (retournent le nom du fandom ou 'General') ===
    public function getAllPostsCustom()
    {
        $posts = \App\Models\Post::with(['user:id,first_name,last_name', 'category:id,name', 'subcategory:id,name', 'medias', 'fandom:id,name', 'tags'])
            ->get()
            ->map(function($post) {
                return [
                    'id' => $post->id,
                    'author' => $post->user ? trim($post->user->first_name . ' ' . ($post->user->last_name ?? '')) : null,
                    'fandom' => $post->fandom ? $post->fandom->name : 'General',
                    'date' => $post->created_at,
                    'likes' => $post->likes ?? 0,
                    'category' => $post->category->name ?? null,
                    'subcategory' => $post->subcategory->name ?? null,
                    'title' => $post->title ?? (is_string($post->description) ? (mb_strimwidth($post->description, 0, 50, '...')) : null),
                    'content' => $post->content ?? $post->description ?? null,
                    'media' => $post->medias->filter(function($media) {
                        return !empty($media->file_path);
                    })->map(function($media) {
                        return asset('storage/' . $media->file_path);
                    })->values()->toArray(),
                ];
            });
        return response()->json(['success' => true, 'data' => $posts]);
    }

    public function addPost(Request $request)
    {
        $request->validate([
            'user_id' => 'required_without:author_id|integer|exists:users,id',
            'author_id' => 'required_without:user_id|integer|exists:users,id',
            'title' => 'nullable|string',
            'content' => 'nullable|string',
            'category_id' => 'nullable|integer|exists:categories,id',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
            'fandom_id' => 'nullable|integer|exists:fandoms,id',
            'media' => 'nullable|array',
            'media.*' => 'file|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $authorId = $request->input('author_id') ?? $request->input('user_id');

        // Only include columns that exist in DB to avoid QueryException
        $columns = Schema::getColumnListing('posts');
        $data = [];
        if (in_array('user_id', $columns)) $data['user_id'] = $authorId;
        if (in_array('title', $columns) && $request->filled('title')) $data['title'] = $request->title;
        if (in_array('content', $columns) && $request->filled('content')) $data['content'] = $request->content;
        // Map incoming content/title into description when appropriate
        if (in_array('description', $columns)) $data['description'] = $request->content ?? $request->title ?? $request->description ?? null;
        if (in_array('category_id', $columns) && $request->filled('category_id')) $data['category_id'] = $request->category_id;
        if (in_array('subcategory_id', $columns) && $request->filled('subcategory_id')) $data['subcategory_id'] = $request->subcategory_id;
        if (in_array('fandom_id', $columns) && $request->filled('fandom_id')) $data['fandom_id'] = $request->fandom_id;

        $post = \App\Models\Post::create($data);

        // Upload media files if present
        if ($request->hasFile('media')) {
            $files = $request->file('media');
            if (!is_array($files)) $files = [$files];
            foreach ($files as $file) {
                if ($file && $file->isValid()) {
                    $path = $file->store('images', 'public');
                    $post->medias()->create([
                        'file_path' => $path,
                        'media_type' => $file->getClientMimeType(),
                    ]);
                }
            }
        }

        // Tags (store in DB but don't expose tag ids/names in the response)
        if ($request->has('tags') && is_array($request->tags)) {
            foreach ($request->tags as $tagName) {
                $tag = \App\Models\Tag::firstOrCreate(['tag_name' => $tagName]);
                $post->tags()->attach($tag->id);
            }
        }

        $post = \App\Models\Post::with(['user:id,first_name,last_name','category','subcategory','medias','fandom'])->find($post->id);

        $result = [
            'id' => $post->id,
            'author' => $post->user ? trim($post->user->first_name . ' ' . ($post->user->last_name ?? '')) : null,
            'fandom' => $post->fandom ? $post->fandom->name : 'General',
            'category' => in_array('category_id', $columns) ? ($post->category->name ?? null) : null,
            'subcategory' => in_array('subcategory_id', $columns) ? ($post->subcategory->name ?? null) : null,
            'title' => $post->title ?? (is_string($post->description) ? (mb_strimwidth($post->description, 0, 50, '...')) : null),
            'content' => $post->content ?? $post->description ?? null,
            'media' => $post->medias->filter(function($m) { return !empty($m->file_path); })->map(function($m) { return asset('storage/' . $m->file_path); })->values()->toArray(),
            'created_at' => $post->created_at,
        ];

        return response()->json(['success' => true, 'data' => $result], 201);
    }

    public function updatePost(Request $request, $id)
    {
        $post = \App\Models\Post::find($id);
        if (!$post) return response()->json(['success' => false, 'error' => 'Post not found'], 404);

        $columns = Schema::getColumnListing('posts');
        $updatable = array_intersect(['title', 'content', 'category_id', 'subcategory_id', 'fandom_id'], $columns);

        foreach ($updatable as $field) {
            if ($request->has($field)) {
                $post->$field = $request->$field;
            }
        }

        // If title/content don't exist but description exists, update description accordingly
        if (in_array('description', $columns) && ($request->has('content') || $request->has('title'))) {
            $post->description = $request->content ?? $request->title ?? $post->description;
        }

        $post->save();

        if ($request->hasFile('media')) {
            $files = $request->file('media');
            if (!is_array($files)) $files = [$files];
            foreach ($files as $file) {
                if ($file && $file->isValid()) {
                    $path = $file->store('images', 'public');
                    $post->medias()->create([
                        'file_path' => $path,
                        'media_type' => $file->getClientMimeType(),
                    ]);
                }
            }
        }

        if ($request->has('tags') && is_array($request->tags)) {
            $post->tags()->detach();
            foreach ($request->tags as $tagName) {
                $tag = \App\Models\Tag::firstOrCreate(['tag_name' => $tagName]);
                $post->tags()->attach($tag->id);
            }
        }

        $post = \App\Models\Post::with(['user:id,first_name,last_name','category','subcategory','medias','fandom'])->find($post->id);

        $result = [
            'id' => $post->id,
            'author' => $post->user ? trim($post->user->first_name . ' ' . ($post->user->last_name ?? '')) : null,
            'fandom' => $post->fandom ? $post->fandom->name : 'General',
            'category' => in_array('category_id', $columns) ? ($post->category->name ?? null) : null,
            'subcategory' => in_array('subcategory_id', $columns) ? ($post->subcategory->name ?? null) : null,
            'title' => $post->title ?? (is_string($post->description) ? (mb_strimwidth($post->description, 0, 50, '...')) : null),
            'content' => $post->content ?? $post->description ?? null,
            'media' => $post->medias->filter(function($m) { return !empty($m->file_path); })->map(function($m) { return asset('storage/' . $m->file_path); })->values()->toArray(),
            'updated_at' => $post->updated_at,
        ];

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function deletePost($id)
    {
        $post = \App\Models\Post::find($id);
        if (!$post) return response()->json(['success' => false, 'error' => 'Post not found'], 404);
        $post->delete();
        return response()->json(['success' => true, 'message' => 'Post deleted']);
    }

    public function getPostsByTag($tag)
    {
        $posts = \App\Models\Post::whereHas('tags', function($q) use ($tag) {
            $q->where('tag_name', $tag);
        })->with(['user:id,first_name,last_name','category','subcategory','medias','fandom'])
        ->get()
        ->map(function($post) {
            return [
                'id' => $post->id,
                'author' => $post->user ? trim($post->user->first_name . ' ' . ($post->user->last_name ?? '')) : null,
                'fandom' => $post->fandom ? $post->fandom->name : 'General',
                'date' => $post->created_at,
                'likes' => $post->likes ?? 0,
                'category' => $post->category->name ?? null,
                'subcategory' => $post->subcategory->name ?? null,
                'title' => $post->title ?? (is_string($post->description) ? (mb_strimwidth($post->description, 0, 50, '...')) : null),
                'content' => $post->content ?? $post->description ?? null,
                'media' => $post->medias->filter(function($media) {
                    return !empty($media->file_path);
                })->map(function($media) {
                    return asset('storage/' . $media->file_path);
                })->values()->toArray(),
            ];
        });
        return response()->json(['success' => true, 'data' => $posts]);
    }

    public function getPostsByCategory($category_id)
    {
        $posts = \App\Models\Post::where('category_id', $category_id)
            ->with(['user:id,first_name,last_name','category','subcategory','medias','fandom'])
            ->get()
            ->map(function($post) {
                return [
                    'id' => $post->id,
                    'author' => $post->user ? trim($post->user->first_name . ' ' . ($post->user->last_name ?? '')) : null,
                    'fandom' => $post->fandom ? $post->fandom->name : 'General',
                    'date' => $post->created_at,
                    'likes' => $post->likes ?? 0,
                    'category' => $post->category->name ?? null,
                    'subcategory' => $post->subcategory->name ?? null,
                    'title' => $post->title ?? (is_string($post->description) ? (mb_strimwidth($post->description, 0, 50, '...')) : null),
                    'content' => $post->content ?? $post->description ?? null,
                    'media' => $post->medias->filter(function($media) {
                        return !empty($media->file_path);
                    })->map(function($media) {
                        return asset('storage/' . $media->file_path);
                    })->values()->toArray(),
                ];
            });
        return response()->json(['success' => true, 'data' => $posts]);
    }

    public function getPostsBySubcategory($subcategory_id)
    {
        $posts = \App\Models\Post::where('subcategory_id', $subcategory_id)
            ->with(['user:id,first_name,last_name','category','subcategory','medias','fandom'])
            ->get()
            ->map(function($post) {
                return [
                    'id' => $post->id,
                    'author' => $post->user ? trim($post->user->first_name . ' ' . ($post->user->last_name ?? '')) : null,
                    'fandom' => $post->fandom ? $post->fandom->name : 'General',
                    'date' => $post->created_at,
                    'likes' => $post->likes ?? 0,
                    'category' => $post->category->name ?? null,
                    'subcategory' => $post->subcategory->name ?? null,
                    'title' => $post->title ?? (is_string($post->description) ? (mb_strimwidth($post->description, 0, 50, '...')) : null),
                    'content' => $post->content ?? $post->description ?? null,
                    'media' => $post->medias->filter(function($media) {
                        return !empty($media->file_path);
                    })->map(function($media) {
                        return asset('storage/' . $media->file_path);
                    })->values()->toArray(),
                ];
            });
        return response()->json(['success' => true, 'data' => $posts]);
    }

    public function getFandomPosts($fandom_id)
    {
        if ($fandom_id === 'general' || $fandom_id === null || (is_string($fandom_id) && strtolower($fandom_id) === 'null')) {
            $posts = \App\Models\Post::whereNull('fandom_id')
                ->with(['user:id,first_name,last_name','category','subcategory','medias'])
                ->get();
        } else {
            $posts = \App\Models\Post::where('fandom_id', $fandom_id)
                ->with(['user:id,first_name,last_name','category','subcategory','medias','fandom'])
                ->get();
        }

        $result = $posts->map(function($post) {
            return [
                'id' => $post->id,
                'author' => $post->user ? trim($post->user->first_name . ' ' . ($post->user->last_name ?? '')) : null,
                'fandom' => $post->fandom ? $post->fandom->name : 'General',
                'date' => $post->created_at,
                'likes' => $post->likes ?? 0,
                'category' => $post->category->name ?? null,
                'subcategory' => $post->subcategory->name ?? null,
                'title' => $post->title ?? (is_string($post->description) ? (mb_strimwidth($post->description, 0, 50, '...')) : null),
                'content' => $post->content ?? $post->description ?? null,
                'media' => $post->medias->filter(function($media) {
                    return !empty($media->file_path);
                })->map(function($media) {
                    return asset('storage/' . $media->file_path);
                })->values()->toArray(),
            ];
        });

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * Upload image
     * Route: POST /api/upload-image
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
        $path = $file->store('images', 'public');

        return response()->json([
            'success' => true,
            'path' => $path
        ]);
    }

    /**
     * n. Get fandoms (id, name, category, subcategory, description, image, date, moderator, optional num_members, num_posts)
     * Route: GET /api/fandoms
     */
    public function getFandoms()
    {
        $fandoms = \App\Models\Fandom::with(['subcategory.category', 'members.user', 'posts'])
            ->get()
            ->map(function($f) {
                $category = $f->subcategory && $f->subcategory->category ? $f->subcategory->category->name : null;
                $subcategory = $f->subcategory ? $f->subcategory->name : null;

                // Find a moderator or admin if exists
                $modMember = $f->members->first(function($m) {
                    return in_array($m->role, ['moderator', 'admin']);
                });
                $moderator = null;
                if ($modMember) {
                    if ($modMember->user) {
                        $moderator = [
                            'id' => $modMember->user->id,
                            'name' => trim(($modMember->user->first_name ?? '') . ' ' . ($modMember->user->last_name ?? '')),
                        ];
                    } else {
                        $moderator = ['user_id' => $modMember->user_id];
                    }
                }

                // Images - prefer cover_image then logo_image
                $imageUrl = null;
                if (!empty($f->cover_image)) $imageUrl = asset('storage/'.$f->cover_image);
                elseif (!empty($f->logo_image)) $imageUrl = asset('storage/'.$f->logo_image);

                return [
                    'id' => $f->id,
                    'name' => $f->name,
                    'category' => $category,
                    'subcategory' => $subcategory,
                    'description' => $f->description,
                    'image' => $imageUrl,
                    'date' => $f->created_at,
                    'moderator' => $moderator,
                    'num_members' => $f->members ? $f->members->count() : 0,
                    'num_posts' => $f->posts ? $f->posts->count() : 0,
                ];
            });

        return response()->json(['success' => true, 'data' => $fandoms]);
    }

    /**
     * o. Get fandom (by id or name)
     * Route: GET /api/fandoms/{value}
     */
    public function getFandom($value)
    {
        $f = \App\Models\Fandom::with(['subcategory.category', 'members.user', 'posts'])
            ->where('id', $value)
            ->orWhere('name', $value)
            ->first();

        if (!$f) return response()->json(['success' => false, 'error' => 'Fandom not found'], 404);

        $category = $f->subcategory && $f->subcategory->category ? $f->subcategory->category->name : null;
        $subcategory = $f->subcategory ? $f->subcategory->name : null;

        $modMember = $f->members->first(function($m) {
            return in_array($m->role, ['moderator', 'admin']);
        });
        $moderator = null;
        if ($modMember) {
            if ($modMember->user) {
                $moderator = [
                    'id' => $modMember->user->id,
                    'name' => trim(($modMember->user->first_name ?? '') . ' ' . ($modMember->user->last_name ?? '')),
                ];
            } else {
                $moderator = ['user_id' => $modMember->user_id];
            }
        }

        $imageUrl = null;
        if (!empty($f->cover_image)) $imageUrl = asset('storage/'.$f->cover_image);
        elseif (!empty($f->logo_image)) $imageUrl = asset('storage/'.$f->logo_image);

        $result = [
            'id' => $f->id,
            'name' => $f->name,
            'category' => $category,
            'subcategory' => $subcategory,
            'description' => $f->description,
            'image' => $imageUrl,
            'date' => $f->created_at,
            'moderator' => $moderator,
            'num_members' => $f->members ? $f->members->count() : 0,
            'num_posts' => $f->posts ? $f->posts->count() : 0,
        ];

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * p. Add fandom (multipart/form-data, image file, moderator user_id optional)
     * Route: POST /api/fandoms
     */
    public function addFandom(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:fandoms,name',
            'description' => 'nullable|string',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
            'moderator_user_id' => 'nullable|integer|exists:users,id',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'cover' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $coverPath = null;
        $logoPath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $coverPath = $file->store('fandoms/covers', 'public');
        }
        if ($request->hasFile('cover')) {
            $file = $request->file('cover');
            $coverPath = $file->store('fandoms/covers', 'public');
        }
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $logoPath = $file->store('fandoms/logos', 'public');
        }

        $fandom = \App\Models\Fandom::create([
            'name' => $request->name,
            'description' => $request->description ?? null,
            'subcategory_id' => $request->subcategory_id ?? null,
            'cover_image' => $coverPath,
            'logo_image' => $logoPath,
        ]);

        $moderator = null;
        if ($request->moderator_user_id) {
            $member = \App\Models\Member::create([
                'user_id' => $request->moderator_user_id,
                'fandom_id' => $fandom->id,
                'role' => 'moderator',
            ]);
            $moderator = [
                'user_id' => $member->user_id,
            ];
        }

        $category = $fandom->subcategory && $fandom->subcategory->category ? $fandom->subcategory->category->name : null;
        $subcategory = $fandom->subcategory ? $fandom->subcategory->name : null;
        $imageUrl = null;
        if ($fandom->cover_image) $imageUrl = asset('storage/'.$fandom->cover_image);
        elseif ($fandom->logo_image) $imageUrl = asset('storage/'.$fandom->logo_image);

        $result = [
            'id' => $fandom->id,
            'name' => $fandom->name,
            'description' => $fandom->description,
            'category' => $category,
            'subcategory' => $subcategory,
            'image' => $imageUrl,
            'date' => $fandom->created_at,
            'moderator' => $moderator,
        ];

        return response()->json(['success' => true, 'data' => $result], 201);
    }

    /**
     * q. Delete fandom (id)
     * Route: DELETE /api/fandoms/{id}
     */
    public function deleteFandom($id)
    {
        $f = \App\Models\Fandom::find($id);
        if (!$f) return response()->json(['success' => false, 'error' => 'Fandom not found'], 404);
        $f->delete();
        return response()->json(['success' => true, 'message' => 'Fandom deleted']);
    }
}
