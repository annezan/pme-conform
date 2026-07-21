/**
 * Client API — Plans d'actions.
 */

import api, { getCsrfCookie } from './client';

export async function listPlansActions(params = {}) {
    const response = await api.get('/plans-actions', { params });
    return response.data;
}

export async function getPlanAction(id) {
    const response = await api.get(`/plans-actions/${id}`);
    return response.data;
}

export async function creerPlanAction(data) {
    await getCsrfCookie();
    const response = await api.post('/plans-actions', data);
    return response.data;
}

export async function majPlanAction(id, data) {
    await getCsrfCookie();
    const response = await api.put(`/plans-actions/${id}`, data);
    return response.data;
}

export async function accepterPlanAction(id) {
    await getCsrfCookie();
    const response = await api.post(`/plans-actions/${id}/accepter`);
    return response.data;
}

export async function cloturerPlanAction(id, commentaire = '') {
    await getCsrfCookie();
    const response = await api.post(`/plans-actions/${id}/cloturer`, { commentaire });
    return response.data;
}

export async function supprimerPlanAction(id) {
    await getCsrfCookie();
    const response = await api.delete(`/plans-actions/${id}`);
    return response.data;
}

export async function ajouterItem(planId, data) {
    await getCsrfCookie();
    const response = await api.post(`/plans-actions/${planId}/items`, data);
    return response.data;
}

export async function majItem(planId, itemId, data) {
    await getCsrfCookie();
    const response = await api.put(`/plans-actions/${planId}/items/${itemId}`, data);
    return response.data;
}

export async function supprimerItem(planId, itemId) {
    await getCsrfCookie();
    const response = await api.delete(`/plans-actions/${planId}/items/${itemId}`);
    return response.data;
}

// ----- Soumission + verification -----

export async function soumettrePlanAction(planId) {
    await getCsrfCookie();
    const response = await api.post(`/plans-actions/${planId}/soumettre`);
    return response.data;
}

export async function rouvrirPlanAction(planId) {
    await getCsrfCookie();
    const response = await api.post(`/plans-actions/${planId}/rouvrir`);
    return response.data;
}

// ----- Preuves attachees aux items -----

export async function listPreuvesItem(itemId) {
    const response = await api.get(`/plan-action-items/${itemId}/preuves`);
    return response.data.preuves;
}

export async function uploadPreuveItem(itemId, formData) {
    await getCsrfCookie();
    const response = await api.post(`/plan-action-items/${itemId}/preuves`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
    return response.data;
}

export async function supprimerPreuveItem(preuveId) {
    await getCsrfCookie();
    const response = await api.delete(`/plan-action-item-preuves/${preuveId}`);
    return response.data;
}

export async function telechargerPreuveItem(preuve) {
    const response = await api.get(`/plan-action-item-preuves/${preuve.id}/telecharger`, {
        responseType: 'blob',
    });
    const blob = new Blob([response.data], { type: preuve.mime || 'application/octet-stream' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = preuve.nom_fichier_original;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
}

export const VERDICT_LABEL = {
    conforme: { label: 'Conforme', color: 'success', icon: 'check' },
    partielle: { label: 'Partielle', color: 'warning', icon: 'half' },
    non_conforme: { label: 'Non conforme', color: 'danger', icon: 'x' },
    non_evalue: { label: 'Non evalue', color: 'gray', icon: 'dash' },
};

export const PRIORITE_LABEL = {
    p1: { label: 'P1 — Immediate', color: 'danger', delai: '30 jours' },
    p2: { label: 'P2 — Court terme', color: 'warning', delai: '90 jours' },
    p3: { label: 'P3 — Moyen terme', color: 'info', delai: '6 mois' },
    p4: { label: 'P4 — Opportuniste', color: 'gray', delai: '12 mois' },
};

export const STATUT_ITEM = {
    a_faire: { label: 'A faire', color: 'gray' },
    en_cours: { label: 'En cours', color: 'info' },
    termine: { label: 'Termine', color: 'success' },
    bloque: { label: 'Bloque', color: 'danger' },
};

export const STATUT_PLAN = {
    propose: { label: 'Propose', color: 'info' },
    accepte_client: { label: 'Accepte', color: 'warning' },
    en_cours: { label: 'En cours', color: 'cyan' },
    cloture: { label: 'Cloture', color: 'success' },
    rejete: { label: 'Rejete', color: 'danger' },
};
