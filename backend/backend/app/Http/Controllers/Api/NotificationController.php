<?php

/**
 * Controleur NotificationController — Endpoints pour le bell icon de l'UI.
 *
 * S'appuie sur la table laravel `notifications` (deja existante via
 * la migration de Laravel) et le trait `Notifiable` du modele User.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Liste paginee des notifications de l'user (les non-lues en premier).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) ($request->query('per_page', 20));

        $notifications = $user->notifications()
            ->orderByRaw('read_at IS NULL DESC') // non-lues en haut
            ->orderByDesc('created_at')
            ->paginate(min(max($perPage, 5), 100));

        return response()->json($notifications);
    }

    /**
     * Compteur de notifications non-lues (pour le badge sur la cloche).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function marquerLue(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['message' => 'Notification marquee comme lue.']);
    }

    public function marquerToutesLues(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'Toutes les notifications marquees comme lues.']);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->delete();

        return response()->json(['message' => 'Notification supprimee.']);
    }
}
