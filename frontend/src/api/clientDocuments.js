/**
 * Client API — Documents de l'espace client (role client).
 */

import api, { getCsrfCookie } from './client';

export async function initialiserEspaceClient(data) {
    await getCsrfCookie();
    const response = await api.post('/client/initialiser', data);
    return response.data;
}

export async function listMesDocuments() {
    const response = await api.get('/client/documents');
    return response.data;
}

export async function uploaderMonDocument(formData) {
    await getCsrfCookie();
    const response = await api.post('/client/documents', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
    return response.data;
}

export async function getMonDocument(id) {
    const response = await api.get(`/client/documents/${id}`);
    return response.data;
}

export async function supprimerMonDocument(id) {
    await getCsrfCookie();
    const response = await api.delete(`/client/documents/${id}`);
    return response.data;
}

/**
 * Documents d'un client precis (utilise par le stepper d'analyse).
 * Necessite role consultant/manager/admin.
 */
export async function listDocumentsDuClient(clientId) {
    const response = await api.get(`/clients/${clientId}/documents`);
    return response.data;
}
