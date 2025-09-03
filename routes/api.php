<?php

use App\Http\Controllers\Api\AuthentificationController;
use App\Http\Controllers\Api\PersonnaliseController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderProductController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SubcategoryController;
use App\Http\Controllers\TagController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


// !!!All this routes starts with api/

// Routes protégées par l'authentification
Route::middleware('auth:sanctum')->group(function () {
   Route::post('/logout', [AuthentificationController::class, 'logout']);
   Route::post('/logoutAllDevices', [AuthentificationController::class, 'logoutfromAllDevices']);//all auth token will be deleted
});


// Routes publiques
Route::post('/login', [AuthentificationController::class, 'login']);
Route::post('/register', [AuthentificationController::class, 'register']);

// ========== ROUTES PUBLIQUES POUR E-COMMERCE ==========

// � PRODUITS - Routes publiques
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::post('/products', [ProductController::class, 'store']);
Route::put('/products/{product}', [ProductController::class, 'update']);
Route::delete('/products/{product}', [ProductController::class, 'destroy']);

// � Post - Routes publiques
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{post}', [PostController::class, 'show']);
Route::post('/posts', [PostController::class, 'store']);
Route::put('/posts/{post}', [PostController::class, 'update']);
Route::delete('/posts/{post}', [PostController::class, 'destroy']);

// 🛒 COMMANDES - Routes publiques (pour test uniquement)
Route::get('/orders', [OrderController::class, 'index']);
Route::get('/orders/{order}', [OrderController::class, 'show']);
Route::post('/orders', [OrderController::class, 'store']);
Route::delete('/orders/{order}', [OrderController::class, 'destroy']);
Route::put('/orders/{order}', [OrderController::class, 'update']);


Route::post('/tags/attach', [TagController::class, 'attachTag']);// donner et cree un tage pour un post ou product
Route::delete('/tags/detach', [TagController::class, 'detachTag']);

// cette Partie de categories et commun entre yassin et oucharou
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);
Route::post('/categories', [CategoryController::class, 'store']);
Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
Route::put('/categories/{category}', [CategoryController::class, 'update']);

Route::get('/subcategories', [SubcategoryController::class, 'index']);
Route::get('/subcategories/{subcategory}', [SubcategoryController::class, 'show']);
Route::post('/subcategories', [SubcategoryController::class, 'store']);
Route::delete('/subcategories/{subcategory}', [SubcategoryController::class, 'destroy']);
Route::put('/subcategories/{subcategory}', [SubcategoryController::class, 'update']);

Route::post('/favorites', [\App\Http\Controllers\FavoriteController::class, 'addToFavorites']);
Route::delete('/favorites', [\App\Http\Controllers\FavoriteController::class, 'removeFromFavorites']);
Route::get('/favorites/check', [\App\Http\Controllers\FavoriteController::class, 'checkFavorite']);
Route::get('/users/{userId}/favorites', [\App\Http\Controllers\FavoriteController::class, 'getUserFavorites']);
Route::get('/users/{userId}/favorites/{type}', [\App\Http\Controllers\FavoriteController::class, 'getUserFavoritesByType']);
Route::get('/favorites/{type}/{id}/users', [\App\Http\Controllers\FavoriteController::class, 'getItemFavoriteUsers']);

Route::post('/ratings', [\App\Http\Controllers\RatingController::class, 'addOrUpdateRating']);
Route::delete('/ratings', [\App\Http\Controllers\RatingController::class, 'deleteRating']);
Route::get('/ratings/{type}/{id}', [\App\Http\Controllers\RatingController::class, 'getItemRatings']);
Route::get('/ratings/{type}/{id}/statistics', [\App\Http\Controllers\RatingController::class, 'getItemRatingStatistics']);
Route::get('/users/{userId}/ratings', [\App\Http\Controllers\RatingController::class, 'getUserRatings']);

