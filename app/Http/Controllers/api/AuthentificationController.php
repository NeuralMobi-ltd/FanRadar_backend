<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OTPMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;

class AuthentificationController extends Controller
{
   public function login(Request $request)
            {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
                ]);

                $user = User::where('email', $request->email)->first();

                if (!$user || !Hash::check($request->password, $user->password)) {
                    return response()->json([
                        'message' => 'Email ou mot de passe invalide.'
                    ], 401);
                }

                // Vérifier si le compte est vérifié
                if (!$user->is_verified) {
                    return response()->json([
                        'message' => 'Votre compte n\'est pas encore vérifié. Veuillez vérifier votre email avec le code OTP reçu.'
                    ], 403);
                }

                // Créer un token avec Sanctum
                $token = $user->createToken('auth_token')->plainTextToken;

                // Ensure we return an exact role string and also full roles/permissions arrays
                $roleNames = $user->getRoleNames();
                $permissionNames = $user->getPermissionNames();

                // Fallback: if user has no roles yet, assign default 'user'
                if ($roleNames->isEmpty()) {
                    $user->assignRole('user');
                    $roleNames = $user->getRoleNames();
                }

                return response()->json([
                    'message' => 'Connexion réussie.',
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'profile_image' => $user->profile_image,
                        'role' => $roleNames->first() ?? null,
                        'permissions' => $permissionNames->toArray(),
                    ],
                    'token' => $token,
                ]);
            }


