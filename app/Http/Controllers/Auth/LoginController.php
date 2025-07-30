<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;

/** @method \Laravel\Socialite\Contracts\Provider stateless() */
class LoginController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/login",
     *     summary="Connexion de l'utilisateur",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="test@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="secret")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connexion réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="token", type="string", example="1|XyzTokenString")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Identifiants invalides",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Identifiants invalides")
     *         )
     *     )
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Identifiants invalides'], 401);
        }

        $token = $user->createToken('flutter-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ]);
    }

   /**
 * @OA\Get(
 *     path="/api/auth/google",
 *     summary="Obtenir l'URL de redirection vers Google",
 *     tags={"Authentification"},
 *     @OA\Response(
 *         response=200,
 *         description="URL de redirection réussie",
 *         @OA\JsonContent(
 *             @OA\Property(property="redirect_url", type="string", example="https://accounts.google.com/o/oauth2/auth?...")
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Erreur lors de la génération de l'URL",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Erreur lors de la génération de l'URL de redirection")
 *         )
 *     )
 * )
 */
public function redirectToGoogle()
{
    try {
        $redirectUrl = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();
        return response()->json(['redirect_url' => $redirectUrl]);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Erreur lors de la génération de l\'URL de redirection: ' . $e->getMessage()], 500);
    }
}

    /**
 * Gère le callback de Google et connecte l'utilisateur.
 * @OA\Get(
 *     path="/api/auth/google/callback",
 *     summary="Callback Google pour l'authentification",
 *     tags={"Authentification"},
 *     @OA\Response(
 *         response=200,
 *         description="Connexion réussie avec Google",
 *         @OA\JsonContent(
 *             @OA\Property(property="user", type="object"),
 *             @OA\Property(property="token", type="string", example="1|XyzTokenString")
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Erreur lors de la connexion avec Google",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Erreur lors de la connexion avec Google")
 *         )
 *     )
 * )
 */


/** @var \Laravel\Socialite\Two\GoogleProvider $googleProvider */        

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            return $googleUser->stateless()->user();

            // Recherche ou création de l'utilisateur
            $user = User::updateOrCreate(
                ['google_id' => $googleUser->getId()],
                [
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'password' => Hash::make(uniqid()), // Mot de passe aléatoire
                ]
            );

            // Génération du token
            $token = $user->createToken('flutter-token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'token' => $token
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la connexion avec Google: ' . $e->getMessage()], 500);
        }
    }
} 

