/**
 * Client API — Audit Flash en self-service (Methode 3 libre).
 * Un client peut lancer son propre Audit Flash sans mission ; l'admin
 * AS Consulting peut visualiser les resultats de tous les clients.
 */

import api, { getCsrfCookie } from './client';

export async function chargerAuditFlashClient() {
    const r = await api.get('/client/audit-flash');
    return r.data;
}

export async function reinitialiserAuditFlashClient() {
    await getCsrfCookie();
    const r = await api.post('/client/audit-flash/reset');
    return r.data;
}

export async function listAuditFlashLibresAdmin() {
    const r = await api.get('/admin/audit-flash-libres');
    return r.data;
}
