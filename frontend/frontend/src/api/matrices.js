/**
 * Client API — Matrices de collecte initiale (Methode 2 etape 1).
 * Le client renseigne par pole les preuves a fournir + les services/postes
 * qui alimenteront l'organigramme puis les questionnaires.
 */

import api, { getCsrfCookie } from './client';

export async function listMesMatrices() {
    const r = await api.get('/client/matrices');
    return r.data;
}

export async function getMatrice(missionId) {
    const r = await api.get(`/missions/${missionId}/matrice`);
    return r.data;
}

export async function updateMatrice(missionId, payload) {
    await getCsrfCookie();
    const r = await api.put(`/missions/${missionId}/matrice`, payload);
    return r.data;
}

export async function remettreMatrice(missionId) {
    await getCsrfCookie();
    const r = await api.post(`/missions/${missionId}/matrice/remettre`);
    return r.data;
}

export async function deriverOrganigramme(missionId) {
    await getCsrfCookie();
    const r = await api.post(`/missions/${missionId}/matrice/deriver-organigramme`);
    return r.data;
}

export async function uploaderPiece(missionId, formData) {
    await getCsrfCookie();
    const r = await api.post(`/missions/${missionId}/matrice/pieces`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
    return r.data;
}

export async function supprimerPiece(pieceId) {
    await getCsrfCookie();
    const r = await api.delete(`/matrice-pieces/${pieceId}`);
    return r.data;
}

export async function ajouterPole(missionId, payload) {
    await getCsrfCookie();
    const r = await api.post(`/missions/${missionId}/matrice/poles`, payload);
    return r.data;
}
