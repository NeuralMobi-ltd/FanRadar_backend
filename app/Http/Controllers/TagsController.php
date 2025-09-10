<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TagsController extends Controller
{
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

}
