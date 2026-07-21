/**
 * Client API — Referentiels (corpus legaux ARTCI/ISO/UEMOA).
 */

import api, { getCsrfCookie } from './client';

export async function listReferentiels(params = {}) {
    const response = await api.get('/referentiels', { params });
    return response.data;
}

export async function getReferentiel(id) {
    const response = await api.get(`/referentiels/${id}`);
    return response.data;
}

export async function createReferentiel(formData) {
    await getCsrfCookie();
    const response = await api.post('/referentiels', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
    return response.data;
}

export async function updateReferentiel(id, data) {
    await getCsrfCookie();
    const response = await api.put(`/referentiels/${id}`, data);
    return response.data;
}

export async function deleteReferentiel(id) {
    await getCsrfCookie();
    const response = await api.delete(`/referentiels/${id}`);
    return response.data;
}

export async function reindexerReferentiel(id) {
    await getCsrfCookie();
    const response = await api.post(`/referentiels/${id}/reindexer`);
    return response.data;
}

export async function uploaderFichierReferentiel(id, fichier) {
    await getCsrfCookie();
    const fd = new FormData();
    fd.append('fichier', fichier);
    const response = await api.post(`/referentiels/${id}/fichier`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
    return response.data;
}

export async function saisirContenuReferentiel(id, contenu) {
    await getCsrfCookie();
    const response = await api.post(`/referentiels/${id}/contenu`, { contenu });
    return response.data;
}
