/**
 * Contexte d'authentification React.
 *
 * Fournit a toute l'application :
 * - L'utilisateur connecte (user)
 * - Les fonctions login/logout
 * - L'etat de chargement initial
 *
 * NB : l'API AUDREY renvoie le role sous forme de string singuliere
 *      (`user.role = 'admin'`) ou d'objet (`{id, name}`). L'ancienne API
 *      Spatie renvoyait un tableau (`user.roles = ['admin']`). Pour eviter
 *      de casser tous les consommateurs (hasRole, ProtectedRoute, badges...),
 *      on normalise le user en ajoutant systematiquement un tableau `roles`.
 */

import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import api, { getCsrfCookie } from '@/api/client';

const AuthContext = createContext(null);

/**
 * Normalise le user pour exposer un `roles: string[]` quelle que soit la shape backend.
 * - AUDREY : `{ role: 'admin', permissions: [...] }`            -> roles: ['admin']
 * - AUDREY alt : `{ role: { id, name }, ... }`                  -> roles: ['admin']
 * - Spatie : `{ roles: [{ id, name }] | ['admin'] }`            -> roles: ['admin']
 */
function normaliserUser(u) {
    if (!u) return u;

    let roles = [];
    if (Array.isArray(u.roles)) {
        roles = u.roles.map(r => (typeof r === 'string' ? r : r?.name)).filter(Boolean);
    } else if (u.role) {
        roles = [typeof u.role === 'string' ? u.role : u.role?.name].filter(Boolean);
    }

    const permissions = Array.isArray(u.permissions)
        ? u.permissions.map(p => (typeof p === 'string' ? p : p?.name)).filter(Boolean)
        : [];

    return { ...u, roles, permissions };
}

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    const fetchUser = useCallback(async () => {
        try {
            const response = await api.get('/user');
            setUser(normaliserUser(response.data.user));
        } catch {
            setUser(null);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchUser();
    }, [fetchUser]);

    const login = async (email, password, remember = false) => {
        await getCsrfCookie();
        const response = await api.post('/login', { email, password, remember });
        const userNorm = normaliserUser(response.data.user);
        setUser(userNorm);
        return { ...response.data, user: userNorm };
    };

    const register = async (payload) => {
        await getCsrfCookie();
        const response = await api.post('/register', payload);
        const userNorm = normaliserUser(response.data.user);
        setUser(userNorm);
        return { ...response.data, user: userNorm };
    };

    const logout = async () => {
        try {
            await getCsrfCookie();
            await api.post('/logout');
        } catch {
            // Ignorer les erreurs — on deconnecte quand meme cote client
        }
        setUser(null);
    };

    const hasRole = (role) => {
        if (!user?.roles) return false;
        if (Array.isArray(role)) {
            return role.some(r => user.roles.includes(r));
        }
        return user.roles.includes(role);
    };

    const hasPermission = (permission) => {
        if (!user?.permissions) return false;
        // Marqueur super-user envoye par le backend pour le role admin :
        // bypass total quel que soit le nom demande.
        if (user.permissions.includes('*')) return true;
        if (Array.isArray(permission)) {
            return permission.some(p => user.permissions.includes(p));
        }
        return user.permissions.includes(permission);
    };

    const value = {
        user,
        loading,
        login,
        register,
        logout,
        hasRole,
        hasPermission,
        fetchUser,
    };

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth doit etre utilise dans un AuthProvider');
    }
    return context;
}
