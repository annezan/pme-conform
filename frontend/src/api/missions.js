/**
 * Client API — Missions et affectations consultants.
 */

import api, { getCsrfCookie } from './client';

export async function getMission(id) {
    const response = await api.get(`/missions/${id}`);
    return response.data;
}

// Consultants affectes a la mission (pivot mission_user)

export async function listConsultants(missionId) {
    const response = await api.get(`/missions/${missionId}/consultants`);
    return response.data.consultants;
}

export async function listCandidatsConsultants(missionId) {
    const response = await api.get(`/missions/${missionId}/consultants/candidats`);
    return response.data.candidats;
}

export async function attacherConsultants(missionId, userIds) {
    await getCsrfCookie();
    const response = await api.post(`/missions/${missionId}/consultants`, { user_ids: userIds });
    return response.data;
}

export async function detacherConsultant(missionId, userId) {
    await getCsrfCookie();
    const response = await api.delete(`/missions/${missionId}/consultants/${userId}`);
    return response.data;
}
