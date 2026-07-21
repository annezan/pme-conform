/**
 * Client API — Ecarts (manquements de conformite).
 */

import api, { getCsrfCookie } from './client';

export async function listEcarts(params = {}) {
    const response = await api.get('/ecarts', { params });
    return response.data;
}

export async function getEcart(id) {
    const response = await api.get(`/ecarts/${id}`);
    return response.data;
}

export async function updateEcart(id, data) {
    await getCsrfCookie();
    const response = await api.put(`/ecarts/${id}`, data);
    return response.data;
}

export async function deleteEcart(id) {
    await getCsrfCookie();
    const response = await api.delete(`/ecarts/${id}`);
    return response.data;
}

// ---------- Preuves justificatives ----------
export async function listEcartPreuves(ecartId) {
    const response = await api.get(`/ecarts/${ecartId}/preuves`);
    return response.data;
}

export async function uploadEcartPreuve(ecartId, formData) {
    await getCsrfCookie();
    const response = await api.post(`/ecarts/${ecartId}/preuves`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
    return response.data;
}

export async function deleteEcartPreuve(preuveId) {
    await getCsrfCookie();
    const response = await api.delete(`/ecart-preuves/${preuveId}`);
    return response.data;
}

export async function telechargerPreuve(preuve) {
    const response = await api.get(`/ecart-preuves/${preuve.id}/telecharger`, {
        responseType: 'blob',
    });
    const url = window.URL.createObjectURL(response.data);
    const a = document.createElement('a');
    a.href = url;
    a.download = preuve.nom_fichier_original || preuve.libelle || `preuve-${preuve.id}`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
}
