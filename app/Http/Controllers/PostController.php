<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        $post->load('user', 'medias');
        return response()->json($post);
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

}
