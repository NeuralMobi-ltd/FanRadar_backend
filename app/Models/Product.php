<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_name',
        'description',
        'price',
        'stock',
        'promotion',
        'sale_start_date',
        'sale_end_date',
        'user_id',
        'subcategory_id',
        'type',
        'revenue',
        'content_status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_start_date' => 'date',
        'sale_end_date' => 'date',
    ];

    // Relation avec OrderProduct
    public function orderProducts()
    {
        return $this->hasMany(OrderProduct::class);
    }

    // Relation avec Orders via OrderProduct
    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_products')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }

    public function medias()
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

// Relation avec les favoris
public function favorites()
{
    return $this->morphMany(Favorite::class, 'favoriteable');
}

// Utilisateurs qui ont mis ce produit en favori
public function favoritedBy()
{
    return $this->morphToMany(User::class, 'favoriteable', 'favorites');
}

// Relation avec les ratings du produit
public function ratings()
{
    return $this->morphMany(Rating::class, 'rateable');
}

// Utilisateurs qui ont noté ce produit
public function ratedBy()
{
    return $this->morphToMany(User::class, 'rateable', 'ratings');
}

// Calculer la note moyenne du produit
public function averageRating()
{
    return $this->ratings()->avg('evaluation');
}

// Compter le nombre total de ratings
public function ratingsCount()
{
    return $this->ratings()->count();
}

}
