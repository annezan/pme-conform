/**
 * Client API — Registre KYC (registre des traitements genere).
 */

import api, { getCsrfCookie } from './client';

export async function listRegistres(params = {}) {
    const response = await api.get('/registres-kyc', { params });
    return response.data;
}

export async function genererRegistre(clientId = null) {
    await getCsrfCookie();
    const payload = clientId ? { client_id: clientId } : {};
    const response = await api.post('/registres-kyc', payload);
    return response.data;
}

export async function getRegistre(id) {
    const response = await api.get(`/registres-kyc/${id}`);
    return response.data;
}

export async function telechargerRegistre(registre) {
    const response = await api.get(`/registres-kyc/${registre.id}/telecharger`, {
        responseType: 'blob',
    });
    const url = window.URL.createObjectURL(response.data);
    const a = document.createElement('a');
    a.href = url;
    // Format reel du registre genere : xlsx (modele MOBISOFT). On respecte
    // tout de meme l'extension renvoyee par le backend si elle existe.
    const extension = registre.format || 'xlsx';
    a.download = `registre-${registre.reference}.${extension}`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
}

export async function supprimerRegistre(id) {
    await getCsrfCookie();
    const response = await api.delete(`/registres-kyc/${id}`);
    return response.data;
}
