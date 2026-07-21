/**
 * Composant ProtectedRoute — Protection des routes authentifiees.
 *
 * Redirige vers /login si l'utilisateur n'est pas connecte.
 * Peut aussi verifier qu'il possede une permission requise.
 *
 * Usage recommande (par permission, supporte les roles dynamiques) :
 *   <Route element={<ProtectedRoute permission="manage-users" />} ... />
 *   <Route element={<ProtectedRoute anyPermission={['view-analyses', 'access-client-space']} />} ... />
 *
 * Usage legacy (par nom de role, deprecate) : `roles={['admin']}` reste accepte
 * pour ne pas casser les routes existantes mais devrait etre migre vers permission.
 */

import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '@/contexts/AuthContext';

export default function ProtectedRoute({ roles, permission, anyPermission, excludePermission, allowDuringPasswordChange = false }) {
    const { user, loading, hasPermission } = useAuth();

    if (loading) {
        return (
            <div className="min-h-screen flex items-center justify-center">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-800 mx-auto"></div>
                    <p className="mt-4 text-gray-600">Chargement...</p>
                </div>
            </div>
        );
    }

    if (!user) {
        return <Navigate to="/login" replace />;
    }

    // Si l'utilisateur a un mot de passe temporaire a changer, on bloque l'acces
    // a tout sauf la page /changer-mot-de-passe (qui passe allowDuringPasswordChange).
    if (user.must_change_password && !allowDuringPasswordChange) {
        return <Navigate to="/changer-mot-de-passe" replace />;
    }

    // Verification par permission (moderne, supporte les roles dynamiques)
    if (permission && !hasPermission(permission)) {
        return <Navigate to="/" replace />;
    }
    if (anyPermission && anyPermission.length > 0 && !hasPermission(anyPermission)) {
        return <Navigate to="/" replace />;
    }
    // Exclusion : si l'utilisateur a cette permission, il ne peut PAS acceder
    // a la route (utile pour les pages reservees cote client, inaccessibles aux ASC).
    if (excludePermission && hasPermission(excludePermission)) {
        return <Navigate to="/" replace />;
    }

    // Verification par nom de role (legacy)
    if (roles && roles.length > 0) {
        const hasRequiredRole = roles.some((role) => user.roles?.includes(role));
        if (!hasRequiredRole) {
            return <Navigate to="/" replace />;
        }
    }

    return <Outlet />;
}
