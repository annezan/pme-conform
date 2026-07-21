/**
 * Client API — Formulaires (questionnaires) lies aux missions.
 * Source = 'manuel' (Methode 1, concu par l'agent) ou 'ia' (Methode 2).
 */

import api, { getCsrfCookie } from './client';

export async function listQuestionnairesParMission(missionId) {
    const r = await api.get(`/missions/${missionId}/questionnaires`);
    return r.data;
}

export async function listMesFormulaires() {
    const r = await api.get('/client/questionnaires');
    return r.data;
}

export async function getQuestionnaire(id) {
    const r = await api.get(`/questionnaires-generes/${id}`);
    return r.data;
}

export async function createQuestionnaire(missionId, payload) {
    await getCsrfCookie();
    const r = await api.post(`/missions/${missionId}/questionnaires`, payload);
    return r.data;
}

export async function updateQuestionnaire(id, payload) {
    await getCsrfCookie();
    const r = await api.put(`/questionnaires-generes/${id}`, payload);
    return r.data;
}

export async function enregistrerReponses(id, payload) {
    await getCsrfCookie();
    const r = await api.put(`/questionnaires-generes/${id}/reponses`, payload);
    return r.data;
}

export async function supprimerQuestionnaire(id) {
    await getCsrfCookie();
    const r = await api.delete(`/questionnaires-generes/${id}`);
    return r.data;
}

export async function getAuditFlashResultat(id) {
    const r = await api.get(`/questionnaires-generes/${id}/audit-flash-resultat`);
    return r.data;
}

// === Phase 4 : Publication + CRUD questions (ASC) ===

export async function publierQuestionnaire(id) {
    await getCsrfCookie();
    const r = await api.post(`/questionnaires-generes/${id}/publier`);
    return r.data;
}

export async function depublierQuestionnaire(id) {
    await getCsrfCookie();
    const r = await api.post(`/questionnaires-generes/${id}/depublier`);
    return r.data;
}

export async function ajouterQuestion(id, payload) {
    await getCsrfCookie();
    const r = await api.post(`/questionnaires-generes/${id}/questions`, payload);
    return r.data;
}

export async function modifierQuestion(id, numero, payload) {
    await getCsrfCookie();
    const r = await api.put(`/questionnaires-generes/${id}/questions/${numero}`, payload);
    return r.data;
}

export async function supprimerQuestion(id, numero) {
    await getCsrfCookie();
    const r = await api.delete(`/questionnaires-generes/${id}/questions/${numero}`);
    return r.data;
}

/**
 * Telecharge le PDF d'un questionnaire (rempli ou non).
 * Utilise responseType blob + ouverture d'un download dans le navigateur.
 */
export async function exporterQuestionnairePdf(id, fallbackName = 'questionnaire.pdf') {
    const r = await api.get(`/questionnaires-generes/${id}/export-pdf`, { responseType: 'blob' });
    // Le nom de fichier peut etre extrait du header content-disposition.
    let filename = fallbackName;
    const dispo = r.headers?.['content-disposition'] || r.headers?.get?.('content-disposition');
    if (dispo) {
        const m = /filename\*?=(?:UTF-8'')?["']?([^"';]+)/i.exec(dispo);
        if (m && m[1]) filename = decodeURIComponent(m[1]);
    }
    const blob = new Blob([r.data], { type: 'application/pdf' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 5000);
}

// === Etape 1 (Methode 2) : suivi de la generation IA des questionnaires ===

export async function suivreGenerationQuestionnaires(missionId) {
    const r = await api.get(`/missions/${missionId}/organigramme/generation`);
    return r.data;
}

// === Regeneration ciblee d'un questionnaire (asynchrone) ===

export async function regenererQuestionnaire(id) {
    await getCsrfCookie();
    const r = await api.post(`/questionnaires-generes/${id}/regenerer`);
    return r.data;
}

export async function suivreRegenerationQuestionnaire(id) {
    const r = await api.get(`/questionnaires-generes/${id}/regenerer/progress`);
    return r.data;
}

export async function regenererTousLesQuestionnaires(missionId) {
    await getCsrfCookie();
    const r = await api.post(`/missions/${missionId}/questionnaires/regenerer-tous`);
    return r.data;
}

export async function publierTousLesQuestionnaires(missionId) {
    await getCsrfCookie();
    const r = await api.post(`/missions/${missionId}/questionnaires/publier-tous`);
    return r.data;
}

export async function depublierTousLesQuestionnaires(missionId) {
    await getCsrfCookie();
    const r = await api.post(`/missions/${missionId}/questionnaires/depublier-tous`);
    return r.data;
}
