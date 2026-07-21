/**
 * Client API — Documents (upload et generation).
 */

import api, { getCsrfCookie } from './client';

export async function uploadDocument(formData) {
    await getCsrfCookie();
    const response = await api.post('/documents/upload', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
    return response.data;
}

export async function generateDocument(agentId, typeDocument, contexte, missionId = null) {
    await getCsrfCookie();
    const response = await api.post('/documents/generer', {
        agent_id: agentId,
        type_document: typeDocument,
        contexte,
        mission_id: missionId,
    });
    return response.data;
}

export async function downloadGenerated(messageId) {
    const response = await api.get(`/documents/generer/${messageId}/download`, {
        responseType: 'blob',
    });
    // Declencher le telechargement
    const url = window.URL.createObjectURL(response.data);
    const a = document.createElement('a');
    a.href = url;
    a.download = `document-genere-${messageId}.md`;
    a.click();
    window.URL.revokeObjectURL(url);
}
