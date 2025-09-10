<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;


class FandomController extends Controller
{
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


public function getFandomsByCategory($category_id, Request $request)
    {
        // Vérifier que la catégorie existe
        $category = Category::find($category_id);
        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        $user = Auth::user();
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(50, max(1, (int) $request->get('limit', 10)));

        // Récupérer les subcategories de cette catégorie
        $subcategoryIds = \App\Models\SubCategory::where('category_id', $category_id)->pluck('id');

        // Récupérer les fandoms liés à ces subcategories avec pagination
        $fandomsQuery = \App\Models\Fandom::with('subcategory')
            ->withCount(['posts', 'members'])
            ->whereIn('subcategory_id', $subcategoryIds)
            ->orderBy('members_count', 'desc'); // Trier par popularité

        $fandoms = $fandomsQuery->paginate($limit, ['*'], 'page', $page);

        // Obtenir les rôles de l'utilisateur pour tous les fandoms s'il est authentifié
        $userMemberships = [];
        if ($user) {
            $fandomIds = $fandoms->pluck('id');
            $memberships = \App\Models\Member::where('user_id', $user->id)
                ->whereIn('fandom_id', $fandomIds)
                ->get();
            foreach ($memberships as $membership) {
                $userMemberships[$membership->fandom_id] = $membership->role;
            }
        }

        // Formater les données des fandoms
        $formattedFandoms = $fandoms->getCollection()->map(function ($fandom) use ($userMemberships) {
            $attrs = $fandom->toArray();
            $attrs['posts_count'] = $fandom->posts_count ?? 0;
            $attrs['members_count'] = $fandom->members_count ?? 0;

            // Ajouter les informations de membership
            $attrs['is_member'] = isset($userMemberships[$fandom->id]);
            $attrs['member_role'] = $userMemberships[$fandom->id] ?? null;

            // Simplifier les informations de subcategory
            if (isset($attrs['subcategory']) && is_array($attrs['subcategory'])) {
                $attrs['subcategory'] = [
                    'id' => $attrs['subcategory']['id'],
                    'name' => $attrs['subcategory']['name'],
                ];
            }

            return $attrs;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'image' => $category->image,
                    'description' => $category->description,
                ],
                'fandoms' => $formattedFandoms,
                'pagination' => [
                    'current_page' => $fandoms->currentPage(),
                    'last_page' => $fandoms->lastPage(),
                    'per_page' => $fandoms->perPage(),
                    'total' => $fandoms->total(),
                    'has_more' => $fandoms->hasMorePages(),
                ]
            ]
        ]);
    }

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

}