// ====================
// FOLLOWS MANAGEMENT
// ====================
Route::post('/users/{userId}/follow', [\App\Http\Controllers\FollowController::class, 'followUser']);
Route::delete('/users/{userId}/follow', [\App\Http\Controllers\FollowController::class, 'unfollowUser']);
Route::get('/users/{userId}/followers', [\App\Http\Controllers\FollowController::class, 'getUserFollowers']);
Route::get('/users/{userId}/following', [\App\Http\Controllers\FollowController::class, 'getUserFollowing']);
Route::get('/users/{userId}/follow/check', [\App\Http\Controllers\FollowController::class, 'checkFollowStatus']);
Route::get('/users/{userId}/follow/stats', [\App\Http\Controllers\FollowController::class, 'getUserFollowStats']);
Route::get('/users/{userId}/mutual-followers', [\App\Http\Controllers\FollowController::class, 'getMutualFollowers']);


// ==========================================
// NOUVELLES ROUTES API PERSONNALISÉES
// ==========================================

// ====================
// AUTHENTICATION PERSONNALISÉ
// ====================
Route::post('Y/auth/login', [PersonnaliseController::class, 'login']);
Route::post('/Y/auth/register', [PersonnaliseController::class, 'register']);

// ====================
// USER PROFILE
// ====================
Route::middleware('auth:sanctum')->group(function () {
    Route::get('Y/users/profile', [PersonnaliseController::class, 'getUserProfile']);
    Route::post('Y/users/profile', [PersonnaliseController::class, 'updateUserProfile']);
});

Route::get('Y/users/{userId}/posts', [PersonnaliseController::class, 'getUserPosts']);
Route::get('Y/users/{userId}/profile', [PersonnaliseController::class, 'getUserProfileById']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('Y/posts/create', [PersonnaliseController::class, 'createPost']);
    Route::post('Y/posts/{postId}/update', [PersonnaliseController::class, 'updatePost']);
    Route::delete('Y/posts/{postId}/delete', [PersonnaliseController::class, 'deletePost']);

    Route::post('Y/users/{userId}/follow', [PersonnaliseController::class, 'followUser']);
    Route::delete('Y/users/{userId}/unfollow', [PersonnaliseController::class, 'unfollowUser']);

    Route::post('Y/posts/{postId}/favorite', [PersonnaliseController::class, 'addfavoritePost']);

    Route::post('Y/posts/{postId}/comments', [PersonnaliseController::class, 'addCommentToPost']);

    Route::get('Y/fandoms/{fandom_id}', [PersonnaliseController::class, 'getfandombyId']);

    // Allow authenticated user to join a fandom by id
    Route::post('Y/fandoms/{fandom_id}/join', [PersonnaliseController::class, 'joinFandom']);

    // Allow authenticated user to leave a fandom by id
    Route::delete('Y/fandoms/{fandom_id}/leave', [PersonnaliseController::class, 'leaveFandom']);



    Route::post('Y/fandoms', [PersonnaliseController::class, 'createFandom']);
    // Update an existing fandom by id
    Route::post('Y/fandoms/{fandom_id}', [PersonnaliseController::class, 'updateFandom']);

    Route::post('Y/posts/save', [\App\Http\Controllers\Api\PersonnaliseController::class, 'savePost']);
    Route::post('Y/posts/unsave', [\App\Http\Controllers\Api\PersonnaliseController::class, 'unsavePost']);
    Route::get('Y/posts/savedPosts', [\App\Http\Controllers\Api\PersonnaliseController::class, 'getSavedPosts']);

    Route::get('Y/users/my-fandoms', [PersonnaliseController::class, 'getMyFandoms']);
     // Allow admin to change member role in fandom
    Route::put('Y/fandoms/{fandom_id}/members/{user_id}/role', [PersonnaliseController::class, 'changeMemberRole']);

    // Allow admin to remove member from fandom
    Route::delete('Y/fandoms/{fandom_id}/members/{user_id}', [PersonnaliseController::class, 'removeMemberFromFandom']);

    // Allow members to add a post to a fandom
    Route::post('Y/fandoms/{fandom_id}/posts', [PersonnaliseController::class, 'addPostToFandom']);

    // Allow members to update their post in a fandom
    Route::put('Y/fandoms/{fandom_id}/posts/{post_id}', [PersonnaliseController::class, 'updatePostInFandom']);

    // Allow members to delete their post in a fandom
    Route::delete('Y/fandoms/{fandom_id}/posts/{post_id}', [PersonnaliseController::class, 'deletePostInFandom']);

});