public function loginUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Email et mot de passe requis',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Email ou mot de passe invalide'
                ]
            ], 401);
        }

        // Vérifier si le compte est vérifié
        if (!$user->is_verified) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_NOT_VERIFIED',
                    'message' => 'Votre compte n\'est pas encore vérifié. Veuillez vérifier votre email avec le code OTP reçu.'
                ]
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Include exact role string and arrays
        $roleNames = $user->getRoleNames();
        $permissionNames = $user->getPermissionNames();

        // Fallback: if user has no roles yet, assign default 'user'
        if ($roleNames->isEmpty()) {
            $user->assignRole('user');
            $roleNames = $user->getRoleNames();
        }



        // Récupérer les catégories préférées
        $preferredCategories = $user->preferredCategories()->pluck('category_id')->toArray();

        // Calcul dynamique des stats (followers, following, posts)
        $followersCount = method_exists($user, 'followers') ? $user->followers()->count() : 0;
        $followingCount = method_exists($user, 'following') ? $user->following()->count() : 0;
        $postsCount = method_exists($user, 'posts') ? $user->posts()->count() : (\App\Models\Post::where('user_id', $user->id)->count());

        return response()->json([
            'message' => 'Connexion réussie.',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'profile_image' => $user->profile_image,
                'background_image' => $user->background_image,
                'date_naissance' => $user->date_naissance,
                'gender' => $user->gender,
                'preferred_categories' => $preferredCategories,
                'role' => $roleNames->first() ?? null,
                'permissions' => $permissionNames->toArray(),
                'stats' => [
                    'followers' => $followersCount,
                    'following' => $followingCount,
                    'posts' => $postsCount
                ],
            ],
            'token' => $token,
        ], 200);
    }





     public function registerUser(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email',
            'password' => 'required|string|min:6',
            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'date_naissance' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'bio' => 'nullable|string|max:2000',
            'preferred_categories' => 'nullable|array',
            'preferred_categories.*' => 'integer|exists:categories,id',

        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Vérifier si un utilisateur avec cet email existe déjà
        $existingUser = User::where('email', $request->email)->first();

        if ($existingUser) {
            // Si l'utilisateur existe mais n'est PAS vérifié, le supprimer
            if (!$existingUser->is_verified) {
                $existingUser->delete();
            } else {
                // Si l'utilisateur existe ET est vérifié, retourner une erreur
                return response()->json([
                    'errors' => ['email' => ['Cet email est déjà utilisé.']]
                ], 422);
            }
        }

        // Générer un OTP pour la vérification
        $otp = rand(100000, 999999);

        // Traitement de l'image si elle est envoyée
        $profileImagePath = null;
        if ($request->hasFile('profile_image')) {
            $path = $request->file('profile_image')->store('profile', 'public');
            $profileImagePath = 'storage/' . $path;
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'profile_image' => $profileImagePath ?? null,
            'background_image' => null,
            'date_naissance' => $request->date_naissance,
            'gender' => $request->gender,
            'bio' => $request->bio ?? null,
            'otp' => $otp,
            'otp_created_at' => now(),
            'is_verified' => false,
        ]);

        $user->assignRole('user');

        // Envoyer l'OTP par email pour la vérification
        Mail::to($user->email)->send(new OTPMail($otp));

        // Enregistrer les catégories préférées si fournies
        $preferredCategories = [];
        if ($request->has('preferred_categories')) {
            foreach ($request->preferred_categories as $catId) {
                $user->preferredCategories()->create(['category_id' => $catId]);
            }
            $preferredCategories = $user->preferredCategories()->pluck('category_id')->toArray();
        }

        // Création du token Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        // Ensure we return an exact role string and also full roles/permissions arrays
        $roleNames = $user->getRoleNames();
        $permissionNames = $user->getPermissionNames();

        // (Suppression du calcul de l'âge)

        return response()->json([
            'message' => 'Inscription réussie. Un code OTP a été envoyé à votre email. Veuillez récupérer et vérifier votre OTP pour activer votre compte.',
            'email' => $user->email,
            'next_step' => 'Vérifiez votre email et utilisez l\'API verifyOTP pour confirmer votre inscription.'
        ], 201);
    }


    public function register(Request $request)
            {
                $validator = Validator::make($request->all(), [
                    'first_name' => 'required|string|max:255',
                    'last_name' => 'required|string|max:255',
                    'email' => 'required|string|email|unique:users,email',
                    'password' => 'required|string|min:6',
                    'profile_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                    'bio' => 'nullable|string|max:2000',
                ]);

                if ($validator->fails()) {
                    return response()->json(['errors' => $validator->errors()], 422);
                }

                // Traitement de l'image si elle est envoyée
                $profileImagePath = null;
                if ($request->hasFile('profile_image')) {
                    $path = $request->file('profile_image')->store('profile', 'public');
                    $profileImagePath = 'storage/' . $path;
                }

                // Création de l'utilisateur
                $user = User::create([
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'profile_image' => $profileImagePath ?? null,
                    'bio' => $request->bio ?? null,
                ]);

                $user->assignRole('user');// Assign a default role 'user'

                // Création du token Sanctum
                $token = $user->createToken('auth_token')->plainTextToken;

                // Ensure we return an exact role string and also full roles/permissions arrays
                $roleNames = $user->getRoleNames();
                $permissionNames = $user->getPermissionNames();

                return response()->json([
                    'message' => 'Inscription réussie.',
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'profile_image' => $user->profile_image,
                        'role' => $roleNames->first() ?? null,
                        'permissions' => $permissionNames->toArray(),
                    ],
                    'token' => $token,
                ], 201);
            }



    public function logout(Request $request)
            {
                $user = $request->user();
                $token = $user ? $user->currentAccessToken() : null;
                // Correction : vérifier que $token est bien une instance de PersonalAccessToken
                if ($token && ($token instanceof \Laravel\Sanctum\PersonalAccessToken)) {
                    $token->delete();
                    return response()->json(['message' => 'Logout successful']);
                }
                return response()->json(['message' => 'No active session found'], 401);
            }

    public function logoutfromAllDevices(Request $request)
            {
                // Supprime tous les tokens de l'utilisateur
                $request->user()->tokens()->delete();

                return response()->json(['message' => 'Logged out from all devices.']);
            }



    public function forgetPassword(Request $request)
        {

            $request->validate([
                'email' => 'required|email',
            ]);

            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return response()->json(['error' => 'Email not found'], 404);
            }

            $otp = rand(100000, 999999);

            $user->update([
                'otp' => $otp,
                'otp_created_at' => now()
            ]);
            $subject = 'OTP for Password Reset';

            Mail::to($user->email)->send(new OTPMail($otp));

            return response()->json(['message' => 'OTP sent to your email'], 200);
        }


    public function resetPassword(Request $request)
        {
            $request->validate([
                    'email' => 'required|email',
                    'otp' => 'required|numeric',
                    'password' => 'required|string|min:6|confirmed',
                    'password_confirmation' => 'required|string|min:6'
            ]);

            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return response()->json(['error' => 'Email invalide'], 404);
            }

            if($user->otp!=$request->otp){
                return response()->json(['error' => 'OTP invalide'], 404);
            }

            $otpCreatedAt = $user->otp_created_at;
            if (\Carbon\Carbon::now()->diffInMinutes($otpCreatedAt) > 10) {
                return response()->json(['error' => 'OTP expiré'], 400);
            }

            // Réinitialiser le mot de passe
            $user->update([
                'password' => bcrypt($request->password),
                'otp' => null,
                'otp_created_at' => null
            ]);

            return response()->json(['message' => 'Mot de passe réinitialisé avec succès'], 200);
        }

  public function verifyregister(Request $request)
        {
            $request->validate([
                'email' => 'required|email|exists:users,email',
                'otp' => 'required|numeric',
            ]);

            // Récupérer l'utilisateur par email d'abord
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json(['error' => 'Utilisateur introuvable'], 404);
            }

            // Vérifier si l'OTP correspond
            if ($user->otp != $request->otp) {
                // Supprimer le compte si l'OTP est invalide et le compte n'est pas vérifié
                if (!$user->is_verified) {
                    $user->delete();
                    return response()->json(['error' => 'OTP invalide. Le compte a été supprimé automatiquement.'], 400);
                }
                return response()->json(['error' => 'OTP invalide'], 400);
            }

            // Vérifier l'expiration (10 minutes)
            if (Carbon::now()->diffInMinutes($user->otp_created_at) > 10) {
                // Supprimer le compte si l'OTP est expiré et le compte n'est pas vérifié
                if (!$user->is_verified) {

                    return response()->json(['error' => 'OTP expiré. Le compte a été supprimé automatiquement.'], 400);
                }
                return response()->json(['error' => 'OTP expiré'], 400);
            }

            // Mettre à jour is_verified à true si l'OTP est correct
            $user->update([
                'is_verified' => true,
                'otp' => null,
                'otp_created_at' => null
            ]);

            return response()->json(['message' => 'OTP validé et compte vérifié'], 200);
        }

       public function verifyOTPforgetPassword(Request $request)
        {
            $request->validate([
                'email' => 'required|email|exists:users,email',
                'otp' => 'required|numeric',
            ]);

            // Récupérer l'utilisateur par email d'abord
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json(['error' => 'Utilisateur introuvable'], 404);
            }

            // Vérifier si l'OTP correspond
            if ($user->otp != $request->otp) {
                return response()->json(['error' => 'OTP invalide'], 400);
            }

            // Vérifier l'expiration (10 minutes)
            if (Carbon::now()->diffInMinutes($user->otp_created_at) > 10) {

                return response()->json(['error' => 'OTP expiré'], 400);
            }

            return response()->json(['message' => 'OTP validé et compte vérifié'], 200);
        }

}
