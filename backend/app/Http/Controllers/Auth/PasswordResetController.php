<?php

/**
 * PasswordResetController — Workflow "Mot de passe oublie" / "Reinitialiser".
 *
 * Endpoints publics (pas d'auth) :
 *   POST /api/forgot-password : demande la generation d'un token de reset
 *   POST /api/reset-password  : echange (email, token, password) contre un mot de
 *                                passe valide en base
 *
 * S'appuie sur le PasswordBroker natif de Laravel + la table
 * password_reset_tokens. Le token est stocke hashe + expire automatiquement
 * apres la duree definie dans config('auth.passwords.users.expire') (60 min).
 *
 * Securite :
 *   - Reponse uniforme sur /forgot-password : on renvoie toujours un message
 *     succes que l'email existe ou non (anti-enumeration des comptes).
 *   - Sur le reset reussi : on efface aussi must_change_password et le flag
 *     d'expiration du mot de passe temporaire (cf. Phase 2).
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /**
     * Demande d'envoi du lien de reinitialisation.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->input('email');
        $user = User::where('email', $email)->whereNull('deleted_at')->first();

        // Reponse uniforme : on ne dit pas si l'email existe ou non.
        $reponse = response()->json([
            'message' => 'Si cette adresse e-mail correspond à un compte, un lien de réinitialisation vous a été envoyé.',
        ]);

        if (! $user) {
            return $reponse;
        }

        // Generer un token via le PasswordBroker (stocke en table password_reset_tokens, hashe).
        $token = Password::broker()->createToken($user);

        try {
            Mail::to($user->email)->send(new PasswordResetMail($user, $token));
        } catch (\Throwable $e) {
            Log::warning("Echec envoi email reset pour {$user->email}", ['error' => $e->getMessage()]);
        }

        return $reponse;
    }

    /**
     * Soumet le token + nouveau mot de passe pour finaliser la reinitialisation.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'must_change_password' => false,
                    'mdp_temporaire_expire_le' => null,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.',
            ]);
        }

        return response()->json([
            'message' => 'Lien invalide ou expiré. Veuillez demander un nouveau lien.',
            'code' => 'INVALID_TOKEN',
        ], 422);
    }
}
