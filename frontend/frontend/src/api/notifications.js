/**
 * Client API — Notifications in-app (table laravel `notifications`).
 */

import api, { getCsrfCookie } from './client';

export async function listNotifications(perPage = 20) {
    const response = await api.get('/notifications', { params: { per_page: perPage } });
    return response.data;
}

export async function unreadNotificationsCount() {
    const response = await api.get('/notifications/unread-count');
    return response.data.count;
}

export async function marquerNotificationLue(id) {
    await getCsrfCookie();
    await api.post(`/notifications/${id}/marquer-lue`);
}

export async function marquerToutesNotificationsLues() {
    await getCsrfCookie();
    await api.post('/notifications/marquer-toutes-lues');
}

export async function supprimerNotification(id) {
    await getCsrfCookie();
    await api.delete(`/notifications/${id}`);
}
