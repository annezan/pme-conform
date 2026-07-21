/**
 * API client pour la validation des comptes inscrits via /inscription.
 */

import api, { getCsrfCookie } from '@/api/client';

export async function listComptesEnAttente(params = {}) {
    const r = await api.get('/admin/comptes-en-attente', { params });
    return r.data;
}

export async function validerCompte(userId) {
    await getCsrfCookie();
    const r = await api.post(`/admin/comptes-en-attente/${userId}/valider`);
    return r.data;
}

export async function rejeterCompte(userId, motif = null) {
    await getCsrfCookie();
    const r = await api.post(`/admin/comptes-en-attente/${userId}/rejeter`, { motif });
    return r.data;
}
