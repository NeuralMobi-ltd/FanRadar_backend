<?php

use App\Http\Controllers\api\AuthentificationController;
use App\Http\Controllers\api\PersonnaliseController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FandomController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderProductController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\SubcategoryController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TagsController;
use App\Http\Controllers\UserController;
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

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);
Route::get('Y/categories/{category_id}/subcategories', [SubcategoryController::class, 'getCategorySubcategories']);
Route::get('Y/categories', [CategoryController::class, 'getAllCategories']);
// ========== ROUTES PUBLIQUES POUR E-COMMERCE ==========


Route::middleware('auth:sanctum')->group(function () {

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




Route::post('/tags/attach', [TagController::class, 'attachTag']);// donner et cree un tage pour un post ou product
Route::delete('/tags/detach', [TagController::class, 'detachTag']);

// cette Partie de categories et commun entre yassin et oucharou


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
});

// ==========================================
// NOUVELLES ROUTES API PERSONNALISÉES
// ==========================================

// ====================
// AUTHENTICATION PERSONNALISÉ
// ====================
Route::post('Y/auth/login', [AuthentificationController::class, 'loginUser']);
Route::post('/Y/auth/register', [AuthentificationController::class, 'registerUser']);
//otp
Route::post('/forgetPassword', [AuthentificationController::class, 'forgetPassword']);
Route::post('/resetPassword', [AuthentificationController::class, 'resetPassword']);

Route::post('/verifyregister', [AuthentificationController::class, 'verifyregister']);

Route::post('/verifyOTPforgetPassword', [AuthentificationController::class, 'verifyOTPforgetPassword']);


