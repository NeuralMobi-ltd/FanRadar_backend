<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Rating extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'rateable_id',
        'rateable_type',
        'evaluation',
        'commentaire',
    ];

    protected $casts = [
        'evaluation' => 'integer',
    ];

    /**
     * Relation polymorphe vers les éléments qui peuvent être notés
     * (Post, Product, etc.)
     */
    public function rateable()
    {
        return $this->morphTo();
    }

    /**
     * Relation vers l'utilisateur qui a donné la note
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope pour filtrer par note
     */
    public function scopeByRating($query, $rating)
    {
        return $query->where('evaluation', $rating);
    }

    /**
     * Scope pour obtenir les meilleures notes (4-5 étoiles)
     */
    public function scopeHighRated($query)
    {
        return $query->where('evaluation', '>=', 4);
    }

    /**
     * Scope pour obtenir les notes moyennes (2-3 étoiles)
     */
    public function scopeMediumRated($query)
    {
        return $query->whereBetween('evaluation', [2, 3]);
    }

    /**
     * Scope pour obtenir les mauvaises notes (0-1 étoile)
     */
    public function scopeLowRated($query)
    {
        return $query->where('evaluation', '<=', 1);
    }
}
