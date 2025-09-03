<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'profile_image',
        'background_image',
        'date_naissance',
        'bio',
        'gender',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relation avec les posts créés par l'utilisateur
    public function posts()
    {
        return $this->hasMany(\App\Models\Post::class, 'user_id');
    }

    // Retourne les noms des rôles de l'utilisateur (Spatie)
    public function getRoleNames()
    {
        return $this->roles()->pluck('name');
    }

    // Retourne les permissions de l'utilisateur (Spatie)
    public function getPermissionNames()
    {
        return $this->getAllPermissions()->pluck('name');
    }

    // Relation avec Orders
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function comments()
    {
        return $this->hasMany(\App\Models\Comment::class);
    }

    // Relation avec les posts sauvegardés
    public function savedPosts()
    {
        return $this->belongsToMany(Post::class, 'saved_posts')->withTimestamps();
    }

    // Relation avec les favoris
    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    // Posts favoris de l'utilisateur
    public function favoritePosts()
    {
        return $this->morphedByMany(Post::class, 'favoriteable', 'favorites');
    }

    // Produits favoris de l'utilisateur
    public function favoriteProducts()
    {
        return $this->morphedByMany(Product::class, 'favoriteable', 'favorites');
    }

    // Relation avec les ratings donnés par l'utilisateur
    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    // Posts notés par l'utilisateur
    public function ratedPosts()
    {
        return $this->morphedByMany(Post::class, 'rateable', 'ratings');
    }

    // Produits notés par l'utilisateur
    public function ratedProducts()
    {
        return $this->morphedByMany(Product::class, 'rateable', 'ratings');
    }

    // Relations de follow
    // Utilisateurs que cet utilisateur suit
    public function following()
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')
            ->withTimestamps();
    }

    // Utilisateurs qui suivent cet utilisateur
    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')
            ->withTimestamps();
    }

    // Vérifier si cet utilisateur suit un autre utilisateur
    public function isFollowing($userId)
    {
        return $this->following()->where('following_id', $userId)->exists();
    }

    // Vérifier si cet utilisateur est suivi par un autre utilisateur
    public function isFollowedBy($userId)
    {
        return $this->followers()->where('follower_id', $userId)->exists();
    }

    // Compter le nombre de personnes que cet utilisateur suit
    public function followingCount()
    {
        return $this->following()->count();
    }

    // Compter le nombre de followers de cet utilisateur
    public function followersCount()
    {
        return $this->followers()->count();
    }

    public function preferredCategories()
    {
        return $this->hasMany(UserPreferredCategory::class);
    }

    public function members()
    {
        return $this->hasMany(\App\Models\Member::class);
    }

    // Relation directe avec les saved_posts
    public function savedPostsRelation()
    {
        return $this->hasMany(SavedPost::class);
    }

    // Sauvegarder un post
    public function savePost($postId)
    {
        return $this->savedPosts()->attach($postId);
    }

    // Retirer un post des sauvegardés
    public function unsavePost($postId)
    {
        return $this->savedPosts()->detach($postId);
    }

    // Vérifier si un post est sauvegardé
    public function hasSavedPost($postId)
    {
        return $this->savedPosts()->where('post_id', $postId)->exists();
    }

    // Basculer l'état de sauvegarde d'un post
    public function toggleSavePost($postId)
    {
        return $this->savedPosts()->toggle($postId);
    }
}
