/**
 * API client pour la gestion des utilisateurs d'une entreprise par client_admin.
 */

import api, { getCsrfCookie } from '@/api/client';

export async function listClientUsers() {
    const r = await api.get('/client/users');
    return r.data;
}

export async function listClientPoles() {
    const r = await api.get('/client/poles');
    return r.data;
}

export async function listClientServices() {
    const r = await api.get('/client/services');
    return r.data;
}

export async function createClientUser(payload) {
    await getCsrfCookie();
    const r = await api.post('/client/users', payload);
    return r.data;
}

export async function updateClientUser(id, payload) {
    await getCsrfCookie();
    const r = await api.put(`/client/users/${id}`, payload);
    return r.data;
}

export async function resetClientUserPassword(id) {
    await getCsrfCookie();
    const r = await api.post(`/client/users/${id}/reset-password`);
    return r.data;
}

export async function deleteClientUser(id) {
    await getCsrfCookie();
    const r = await api.delete(`/client/users/${id}`);
    return r.data;
}

export async function changeTemporaryPassword(password, password_confirmation) {
    await getCsrfCookie();
    const r = await api.post('/user/change-temporary-password', { password, password_confirmation });
    return r.data;
}
