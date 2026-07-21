/**
 * Client API — Portefeuille ASC (vue 360 des clients).
 */

import api from './client';

export async function listPortefeuille(params = {}) {
    const response = await api.get('/asc/portefeuille', { params });
    return response.data;
}

export async function getVueClient(clientId) {
    const response = await api.get(`/asc/portefeuille/${clientId}`);
    return response.data;
}
