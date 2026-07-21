/**
 * Client API — Chartes & signatures.
 */

import api, { getCsrfCookie } from './client';

export async function listChartes() {
    const response = await api.get('/chartes');
    return response.data;
}

export async function getCharte(id) {
    const response = await api.get(`/chartes/${id}`);
    return response.data;
}

export async function signerCharte(id, hashAffiche) {
    await getCsrfCookie();
    const response = await api.post(`/chartes/${id}/signer`, {
        accepte_contenu: true,
        hash_affiche: hashAffiche,
    });
    return response.data;
}

export async function listMesSignatures() {
    const response = await api.get('/mes-signatures');
    return response.data;
}

export async function revoquerSignature(id, raison = '') {
    await getCsrfCookie();
    const response = await api.post(`/signatures/${id}/revoquer`, { raison });
    return response.data;
}