Route::get('Y/users/{userId}/followers', [PersonnaliseController::class, 'getUserFollowers']);
Route::get('Y/users/{userId}/following', [PersonnaliseController::class, 'getUserFollowing']);

Route::get('Y/feed/home', [PersonnaliseController::class, 'getHomeFeed']);
Route::get('Y/feed/explore', [PersonnaliseController::class, 'getExploreFeed']);

Route::get('Y/categories/list', [PersonnaliseController::class, 'getCategories']);
//Route::get('/categories/{category}/content', [PersonnaliseController::class, 'getCategoryContent']);

//Route::get('/store/products', [PersonnaliseController::class, 'getStoreProducts']);
Route::get('Y/fandoms', [PersonnaliseController::class, 'getFandoms']);
Route::get('Y/fandoms/trending', [PersonnaliseController::class, 'getTrendingFandoms']);
// Search fandoms by query: /api/fandoms/search?q=QUERY
Route::get('Y/fandoms/search', [PersonnaliseController::class, 'searchFandoms']);


// Get posts for a fandom
Route::get('Y/fandoms/{fandom_id}/posts', [PersonnaliseController::class, 'getFandomPosts']);
// Get members (users) for a fandom
Route::get('Y/fandoms/{fandom_id}/members', [PersonnaliseController::class, 'getFandomMembers']);

Route::get('Y/categories', [PersonnaliseController::class, 'getAllCategories']);
Route::get('Y/categories/{category_id}/subcategories', [PersonnaliseController::class, 'getCategorySubcategories']);

// Search users by name with pagination
Route::get('Y/search/users', [PersonnaliseController::class, 'searchUsers']);
// Search posts by tags, description or subcategory with pagination
Route::get('Y/search/posts', [PersonnaliseController::class, 'searchPosts']);
// Search fandoms by name and description with pagination
Route::get('Y/search/fandom', [PersonnaliseController::class, 'searchFandomsPaginated']);


// ====================
// MAIN CONTENT / FEED
// ====================




// ====================
// CATEGORIES PERSONNALISÉES
// ====================

// ====================
// SOCIAL / USER RELATIONS
// ====================
Route::put('/users/avatar', [PersonnaliseController::class, 'updateAvatar']);
Route::put('/users/cover-photo', [PersonnaliseController::class, 'updateCoverPhoto']);

// ====================
// POSTS
// ====================
Route::post('/posts/{postId}/share', [PersonnaliseController::class, 'sharePost']);

// ====================
// SAVED POSTS - PROTECTED
// ====================
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/posts/saved', [\App\Http\Controllers\Api\PersonnaliseController::class, 'getSavedPosts']);

});

// ====================
// HASHTAGS
// ====================
Route::get('/hashtags/{hashtag}/posts', [PersonnaliseController::class, 'getHashtagPosts']);

// ====================
// STORE / E-COMMERCE
// ====================
Route::get('/store/categories', [PersonnaliseController::class, 'getStoreCategories']);
Route::get('/store/brands', [PersonnaliseController::class, 'getStoreBrands']);
Route::post('/store/cart', [PersonnaliseController::class, 'addToCart']);
Route::post('/store/wishlist/{productId}', [PersonnaliseController::class, 'addToWishlist']);
Route::get('/store/cart', [PersonnaliseController::class, 'getCart']);
Route::put('/store/cart/{itemId}', [PersonnaliseController::class, 'updateCartItem']);
Route::delete('/store/cart/{itemId}', [PersonnaliseController::class, 'removeCartItem']);
Route::post('/store/orders', [PersonnaliseController::class, 'createOrder']);
Route::get('/store/orders', [PersonnaliseController::class, 'getOrders']);
Route::put('/store/orders/{orderId}/cancel', [PersonnaliseController::class, 'cancelOrder']);
Route::get('/store/orders/{orderId}', [PersonnaliseController::class, 'getOrderDetails']);
Route::post('/store/orders/{orderId}/review', [PersonnaliseController::class, 'reviewOrder']);

