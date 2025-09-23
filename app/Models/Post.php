<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Post extends Model
{
    use HasFactory;
    protected $fillable = [

        'user_id',
        'feedback',
        'schedule_at',
        'description',
        'content_status',
        'category_id',
        'subcategory_id',
        'fandom_id',
        'media',
    ];

    protected $casts = [
        'media' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relation polymorphe vers medias (si tu veux gérer ça)
    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }
    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
    public function subcategory()
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    // Relation avec les favoris
    public function favorites()
    {
        return $this->morphMany(Favorite::class, 'favoriteable');
    }
    public function comments()
    {
        return $this->hasMany(\App\Models\Comment::class);
    }

    // Utilisateurs qui ont mis ce post en favori
    public function favoritedBy()
    {
        return $this->morphToMany(User::class, 'favoriteable', 'favorites');
    }

    // Relation avec les ratings du post
    public function ratings()
    {
        return $this->morphMany(Rating::class, 'rateable');
    }

    // Utilisateurs qui ont noté ce post
    public function ratedBy()
    {
        return $this->morphToMany(User::class, 'rateable', 'ratings');
    }

    // Calculer la note moyenne du post
    public function averageRating()
    {
        return $this->ratings()->avg('evaluation');
    }

    // Compter le nombre total de ratings
    public function ratingsCount()
    {
        return $this->ratings()->count();
    }

    public function medias()
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    /**
     * Relation vers le fandom (optionnelle)
     */
    public function fandom()
    {
        return $this->belongsTo(Fandom::class, 'fandom_id');
    }

    // Relation avec les utilisateurs qui ont sauvegardé ce post
    public function savedByUsers()
    {
        return $this->belongsToMany(User::class, 'saved_posts')->withTimestamps();
    }

    // Relation directe avec les saved_posts
    public function savedPosts()
    {
        return $this->hasMany(SavedPost::class);
    }

    // Vérifier si un post est sauvegardé par un utilisateur spécifique
    public function isSavedBy($userId)
    {
        return $this->savedByUsers()->where('user_id', $userId)->exists();
    }

    // Compter le nombre de fois que ce post a été sauvegardé
    public function savesCount()
    {
        return $this->savedByUsers()->count();
    }
}


