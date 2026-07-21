/**
 * Client API — Traitements de donnees (PME-CONFORM).
 */

import api, { getCsrfCookie } from './client';

export async function listTraitements(params = {}) {
    const response = await api.get('/traitements', { params });
    return response.data;
}

export async function getTraitement(id) {
    const response = await api.get(`/traitements/${id}`);
    return response.data;
}

/**
 * Récupère les données pré-remplies pour un nouveau traitement
 * (client_id, direction_pole, etc.) à partir du profil de l'utilisateur connecté.
 * Phase 5.
 */
export async function getPreremplissageTraitement() {
    const response = await api.get('/traitements/preremplir');
    return response.data;
}

export async function creerTraitement(data) {
    await getCsrfCookie();
    const response = await api.post('/traitements', data);
    return response.data;
}

export async function creerTraitementsDepuisQuestionnaires(clientId) {
    await getCsrfCookie();
    const response = await api.post('/traitements/creer-depuis-questionnaires', { client_id: clientId });
    return response.data;
}

export async function majTraitement(id, data) {
    await getCsrfCookie();
    const response = await api.put(`/traitements/${id}`, data);
    return response.data;
}

export async function validerTraitement(id) {
    await getCsrfCookie();
    const response = await api.post(`/traitements/${id}/valider`);
    return response.data;
}

export async function archiverTraitement(id, commentaire) {
    await getCsrfCookie();
    const response = await api.post(`/traitements/${id}/archiver`, { commentaire });
    return response.data;
}

export async function supprimerTraitement(id) {
    await getCsrfCookie();
    const response = await api.delete(`/traitements/${id}`);
    return response.data;
}

export async function getHistoriqueTraitement(id) {
    const response = await api.get(`/traitements/${id}/historique`);
    return response.data;
}

/**
 * Constantes partagees (miroir des enums backend).
 */
export const BASES_LEGALES = [
    { value: 'consentement', label: 'Consentement' },
    { value: 'contrat', label: 'Execution d\'un contrat' },
    { value: 'obligation_legale', label: 'Obligation legale' },
    { value: 'interet_legitime', label: 'Interet legitime' },
    { value: 'mission_interet_public', label: 'Mission d\'interet public' },
    { value: 'sauvegarde', label: 'Sauvegarde des interets vitaux' },
];

export const CATEGORIES_PERSONNES = [
    'salaries', 'clients', 'prospects', 'fournisseurs', 'partenaires',
    'visiteurs', 'candidats', 'mineurs', 'patients', 'electeurs', 'usagers',
];

export const CATEGORIES_DONNEES = [
    'identite', 'contact', 'etat_civil', 'banque', 'fiscales',
    'contrat', 'formation', 'carriere', 'localisation', 'connexion',
    'photo', 'biometrique', 'sante', 'opinions', 'affiliation_syndicale',
    'infractions', 'mineur',
];

export const MESURES_TECHNIQUES = [
    'chiffrement_repos', 'chiffrement_transit', 'auth_mfa', 'controle_acces_rbac',
    'logs_acces', 'sauvegardes_regulieres', 'anti_virus', 'pare_feu',
    'segmentation_reseau', 'detection_intrusions', 'gestion_vulnerabilites',
];

export const MESURES_ORGANISATIONNELLES = [
    'sensibilisation_salaries', 'charte_confidentialite', 'procedure_incidents',
    'contrats_sous_traitants_dpa', 'dpo_designe', 'registre_tenu',
    'aipd_realisee', 'revue_acces_periodique', 'plan_continuite',
];