// ====================
// UPLOAD IMAGE
// ====================
Route::post('/upload/image', [PersonnaliseController::class, 'uploadImage']);

// ========== Partie des Api de Oucharou ==========
// ROLES & PERMISSIONS
// ====================
Route::get('/roles-permissions', [\App\Http\Controllers\Api\M_Controller::class, 'getAllRolesAndPermissions']);

// ====================
// USER MANAGEMENT
// ====================
Route::get('/users', [\App\Http\Controllers\Api\M_Controller::class, 'getAllUsers']);
Route::get('/user/{value}', [\App\Http\Controllers\Api\M_Controller::class, 'getUser']);
Route::post('/users', [\App\Http\Controllers\Api\M_Controller::class, 'addUser']);
Route::put('/users/{id}', [\App\Http\Controllers\Api\M_Controller::class, 'updateUser']);

// ====================
// CATEGORY & SUBCATEGORY MANAGEMENT (M_Controller)
// ====================
Route::get('/categories-simple', [\App\Http\Controllers\Api\M_Controller::class, 'getCategoriesSimple']);
Route::get('/subcategories-simple', [\App\Http\Controllers\Api\M_Controller::class, 'getSubcategoriesSimple']);
Route::get('/categories-with-subs', [\App\Http\Controllers\Api\M_Controller::class, 'getCategoriesWithSubs']);
Route::post('/categories-simple', [\App\Http\Controllers\Api\M_Controller::class, 'addCategorySimple']);
Route::post('/subcategories-simple', [\App\Http\Controllers\Api\M_Controller::class, 'addSubcategorySimple']);
Route::delete('/categories-simple/{id}', [\App\Http\Controllers\Api\M_Controller::class, 'deleteCategorySimple']);
Route::delete('/subcategories-simple/{id}', [\App\Http\Controllers\Api\M_Controller::class, 'deleteSubcategorySimple']);

// ====================
// TAGS MANAGEMENT (M_Controller)
// ====================
Route::get('/tags-simple', [\App\Http\Controllers\Api\M_Controller::class, 'getAllTagsSimple']);
Route::post('/tags-simple', [\App\Http\Controllers\Api\M_Controller::class, 'addTagSimple']);

// ====================
// POSTS MANAGEMENT (M_Controller)
// ====================
Route::get('/posts-simple', [\App\Http\Controllers\Api\M_Controller::class, 'getAllPostsSimple']);
Route::post('/posts-simple', [\App\Http\Controllers\Api\M_Controller::class, 'addPostSimple']);
Route::delete('/posts-simple/{id}', [\App\Http\Controllers\Api\M_Controller::class, 'deletePostSimple']);
Route::put('/posts-simple/{id}', [\App\Http\Controllers\Api\M_Controller::class, 'updatePostSimple']);
Route::get('/posts-by-tag/{tag}', [\App\Http\Controllers\Api\M_Controller::class, 'getPostsByTagSimple']);
Route::get('/posts-by-category-sub', [\App\Http\Controllers\Api\M_Controller::class, 'getPostsByCategorySubSimple']);
Route::get('/posts-by-category/{category_id}', [\App\Http\Controllers\Api\M_Controller::class, 'getPostsByCategorySimple']);
Route::get('/posts-by-subcategory/{subcategory_id}', [\App\Http\Controllers\Api\M_Controller::class, 'getPostsBySubcategorySimple']);

// ====================

// PRODUCTS MANAGEMENT (M_Controller)
// ====================
Route::get('/products-simple', [\App\Http\Controllers\Api\M_Controller::class, 'getAllProductsSimple']);
Route::post('/products-simple', [\App\Http\Controllers\Api\M_Controller::class, 'addProductSimple']);
Route::get('/drops-simple', [\App\Http\Controllers\Api\M_Controller::class, 'getDropsSimple']);
Route::post('/drops-simple', [\App\Http\Controllers\Api\M_Controller::class, 'addDropSimple']);
Route::put('/products-simple/{id}', [\App\Http\Controllers\Api\M_Controller::class, 'updateProductSimple']);
Route::delete('/products-simple/{id}', [\App\Http\Controllers\Api\M_Controller::class, 'deleteProductSimple']);