// ====================
// USER PROFILE
// ====================
Route::middleware('auth:sanctum')->group(function () {
    Route::get('Y/users/profile', [UserController::class, 'getUserProfile']);
    Route::post('Y/users/profile', [UserController::class, 'updateUserProfile']);


    Route::get('Y/users/{userId}/posts', [PostController::class, 'getUserPosts']);
    Route::get('Y/users/{userId}/profile', [UserController::class, 'getUserProfileById']);



    Route::post('Y/posts/create', [PostController::class, 'createPost']);
    Route::post('Y/posts/{postId}/update', [PostController::class, 'updatePost']);
    Route::delete('Y/posts/{postId}/delete', [PostController::class, 'deletePost']);

    Route::post('Y/users/{userId}/follow', [UserController::class, 'followUser']);
    Route::delete('Y/users/{userId}/unfollow', [UserController::class, 'unfollowUser']);


    Route::post('Y/posts/{postId}/comments', [PostController::class, 'addCommentToPost']);

    Route::get('Y/fandoms/{fandom_id}', [FandomController::class, 'getfandombyId']);

    // Allow authenticated user to join a fandom by id
    Route::post('Y/fandoms/{fandom_id}/join', [FandomController::class, 'joinFandom']);

    // Allow authenticated user to leave a fandom by id
    Route::delete('Y/fandoms/{fandom_id}/leave', [FandomController::class, 'leaveFandom']);



    Route::post('Y/fandoms', [FandomController::class, 'createFandom']);
    // Update an existing fandom by id
    Route::post('Y/fandoms/{fandom_id}', [FandomController::class, 'updateFandom']);

    Route::post('Y/posts/save', [PostController::class, 'savePost']);
    Route::post('Y/posts/unsave', [PostController::class, 'unsavePost']);
    Route::get('Y/posts/savedPosts', [PostController::class, 'getSavedPosts']);

    Route::get('Y/users/my-fandoms', [FandomController::class, 'getMyFandoms']);
     // Allow admin to change member role in fandom
    Route::put('Y/fandoms/{fandom_id}/members/{user_id}/role', [FandomController::class, 'changeMemberRole']);

    // Allow admin to remove member from fandom
    Route::delete('Y/fandoms/{fandom_id}/members/{user_id}', [FandomController::class, 'removeMemberFromFandom']);

    // Allow members to add a post to a fandom
    Route::post('Y/fandoms/{fandom_id}/posts', [FandomController::class, 'addPostToFandom']);

    // Allow members to update their post in a fandom
    Route::put('Y/fandoms/{fandom_id}/posts/{post_id}', [FandomController::class, 'updatePostInFandom']);

    // Allow members to delete their post in a fandom
    Route::delete('Y/fandoms/{fandom_id}/posts/{post_id}', [FandomController::class, 'deletePostInFandom']);

    Route::get('Y/feed/following', [PostController::class, 'getFollowingFeed']);




Route::get('Y/users/{userId}/followers', [UserController::class, 'getUserFollowers']);
Route::get('Y/users/{userId}/following', [UserController::class, 'getUserFollowing']);

Route::get('Y/feed/home', [PostController::class, 'getHomeFeed']);
Route::get('Y/feed/explore', [PostController::class, 'getExploreFeed']);


//Route::get('Y/categories/list', [PersonnaliseController::class, 'getCategories']);
//Route::get('/categories/{category}/content', [PersonnaliseController::class, 'getCategoryContent']);
//Route::get('/store/products', [PersonnaliseController::class, 'getStoreProducts']);


Route::get('Y/fandoms', [FandomController::class, 'getFandoms']);

// Search fandoms by query: /api/fandoms/search?q=QUERY
Route::get('Y/fandoms/search', [FandomController::class, 'searchFandoms']);

// Get fandoms by category: /api/categories/{category_id}/fandoms
Route::get('Y/categories/{category_id}/fandoms', [FandomController::class, 'getFandomsByCategory']);

// Get posts for a fandom
Route::get('Y/fandoms/{fandom_id}/posts', [FandomController::class, 'getFandomPosts']);
// Get members (users) for a fandom
Route::get('Y/fandoms/{fandom_id}/members', [FandomController::class, 'getFandomMembers']);



// Search users by name with pagination
Route::get('Y/search/users', [UserController::class, 'searchUsers']);
// Search posts by tags, description or subcategory with pagination
Route::get('Y/search/posts', [PostController::class, 'searchPosts']);
// Search fandoms by name and description with pagination
Route::get('Y/search/fandom', [FandomController::class, 'searchFandomsPaginated']);
// Search products by name and description with pagination
Route::get('Y/search/products', [ProductController::class, 'searchProducts']);


Route::get('Y/subcategories/{subcategory}/content', [SubcategoryController::class, 'getSubcategoryContent']);
Route::get('Y/subcategories/{subcategory_id}/fandoms', [SubcategoryController::class, 'getSubcategoryFandoms']);

Route::get('Y/hashtags/trending', [TagsController::class, 'getTrendingHashtags']);
Route::get('Y/hashtags/{hashtag_id}/posts', [TagsController::class, 'getHashtagPosts']);
Route::get('Y/fandoms/trending/top', [FandomController::class, 'getTrendingFandoms']);

Route::get('Y/posts/trending/top', [PostController::class, 'getTrendingPosts']);
Route::get('Y/posts/{postId}/comments', [PostController::class, 'getPostComments']);
Route::get('Y/categories/{category_id}/posts', [PostController::class, 'getCategoryPosts']);
Route::get('Y/categories/{category_id}/fandoms', [FandomController::class, 'getCategoryFandoms']);


Route::post('Y/posts/{postId}/favorite', [FavoriteController::class, 'addfavoritePost']);
Route::delete('Y/posts/{postId}/removefavorite', [FavoriteController::class, 'removefavoritePost']);
Route::post('Y/favorites/{pProductId}/favorite', [FavoriteController::class, 'addFavoriteProduct']);
Route::delete('Y/favorites/{pProductId}/removefavorite', [FavoriteController::class, 'removeFavoriteProduct']);

// Routes pour afficher les favoris
Route::get('Y/myfavorites/posts', [PostController::class, 'getFavoritePosts']);
Route::get('Y/myfavorites/products', [ProductController::class, 'getFavoriteProducts']);

Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);

// Get limited edition products (drag products)
Route::get('Y/products/drag', [ProductController::class, 'getDragProducts']);

Route::post('products', [ProductController::class, 'store']);
Route::put('products/{product}', [ProductController::class, 'update']);
Route::delete('products/{product}', [ProductController::class, 'destroy']);



Route::post('/ratings', [RatingController::class, 'addOrUpdateRating']);
Route::delete('/ratings', [RatingController::class, 'deleteRating']);

// 🛒 COMMANDES
Route::get('/orders', [OrderController::class, 'index']);
Route::get('/orders/my-orders', [OrderController::class, 'getMyOrders']);
Route::get('/orders/{order}', [OrderController::class, 'show']);
Route::post('/orders', [OrderController::class, 'store']);

Route::delete('/orders/{order}', [OrderController::class, 'destroy']);
Route::put('/orders/{order}', [OrderController::class, 'update']);

// ====================
// admin api
// ====================

Route::get('admin/users', [\App\Http\Controllers\api\M_Controller::class, 'getAllUsers']);
Route::get('admin/user/{id}', [\App\Http\Controllers\api\M_Controller::class, 'getUser']);
Route::post('admin/users', [\App\Http\Controllers\api\M_Controller::class, 'addUser']);
Route::put('admin/users/{id}', [\App\Http\Controllers\api\M_Controller::class, 'updateUser']);
Route::delete('admin/users/{id}', [\App\Http\Controllers\api\M_Controller::class, 'deleteUser']);




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
Route::post('/posts/{postId}/share', [PostController::class, 'sharePost']);

// ====================
// SAVED POSTS - PROTECTED
// ====================

// ====================
// HASHTAGS
// ====================

// ====================
// STORE / E-COMMERCE
// ====================
/*
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
*/

// ========== Partie des Api de Oucharou ==========
// ROLES & PERMISSIONS
// ====================
Route::get('/roles-permissions', [\App\Http\Controllers\api\M_Controller::class, 'getAllRolesAndPermissions']);

// ====================
// USER MANAGEMENT
// ====================
Route::get('/users', [\App\Http\Controllers\api\M_Controller::class, 'getAllUsers']);
Route::get('/user/{value}', [\App\Http\Controllers\api\M_Controller::class, 'getUser']);
Route::post('/users', [\App\Http\Controllers\api\M_Controller::class, 'addUser']);
Route::post('/users/{id}', [\App\Http\Controllers\api\M_Controller::class, 'updateUser']);

// ====================
// CATEGORY & SUBCATEGORY MANAGEMENT (M_Controller)
// ====================
Route::get('/categories-simple', [\App\Http\Controllers\api\M_Controller::class, 'getCategoriesSimple']);
Route::get('/subcategories-simple', [\App\Http\Controllers\api\M_Controller::class, 'getSubcategoriesSimple']);
Route::get('/categories-with-subs', [\App\Http\Controllers\api\M_Controller::class, 'getCategoriesWithSubs']);
Route::post('/categories-simple', [\App\Http\Controllers\api\M_Controller::class, 'addCategorySimple']);
Route::post('/subcategories-simple', [\App\Http\Controllers\api\M_Controller::class, 'addSubcategorySimple']);
Route::delete('/categories-simple/{id}', [\App\Http\Controllers\api\M_Controller::class, 'deleteCategorySimple']);
Route::delete('/subcategories-simple/{id}', [\App\Http\Controllers\api\M_Controller::class, 'deleteSubcategorySimple']);

// ====================
// TAGS MANAGEMENT (M_Controller)
// ====================
Route::get('/tags-simple', [\App\Http\Controllers\api\M_Controller::class, 'getAllTagsSimple']);
Route::post('/tags-simple', [\App\Http\Controllers\api\M_Controller::class, 'addTagSimple']);

// ====================
// POSTS MANAGEMENT (M_Controller)
// ====================
Route::get('/posts-simple', [\App\Http\Controllers\api\M_Controller::class, 'getAllPostsSimple']);
Route::post('/posts-simple', [\App\Http\Controllers\api\M_Controller::class, 'addPostSimple']);
Route::delete('/posts-simple/{id}', [\App\Http\Controllers\api\M_Controller::class, 'deletePostSimple']);
Route::put('/posts-simple/{id}', [\App\Http\Controllers\api\M_Controller::class, 'updatePostSimple']);
Route::get('/posts-by-tag/{tag}', [\App\Http\Controllers\api\M_Controller::class, 'getPostsByTagSimple']);
Route::get('/posts-by-category-sub', [\App\Http\Controllers\api\M_Controller::class, 'getPostsByCategorySubSimple']);
Route::get('/posts-by-category/{category_id}', [\App\Http\Controllers\api\M_Controller::class, 'getPostsByCategorySimple']);
Route::get('/posts-by-subcategory/{subcategory_id}', [\App\Http\Controllers\api\M_Controller::class, 'getPostsBySubcategorySimple']);

// ====================

// PRODUCTS MANAGEMENT (M_Controller)
// ====================
Route::get('/products-simple', [\App\Http\Controllers\api\M_Controller::class, 'getAllProductsSimple']);
Route::post('/products-simple', [\App\Http\Controllers\api\M_Controller::class, 'addProductSimple']);
Route::get('/drops-simple', [\App\Http\Controllers\api\M_Controller::class, 'getDropsSimple']);
Route::post('/drops-simple', [\App\Http\Controllers\api\M_Controller::class, 'addDropSimple']);
Route::put('/products-simple/{id}', [\App\Http\Controllers\api\M_Controller::class, 'updateProductSimple']);
Route::delete('/products-simple/{id}', [\App\Http\Controllers\api\M_Controller::class, 'deleteProductSimple']);

});


