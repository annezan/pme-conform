/**
 * Client API — Analyses d'ecarts de conformite.
 */

import api, { getCsrfCookie } from './client';

export async function listAnalyses(params = {}) {
    const response = await api.get('/analyses', { params });
    return response.data;
}

export async function getAnalyse(id) {
    const response = await api.get(`/analyses/${id}`);
    return response.data;
}

export async function lancerAnalyse(payload) {
    await getCsrfCookie();
    const response = await api.post('/analyses', payload);
    return response.data;
}

export async function deleteAnalyse(id) {
    await getCsrfCookie();
    const response = await api.delete(`/analyses/${id}`);
    return response.data;
}

export async function regenererRapport(id) {
    await getCsrfCookie();
    const response = await api.post(`/analyses/${id}/regenerer-rapport`);
    return response.data;
}

export async function annulerAnalyse(id) {
    await getCsrfCookie();
    const response = await api.post(`/analyses/${id}/annuler`);
    return response.data;
}

export async function relancerAnalyse(id, enrichissementIa = null) {
    await getCsrfCookie();
    // enrichissementIa : null = on garde le mode actuel ; true/false = on force.
    const body = enrichissementIa === null ? {} : { enrichissement_ia: enrichissementIa };
    const response = await api.post(`/analyses/${id}/relancer`, body);
    return response.data;
}

export async function refaireAnalyse(id, motif = null) {
    await getCsrfCookie();
    const response = await api.post(`/analyses/${id}/refaire`, motif ? { motif } : {});
    return response.data;
}

export async function listVersionsAnalyse(id) {
    const response = await api.get(`/analyses/${id}/versions`);
    return response.data;
}

export async function getVersionAnalyse(analyseId, versionId) {
    const response = await api.get(`/analyses/${analyseId}/versions/${versionId}`);
    return response.data;
}

export async function enrichirAnalyseIA(id) {
    await getCsrfCookie();
    const response = await api.post(`/analyses/${id}/enrichir`);
    return response.data;
}

export async function annulerEnrichissementIA(id) {
    await getCsrfCookie();
    const response = await api.post(`/analyses/${id}/annuler-enrichissement`);
    return response.data;
}

export async function telechargerRapport(analyse) {
    const response = await api.get(`/analyses/${analyse.id}/rapport`, {
        responseType: 'blob',
    });
    const url = window.URL.createObjectURL(response.data);
    const a = document.createElement('a');
    a.href = url;
    const extFromPath = (analyse.rapport_word_path || '').split('.').pop();
    const ext = ['pptx', 'docx'].includes(extFromPath) ? extFromPath : 'pptx';
    a.download = `rapport-risques-${analyse.reference}.${ext}`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
}
