<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;
    protected $fillable = ['name'];

    // Une catégorie a plusieurs sous-catégories
    public function subcategories()
    {
        return $this->hasMany(Subcategory::class);
    }
    
    public function userPreferredCategories()
    {
        return $this->hasMany(UserPreferredCategory::class);
    }
}
