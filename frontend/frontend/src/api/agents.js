/**
 * Client API — Agents IA.
 */

import api from './client';

export async function getAgents() {
    const response = await api.get('/agents');
    return response.data.agents;
}

export async function getAgent(slug) {
    const response = await api.get(`/agents/${slug}`);
    return response.data;
}
