/**
 * API client pour le workflow "Mot de passe oublié" / "Réinitialiser".
 */

import api, { getCsrfCookie } from '@/api/client';

export async function forgotPassword(email) {
    await getCsrfCookie();
    const r = await api.post('/forgot-password', { email });
    return r.data;
}

export async function resetPassword({ email, token, password, password_confirmation }) {
    await getCsrfCookie();
    const r = await api.post('/reset-password', { email, token, password, password_confirmation });
    return r.data;
}
