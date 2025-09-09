<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OTPMail;
use App\Models\User;
use App\Models\Post;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Models\Fandom;
use App\Models\Member;
use App\Models\Comment;

class PersonnaliseController extends Controller
{
    // --------------------
    // AUTH
    // --------------------
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Email et mot de passe requis', 'details' => $validator->errors()]], 422);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Email ou mot de passe invalide']], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $roleNames = method_exists($user, 'getRoleNames') ? $user->getRoleNames() : collect();
        $permissionNames = method_exists($user, 'getPermissionNames') ? $user->getPermissionNames() : collect();

        if ($roleNames->isEmpty() && method_exists($user, 'assignRole')) {
            $user->assignRole('user');
            $roleNames = $user->getRoleNames();
        }

        $preferredCategories = method_exists($user, 'preferredCategories') ? $user->preferredCategories()->pluck('category_id')->toArray() : [];

        $followersCount = method_exists($user, 'followers') ? $user->followers()->count() : 0;
        $followingCount = method_exists($user, 'following') ? $user->following()->count() : 0;
        $postsCount = method_exists($user, 'posts') ? $user->posts()->count() : Post::where('user_id', $user->id)->count();

        return response()->json(['message' => 'Connexion réussie.', 'user' => [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'profile_image' => $this->makeImageUrl($user->profile_image),
            'background_image' => $this->makeImageUrl($user->background_image),
            'date_naissance' => $user->date_naissance,
            'gender' => $user->gender,
            'preferred_categories' => $preferredCategories,
            'role' => $roleNames->first() ?? null,
            'permissions' => $permissionNames->toArray(),
            'stats' => ['followers' => $followersCount, 'following' => $followingCount, 'posts' => $postsCount]
        ], 'token' => $token], 200);
    }

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
            'profile_image' => null,
            'background_image' => null,
            'date_naissance' => $request->date_naissance,
            'gender' => $request->gender,
            'bio' => $request->bio ?? null,
            'otp' => null,
            'otp_created_at' => null,
        ]);

        if (method_exists($user, 'assignRole')) {
            $user->assignRole('user');
        }

        $preferredCategories = [];
        if ($request->has('preferred_categories') && method_exists($user, 'preferredCategories')) {
            foreach ($request->preferred_categories as $catId) {
                $user->preferredCategories()->create(['category_id' => $catId]);
            }
            $preferredCategories = $user->preferredCategories()->pluck('category_id')->toArray();
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $roleNames = method_exists($user, 'getRoleNames') ? $user->getRoleNames() : collect();
        $permissionNames = method_exists($user, 'getPermissionNames') ? $user->getPermissionNames() : collect();

        return response()->json(['message' => 'Inscription réussie.', 'user' => [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'profile_image' => $this->makeImageUrl($user->profile_image),
            'background_image' => $this->makeImageUrl($user->background_image),
            'date_naissance' => $user->date_naissance,
            'gender' => $user->gender,
            'preferred_categories' => $preferredCategories,
            'role' => $roleNames->first() ?? null,
            'permissions' => $permissionNames->toArray(),
            'stats' => ['followers' => 0, 'following' => 0, 'posts' => 0]
        ], 'token' => $token], 201);
    }

    public function forgetPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();
        if (!$user) return response()->json(['error' => 'Email not found'], 404);

        $otp = rand(100000, 999999);
        $user->update(['otp' => $otp, 'otp_created_at' => now()]);
        Mail::to($user->email)->send(new OTPMail($otp));

        return response()->json(['message' => 'OTP sent to your email'], 200);
    }

    public function verifyOTP(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email', 'otp' => 'required|numeric']);

        $user = User::where('email', $request->email)->where('otp', $request->otp)->first();
        if (!$user) return response()->json(['error' => 'OTP invalide'], 400);

        if ($user->otp_created_at && Carbon::now()->diffInMinutes($user->otp_created_at) > 10) {
            return response()->json(['error' => 'OTP expiré'], 400);
        }

        return response()->json(['message' => 'OTP validé'], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate(['email' => 'required|email', 'otp' => 'required|numeric', 'password' => 'required|string|min:6|confirmed']);

        $user = User::where('email', $request->email)->first();
        if (!$user) return response()->json(['error' => 'Email invalide'], 404);
        if ($user->otp != $request->otp) return response()->json(['error' => 'OTP invalide'], 404);

        if ($user->otp_created_at && Carbon::now()->diffInMinutes($user->otp_created_at) > 10) {
            return response()->json(['error' => 'OTP expiré'], 400);
        }

        $user->update(['password' => bcrypt($request->password), 'otp' => null, 'otp_created_at' => null]);

        return response()->json(['message' => 'Mot de passe réinitialisé avec succès'], 200);
    }

    // --------------------
    // USER PROFILE
    // --------------------
    public function getUserProfile()
    {
        $user = Auth::user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);

        $preferredCategories = method_exists($user, 'preferredCategories') ? $user->preferredCategories()->pluck('category_id')->toArray() : [];
        $followersCount = method_exists($user, 'followers') ? $user->followers()->count() : 0;
        $followingCount = method_exists($user, 'following') ? $user->following()->count() : 0;
        $postsCount = method_exists($user, 'posts') ? $user->posts()->count() : Post::where('user_id', $user->id)->count();

        $roleNames = method_exists($user, 'getRoleNames') ? $user->getRoleNames() : collect();
        $permissionNames = method_exists($user, 'getPermissionNames') ? $user->getPermissionNames() : collect();

        return response()->json(['success' => true, 'user' => [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'bio' => $user->bio,
            'profile_image' => $this->makeImageUrl($user->profile_image),
            'background_image' => $this->makeImageUrl($user->background_image),
            'date_naissance' => $user->date_naissance,
            'gender' => $user->gender,
            'preferred_categories' => $preferredCategories,
            'role' => $roleNames->first() ?? null,
            'permissions' => $permissionNames->toArray(),
            'stats' => ['followers' => $followersCount, 'following' => $followingCount, 'posts' => $postsCount]
        ]]);
    }

    public function updateUserProfile(Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);

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

        if ($validator->fails()) return response()->json(['success' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Données invalides', 'details' => $validator->errors()]], 422);

        $updateData = [];
        if ($request->has('first_name')) $updateData['first_name'] = $request->first_name;
        if ($request->has('last_name')) $updateData['last_name'] = $request->last_name;
        if ($request->has('date_naissance')) $updateData['date_naissance'] = $request->date_naissance;
        if ($request->has('gender')) $updateData['gender'] = $request->gender;
        if ($request->has('bio')) $updateData['bio'] = $request->bio;

        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            if ($file->isValid()) {
                $path = $file->store('profile', 'public');
                $updateData['profile_image'] = 'storage/' . $path;
            }
        }

        if ($request->hasFile('background_image')) {
            $file = $request->file('background_image');
            if ($file->isValid()) {
                $path = $file->store('backgroundprofile', 'public');
                $updateData['background_image'] = 'storage/' . $path;
            }
        }

        $user->update($updateData);

        if ($request->has('preferred_categories') && method_exists($user, 'preferredCategories')) {
            $user->preferredCategories()->delete();
            foreach ($request->preferred_categories as $catId) {
                $user->preferredCategories()->create(['category_id' => $catId]);
            }
        }

        return $this->getUserProfile();
    }

    // --------------------
    // POSTS: create/update/delete/list
    // --------------------
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
                $imageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                $videoExtensions = ['mp4', 'mov'];

                if (in_array($extension, $imageExtensions)) { $folder = 'posts/images'; $mediaType = 'image'; }
                elseif (in_array($extension, $videoExtensions)) { $folder = 'posts/videos'; $mediaType = 'video'; }
                else { continue; }

                $path = $file->store($folder, 'public');
                if (Storage::disk('public')->exists($path)) {
                    $post->medias()->create(['file_path' => $path, 'media_type' => $mediaType]);
                }
            }
        }

        $post->load('tags', 'medias');

        return response()->json(['message' => 'Post créé avec succès.', 'post' => [
            'id' => $post->id,
            'body' => $post->body ?? '',
            'description' => $post->description ?? '',
            'subcategory_id' => $post->subcategory_id ?? null,
            'media' => $post->medias ? $post->medias->map(function($m){ return $this->makeImageUrl($m->file_path); })->toArray() : [],
            'tags' => $post->tags ? $post->tags->pluck('tag_name')->toArray() : [],
            'content_status' => $post->content_status,
            'schedule_at' => $post->schedule_at,
            'createdAt' => $post->created_at ? $post->created_at->toISOString() : null
        ]], 201);
    }

    public function updatePost($postId, Request $request)
    {
        $post = Post::find($postId);
        if (!$post) return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Post non trouvé']], 404);

        $validator = Validator::make($request->all(), [
            'description' => 'nullable|string',
            'body' => 'nullable|string',
            'content_status' => 'sometimes|in:draft,published,archived',
            'schedule_at' => 'nullable|date',
            'medias' => 'nullable|array',
            'medias.*' => 'file|mimes:jpg,jpeg,png,mp4,mov|max:20480',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:255',
        ]);

        if ($validator->fails()) return response()->json(['success' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Données invalides', 'details' => $validator->errors()]], 422);

        $updateData = [];
        if ($request->has('body')) $updateData['body'] = $request->body;
        if ($request->has('description')) $updateData['description'] = $request->description;
        if ($request->has('content_status')) $updateData['content_status'] = $request->content_status;
        if ($request->has('schedule_at')) $updateData['schedule_at'] = $request->schedule_at;

        $post->update($updateData);

        $mediaFiles = $request->file('medias');
        if (is_iterable($mediaFiles)) {
            foreach ($mediaFiles as $file) {
                $extension = strtolower($file->getClientOriginalExtension());
                $imageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                $videoExtensions = ['mp4', 'mov'];
                if (in_array($extension, $imageExtensions)) { $folder = 'posts/images'; $mediaType = 'image'; }
                elseif (in_array($extension, $videoExtensions)) { $folder = 'posts/videos'; $mediaType = 'video'; }
                else { continue; }
                $path = $file->store($folder, 'public');
                if (Storage::disk('public')->exists($path)) {
                    $post->medias()->create(['file_path' => $path, 'media_type' => $mediaType]);
                }
            }
        }

        $post->load('medias', 'tags', 'user');

        return response()->json(['message' => 'Post mis à jour avec succès.', 'post' => [
            'id' => $post->id,
            'body' => $post->body ?? '',
            'description' => $post->description ?? '',
            'media' => $post->medias ? $post->medias->map(function($m){ return $this->makeImageUrl($m->file_path); })->toArray() : [],
            'tags' => $post->tags ? $post->tags->pluck('tag_name')->toArray() : [],
            'content_status' => $post->content_status,
            'schedule_at' => $post->schedule_at,
            'createdAt' => $post->created_at ? $post->created_at->toISOString() : null
        ]], 200);
    }

    public function deletePost($postId, Request $request)
    {
        $post = Post::with('medias')->find($postId);
        if (!$post) return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Post non trouvé']], 404);

        foreach ($post->medias as $media) {
            if (isset($media->file_path) && Storage::disk('public')->exists($media->file_path)) {
                Storage::disk('public')->delete($media->file_path);
            }
            $media->delete();
        }

        if (method_exists($post, 'tags')) $post->tags()->detach();
        $post->delete();

        return response()->json(['success' => true, 'message' => 'Post supprimé avec succès.'], 200);
    }

    public function getUserPosts($userId, Request $request)
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);

        $posts = Post::where('user_id', $userId)->with(['medias', 'tags'])->latest()->paginate($limit, ['*'], 'page', $page);

        $formattedPosts = collect($posts->items())->map(function ($post) {
            return [
                'id' => $post->id,
                'description' => $post->description ?? '',
                'content_status' => $post->content_status,
                'schedule_at' => $post->schedule_at,
                'category_id' => $post->category_id ?? null,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
                'media' => method_exists($post, 'medias') ? $post->medias->map(function($m){ return $this->makeImageUrl($m->file_path); })->toArray() : [],
                'tags' => method_exists($post, 'tags') ? $post->tags->pluck('tag_name')->toArray() : [],
            ];
        });

        return response()->json(['success' => true, 'data' => ['posts' => $formattedPosts, 'pagination' => ['page' => $posts->currentPage(), 'limit' => $posts->perPage(), 'total' => $posts->total(), 'pages' => $posts->lastPage()]]]);
    }

    // --------------------
    // COMMENTS
    // --------------------
    public function addCommentToPost($postId, Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);

        $request->validate(['content' => 'required|string|max:2000']);
        $post = Post::find($postId);
        if (!$post) return response()->json(['success' => false, 'message' => 'Post not found'], 404);

        $comment = $post->comments()->create(['user_id' => $user->id, 'post_id' => $post->id, 'content' => $request->content]);

        return response()->json(['success' => true, 'message' => 'Commentaire ajouté avec succès.', 'comment' => [
            'id' => $comment->id,
            'content' => $comment->content ?? '',
            'user' => ['id' => $user->id, 'first_name' => $user->first_name, 'last_name' => $user->last_name],
            'created_at' => $comment->created_at ? $comment->created_at->toISOString() : null
        ]], 201);
    }

    public function getPostComments($postId, Request $request)
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 20)));

        $post = Post::find($postId);
        if (!$post) return response()->json(['success' => false, 'message' => 'Post not found'], 404);

        $comments = Comment::where('post_id', $postId)->with(['user:id,first_name,last_name,profile_image,bio'])->orderBy('created_at', 'desc')->paginate($limit, ['*'], 'page', $page);

        $formatted = collect($comments->items())->map(function($comment){
            $user = $comment->user;
            return [
                'id' => $comment->id,
                'content' => $comment->content ?? '',
                'created_at' => $comment->created_at ? $comment->created_at->toISOString() : null,
                'updated_at' => $comment->updated_at ? $comment->updated_at->toISOString() : null,
                'user' => $user ? ['id' => $user->id, 'first_name' => $user->first_name, 'last_name' => $user->last_name, 'profile_image' => $this->makeImageUrl($user->profile_image), 'bio' => $user->bio] : null
            };
        });

        return response()->json(['success' => true, 'data' => ['post_id' => $postId, 'comments' => $formatted, 'comments_count' => $comments->total(), 'pagination' => ['current_page' => $comments->currentPage(), 'total_pages' => $comments->lastPage(), 'per_page' => $comments->perPage(), 'has_more' => $comments->hasMorePages()]]]);
    }

    // --------------------
    // FANDOMS / CATEGORIES
    // --------------------
    public function getFandoms(Request $request)
    {
        $fandoms = Fandom::with(['members', 'subcategory.category'])->orderBy('created_at', 'desc')->get();
        $items = $fandoms->map(function($fandom){
            $moderatorMember = $fandom->members->firstWhere('role', 'moderator') ?? $fandom->members->firstWhere('role', 'admin') ?? $fandom->members->first();
            $moderator = $moderatorMember ? ['user_id' => $moderatorMember->user_id ?? null, 'role' => $moderatorMember->role ?? null] : null;
            return [
                'id' => $fandom->id,
                'name' => $fandom->name,
                'category' => $fandom->subcategory && $fandom->subcategory->category ? $fandom->subcategory->category->name : null,
                'subcategory' => $fandom->subcategory ? $fandom->subcategory->name : null,
                'description' => $fandom->description ?? null,
                'image' => $this->makeImageUrl($fandom->cover_image ?? $fandom->logo_image ?? null),
                'date' => $fandom->created_at ? $fandom->created_at->toISOString() : null,
                'moderator' => $moderator
            ];
        })->toArray();

        return response()->json(['success' => true, 'data' => ['fandoms' => $items]]);
    }

    public function getFandomsByCategory($category_id, Request $request)
    {
        $category = Category::find($category_id);
        if (!$category) return response()->json(['success' => false, 'message' => 'Category not found'], 404);

        $page = max(1, (int) $request->get('page', 1));
        $limit = min(50, max(1, (int) $request->get('limit', 10)));

        $subcategoryIds = \App\Models\SubCategory::where('category_id', $category_id)->pluck('id');
        $fandoms = \App\Models\Fandom::with('subcategory')->withCount(['posts', 'members'])->whereIn('subcategory_id', $subcategoryIds)->orderBy('members_count', 'desc')->paginate($limit, ['*'], 'page', $page);

        $user = Auth::user();
        $userMemberships = [];
        if ($user) {
            $fandomIds = $fandoms->pluck('id')->toArray();
            $memberships = Member::where('user_id', $user->id)->whereIn('fandom_id', $fandomIds)->get();
            foreach ($memberships as $m) $userMemberships[$m->fandom_id] = $m->role;
        }

        $formattedFandoms = $fandoms->getCollection()->map(function($fandom) use ($userMemberships){
            $attrs = $fandom->toArray();
            $attrs['posts_count'] = $fandom->posts_count ?? 0;
            $attrs['members_count'] = $fandom->members_count ?? 0;
            $attrs['is_member'] = isset($userMemberships[$fandom->id]);
            $attrs['member_role'] = $userMemberships[$fandom->id] ?? null;
            if (isset($attrs['subcategory']) && is_array($attrs['subcategory'])) {
                $attrs['subcategory'] = ['id' => $attrs['subcategory']['id'] ?? null, 'name' => $attrs['subcategory']['name'] ?? null];
            }
            return $attrs;
        });

        return response()->json(['success' => true, 'data' => ['category' => ['id' => $category->id, 'name' => $category->name, 'description' => $category->description], 'fandoms' => $formattedFandoms, 'pagination' => ['current_page' => $fandoms->currentPage(), 'last_page' => $fandoms->lastPage(), 'per_page' => $fandoms->perPage(), 'total' => $fandoms->total(), 'has_more' => $fandoms->hasMorePages()]]]);
    }

    public function deleteFandom($id)
    {
        $f = Fandom::find($id);
        if (!$f) return response()->json(['success' => false, 'error' => 'Fandom not found'], 404);
        $f->delete();
        return response()->json(['success' => true, 'message' => 'Fandom deleted']);
    }

    // --------------------
    // STORE / PRODUCTS
    // --------------------
    public function getStoreProducts(Request $request)
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 20)));

        $products = Product::with(['medias', 'tags', 'subcategory'])->orderBy('created_at', 'desc')->paginate($limit, ['*'], 'page', $page);

        $formatted = $products->getCollection()->map(function($product){
            return [
                'id' => $product->id,
                'product_name' => $product->product_name ?? $product->name ?? '',
                'description' => $product->description ?? '',
                'price' => (float) ($product->price ?? 0),
                'image' => $product->medias && $product->medias->first() ? $this->makeImageUrl($product->medias->first()->file_path) : null,
            ];
        });

        return response()->json(['success' => true, 'data' => ['products' => $formatted, 'pagination' => ['current_page' => $products->currentPage(), 'last_page' => $products->lastPage(), 'per_page' => $products->perPage(), 'total' => $products->total()]]]);
    }

    public function getDragProducts(Request $request)
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(50, max(1, (int) $request->get('limit', 10)));
        $status = $request->get('status');
        $now = now();

        $query = Product::with(['medias', 'tags', 'subcategory', 'ratings'])->whereNotNull('sale_start_date')->whereNotNull('sale_end_date')->where('content_status', 'published');

        if ($status === 'upcoming') $query->where('sale_start_date', '>', $now);
        elseif ($status === 'active') $query->where('sale_start_date', '<=', $now)->where('sale_end_date', '>=', $now);
        elseif ($status === 'expired') $query->where('sale_end_date', '<', $now);

        $query->orderBy('sale_end_date', 'asc');
        $products = $query->paginate($limit, ['*'], 'page', $page);

        $formatted = $products->getCollection()->map(function($product) use ($now){
            $status = 'expired'; $timeRemaining = null; $daysUntilStart = null;
            if ($product->sale_start_date > $now) { $status = 'upcoming'; $daysUntilStart = $now->diffInDays($product->sale_start_date); }
            elseif ($product->sale_start_date <= $now && $product->sale_end_date >= $now) { $status = 'active'; $timeRemaining = $now->diffInDays($product->sale_end_date); }
            $averageRating = $product->ratings->count() > 0 ? round($product->ratings->avg('evaluation'), 1) : 0;
            return [
                'id' => $product->id,
                'product_name' => $product->product_name ?? $product->name ?? '',
                'description' => $product->description ?? '',
                'price' => (float) ($product->price ?? 0),
                'status' => $status,
                'media' => $product->medias ? $product->medias->map(function($m){ return $this->makeImageUrl($m->file_path); })->toArray() : [],
                'average_rating' => $averageRating,
            ];
        });

        return response()->json(['success' => true, 'data' => ['products' => $formatted, 'pagination' => ['current_page' => $products->currentPage(), 'last_page' => $products->lastPage(), 'per_page' => $products->perPage(), 'total' => $products->total()]]]);
    }

    // --------------------
    // FAVORITES / SAVES
    // --------------------
    public function savePost(Request $request)
    {
        $request->validate(['post_id' => 'required|exists:posts,id']);
        $user = $request->user();
        $postId = $request->post_id;
        $post = Post::find($postId);
        if (!$post) return response()->json(['success' => false, 'message' => 'Post not found'], 404);
        if ($user->savedPosts()->where('post_id', $postId)->exists()) return response()->json(['success' => false, 'message' => 'Post is already saved'], 409);
        $user->savedPosts()->attach($postId);
        return response()->json(['success' => true, 'message' => 'Post saved successfully']);
    }

    public function unsavePost(Request $request)
    {
        $request->validate(['post_id' => 'required|exists:posts,id']);
        $user = $request->user();
        $postId = $request->post_id;
        if (!$user->savedPosts()->where('post_id', $postId)->exists()) return response()->json(['success' => false, 'message' => 'Post is not saved'], 404);
        $user->savedPosts()->detach($postId);
        return response()->json(['success' => true, 'message' => 'Post unsaved successfully']);
    }

    public function getFavoritePosts(Request $request)
    {
        $user = Auth::user(); if (!$user) return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        $page = max(1, (int) $request->get('page', 1)); $limit = min(100, max(1, (int) $request->get('limit', 10)));
        $favorites = Favorite::where('user_id', $user->id)->where('favoriteable_type', 'App\\Models\\Post')->with(['favoriteable.user', 'favoriteable.medias', 'favoriteable.tags'])->orderBy('created_at', 'desc')->paginate($limit, ['*'], 'page', $page);
        $formatted = collect($favorites->items())->map(function($favorite){ $post = $favorite->favoriteable; if (!$post) return null; return ['id' => $post->id, 'description' => $post->description ?? '', 'content' => $post->content ?? $post->body ?? '', 'media' => $post->medias ? $post->medias->map(function($m){ return $this->makeImageUrl($m->file_path); })->toArray() : [], 'user' => $post->user ? ['first_name' => $post->user->first_name, 'last_name' => $post->user->last_name, 'profile_image' => $this->makeImageUrl($post->user->profile_image)] : null]; })->filter();
        return response()->json(['success' => true, 'data' => ['posts' => $formatted->values(), 'pagination' => ['current_page' => $favorites->currentPage(), 'total_pages' => $favorites->lastPage(), 'total_items' => $favorites->total(), 'per_page' => $favorites->perPage()]]]);
    }

    public function getFavoriteProducts(Request $request)
    {
        $user = Auth::user(); if (!$user) return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        $page = max(1, (int) $request->get('page', 1)); $limit = min(100, max(1, (int) $request->get('limit', 10)));
        $favorites = Favorite::where('user_id', $user->id)->where('favoriteable_type', 'App\\Models\\Product')->with(['favoriteable'])->orderBy('created_at', 'desc')->paginate($limit, ['*'], 'page', $page);
        $formatted = collect($favorites->items())->map(function($favorite){ $product = $favorite->favoriteable; if (!$product) return null; return ['id' => $product->id, 'name' => $product->name ?? $product->product_name ?? '', 'description' => $product->description ?? '', 'price' => $product->price ?? 0, 'image' => $product->image ?? null]; })->filter();
        return response()->json(['success' => true, 'data' => ['products' => $formatted->values(), 'pagination' => ['current_page' => $favorites->currentPage(), 'total_pages' => $favorites->lastPage(), 'total_items' => $favorites->total(), 'per_page' => $favorites->perPage()]]]);
    }

    // --------------------
    // UTIL
    // --------------------
    protected function makeImageUrl($path)
    {
        if (empty($path)) return null;
        if (preg_match('/^https?:\\/\\//i', $path)) return $path;
        return url($path);
    }
}
