/**
 * MesUtilisateurs — Page client_admin pour gerer les utilisateurs de son entreprise.
 *
 * Permet de :
 *   - Lister les utilisateurs existants (avec leur pole, statut, derniere connexion)
 *   - Creer un nouveau user (mdp temporaire genere automatiquement + email)
 *   - Modifier les infos d'un user
 *   - Reinitialiser le mdp d'un user
 *   - Supprimer un user
 */

import { useEffect, useState } from 'react';
import { useAuth } from '@/contexts/AuthContext';
import {
    listClientUsers, listClientPoles, listClientServices,
    createClientUser, updateClientUser,
    resetClientUserPassword, deleteClientUser,
} from '@/api/clientUsers';
import { alertSuccess, alertError, confirmAction, confirmDelete } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import EmptyState from '@/components/ui/EmptyState';
import Modal from '@/components/ui/Modal';
import { Input, Select } from '@/components/ui/Input';
import {
    UsersIcon, UserPlusIcon, KeyIcon, PencilSquareIcon, TrashIcon,
    EnvelopeIcon, BuildingOffice2Icon, ClockIcon, CheckCircleIcon, ExclamationTriangleIcon,
} from '@heroicons/react/24/outline';

const FORM_INITIAL = {
    nom: '', prenom: '', email: '', telephone: '', poste: '',
    // Rattachement : SOIT 'pole' SOIT 'service'. Selectionne un seul champ.
    pole: '', service: '', serviceKey: '', // serviceKey = "pole||service" pour le select
    role: 'client',
};

export default function MesUtilisateurs() {
    const { user: connectedUser } = useAuth();
    const [users, setUsers] = useState([]);
    const [poles, setPoles] = useState([]);
    const [services, setServices] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [form, setForm] = useState(FORM_INITIAL);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState({});

    const charger = async () => {
        setLoading(true);
        try {
            const [resUsers, resPoles, resServices] = await Promise.all([
                listClientUsers(),
                listClientPoles(),
                listClientServices(),
            ]);
            setUsers(resUsers.data || []);
            setPoles(resPoles.data || []);
            setServices(resServices.data || []);
        } catch (err) {
            alertError(err.response?.data?.message || 'Impossible de charger les utilisateurs');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { charger(); }, []);

    const ouvrirCreation = () => {
        setEditingId(null);
        setForm(FORM_INITIAL);
        setErrors({});
        setShowModal(true);
    };

    const ouvrirEdition = (user) => {
        setEditingId(user.id);
        const serviceKey = user.service ? `${user.pole}||${user.service}` : '';
        setForm({
            nom: user.nom || '',
            prenom: user.prenom || '',
            email: user.email || '',
            telephone: user.telephone || '',
            poste: user.poste || '',
            // Si le user a un service, on pre-coche le select service ;
            // sinon on pre-coche le select pole.
            pole: user.service ? '' : (user.pole || ''),
            service: user.service || '',
            serviceKey,
            role: user.role || 'client',
        });
        setErrors({});
        setShowModal(true);
    };

    const enregistrer = async () => {
        // Garde-fou : SOIT pole, SOIT service, jamais les deux ni aucun.
        const aUnPole = !!form.pole;
        const aUnService = !!form.serviceKey;
        if (aUnPole && aUnService) {
            setErrors({ pole: 'Choisissez soit un pôle, soit un service — pas les deux.' });
            return;
        }
        if (!aUnPole && !aUnService) {
            setErrors({ pole: 'Vous devez choisir un pôle ou un service.' });
            return;
        }

        // Reconstitue le payload backend : on envoie toujours pole (+ service si scope)
        let polePayload = form.pole;
        let servicePayload = null;
        if (aUnService) {
            const [p, s] = form.serviceKey.split('||');
            polePayload = p;
            servicePayload = s;
        }

        setSaving(true);
        setErrors({});
        try {
            const base = {
                nom: form.nom, prenom: form.prenom, email: form.email,
                telephone: form.telephone, poste: form.poste,
                pole: polePayload, service: servicePayload,
                role: form.role,
            };
            if (editingId) {
                const { email: _, role: __, ...payload } = base;
                await updateClientUser(editingId, payload);
                alertSuccess('Utilisateur mis à jour.');
            } else {
                await createClientUser(base);
                alertSuccess('Utilisateur créé. Un e-mail avec ses identifiants temporaires lui a été envoyé.');
            }
            setShowModal(false);
            charger();
        } catch (err) {
            const respErrors = err.response?.data?.errors;
            if (respErrors) {
                const flat = {};
                Object.entries(respErrors).forEach(([k, msgs]) => { flat[k] = Array.isArray(msgs) ? msgs[0] : msgs; });
                setErrors(flat);
            }
            alertError(err.response?.data?.message || 'Enregistrement refusé');
        } finally {
            setSaving(false);
        }
    };

    const reset = async (user) => {
        const confirmed = await confirmAction(
            `Réinitialiser le mot de passe de ${user.prenom} ${user.nom} ? Un nouveau mot de passe temporaire lui sera envoyé par e-mail.`,
            'Réinitialiser le mot de passe'
        );
        if (!confirmed) return;
        try {
            await resetClientUserPassword(user.id);
            alertSuccess('Mot de passe réinitialisé. L\'utilisateur a reçu un e-mail.');
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Échec de la réinitialisation');
        }
    };

    const supprimer = async (user) => {
        if (!(await confirmDelete(`${user.prenom} ${user.nom}`))) return;
        try {
            await deleteClientUser(user.id);
            alertSuccess('Utilisateur supprimé.');
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Suppression refusée');
        }
    };

    return (
        <div className="p-6 lg:p-8 max-w-6xl mx-auto">
            <PageHeader
                title="Utilisateurs de mon entreprise"
                subtitle="Créez et gérez les comptes des collaborateurs qui accèdent à la plateforme. Chaque utilisateur est rattaché à un pôle unique."
                eyebrow="Espace client"
                icon={UsersIcon}
                accent="blue"
            >
                <Button onClick={ouvrirCreation}>
                    <UserPlusIcon className="w-4 h-4" />
                    Nouvel utilisateur
                </Button>
            </PageHeader>

            {loading && <p className="text-sm text-gray-500 px-2">Chargement...</p>}

            {!loading && users.length === 0 && (
                <EmptyState
                    icon={UsersIcon}
                    title="Aucun utilisateur"
                    description="Vous n'avez encore créé aucun collaborateur. Cliquez sur « Nouvel utilisateur » pour commencer."
                    accent="blue"
                />
            )}

            <div className="space-y-2">
                {users.map(user => (
                    <Card key={user.id} className="p-4">
                        <div className="flex flex-col lg:flex-row lg:items-center gap-4">
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-3 mb-1 flex-wrap">
                                    <div className="w-10 h-10 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold">
                                        {(user.prenom?.[0] || '?').toUpperCase()}{(user.nom?.[0] || '').toUpperCase()}
                                    </div>
                                    <div>
                                        <p className="font-semibold text-gray-900">{user.prenom} {user.nom}</p>
                                        <p className="text-xs text-gray-500 flex items-center gap-1">
                                            <EnvelopeIcon className="w-3.5 h-3.5" /> {user.email}
                                        </p>
                                    </div>
                                    {user.role && <Badge variant="gray">{user.role}</Badge>}
                                    {user.pole && (
                                        <Badge variant="info" dot title={user.service ? `Service : ${user.service}` : 'Responsable du pôle entier'}>
                                            <BuildingOffice2Icon className="w-3 h-3" />
                                            {user.pole}{user.service ? ` / ${user.service}` : ''}
                                        </Badge>
                                    )}
                                    {user.must_change_password && (
                                        <Badge variant="warning">
                                            <ExclamationTriangleIcon className="w-3 h-3" />
                                            Mdp temporaire
                                        </Badge>
                                    )}
                                    {!user.is_active && <Badge variant="danger">Désactivé</Badge>}
                                </div>
                                <div className="text-xs text-gray-500 flex items-center gap-3 mt-1 ml-13 pl-1 flex-wrap">
                                    {user.poste && <span>{user.poste}</span>}
                                    {user.derniere_connexion ? (
                                        <span className="flex items-center gap-1">
                                            <ClockIcon className="w-3 h-3" />
                                            Dernière connexion : {new Date(user.derniere_connexion).toLocaleString('fr-FR')}
                                        </span>
                                    ) : (
                                        <span className="flex items-center gap-1 text-gray-400">
                                            <ClockIcon className="w-3 h-3" /> Jamais connecté
                                        </span>
                                    )}
                                </div>
                            </div>

                            <div className="flex items-center gap-2 shrink-0">
                                <Button variant="secondary" size="sm" onClick={() => ouvrirEdition(user)}>
                                    <PencilSquareIcon className="w-4 h-4" />
                                </Button>
                                {/* Reset du mdp et suppression masques sur sa propre ligne :
                                    un user ne doit pas pouvoir supprimer son propre compte. */}
                                {user.id !== connectedUser?.id && (
                                    <>
                                        <Button variant="warning" size="sm" onClick={() => reset(user)} title="Réinitialiser le mot de passe">
                                            <KeyIcon className="w-4 h-4" />
                                        </Button>
                                        <Button variant="danger" size="sm" onClick={() => supprimer(user)} title="Supprimer le compte">
                                            <TrashIcon className="w-4 h-4" />
                                        </Button>
                                    </>
                                )}
                                {user.id === connectedUser?.id && (
                                    <span className="text-xs text-gray-400 italic px-2 select-none" title="Votre propre compte">
                                        (vous)
                                    </span>
                                )}
                            </div>
                        </div>
                    </Card>
                ))}
            </div>

            <Modal
                open={showModal}
                onClose={() => setShowModal(false)}
                title={editingId ? 'Modifier l\'utilisateur' : 'Créer un utilisateur'}
                icon={editingId ? PencilSquareIcon : UserPlusIcon}
                size="md"
            >
                <div className="space-y-3">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <Input label="Prénom" required value={form.prenom} onChange={e => setForm(f => ({ ...f, prenom: e.target.value }))} error={errors.prenom} />
                        <Input label="Nom" required value={form.nom} onChange={e => setForm(f => ({ ...f, nom: e.target.value }))} error={errors.nom} />
                    </div>
                    <Input
                        label="E-mail"
                        type="email"
                        required
                        value={form.email}
                        onChange={e => setForm(f => ({ ...f, email: e.target.value }))}
                        error={errors.email}
                        disabled={!!editingId}
                        helper={editingId ? "L'e-mail ne peut pas être modifié." : "L'utilisateur recevra ses identifiants à cette adresse."}
                    />
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <Input label="Téléphone" value={form.telephone} onChange={e => setForm(f => ({ ...f, telephone: e.target.value }))} />
                        <Input label="Poste" value={form.poste} onChange={e => setForm(f => ({ ...f, poste: e.target.value }))} />
                    </div>
                    {/* Rattachement : EXCLUSIF entre Pôle (responsable de tout le pôle)
                        et Service (responsable d'un service précis). Si l'un est rempli,
                        l'autre est désactivé. */}
                    <div className="space-y-2">
                        <p className="text-sm font-semibold text-gray-700">Rattachement <span className="text-red-500">*</span></p>
                        <p className="text-xs text-gray-500">
                            Choisissez <strong>soit</strong> un pôle (l'utilisateur supervisera tous les questionnaires de ce pôle),
                            <strong> soit</strong> un service (visibilité limitée à ce service). Pas les deux.
                        </p>
                        {errors.pole && <p className="text-xs text-red-600 font-medium">{errors.pole}</p>}

                        <Select
                            label="Pôle entier"
                            value={form.pole}
                            onChange={e => setForm(f => ({ ...f, pole: e.target.value, service: '', serviceKey: '' }))}
                            disabled={!!form.serviceKey}
                            helper={form.serviceKey ? "Désactivé car un service est déjà choisi ci-dessous." : "L'utilisateur verra tous les questionnaires des services de ce pôle."}
                        >
                            <option value="">— Sélectionner un pôle (responsable du pôle entier) —</option>
                            {poles.map(p => {
                                const prisParAutre = p.user_id && p.user_id !== editingId;
                                return (
                                    <option key={p.nom} value={p.nom} disabled={prisParAutre}>
                                        {p.nom}{prisParAutre ? ` — déjà attribué à ${p.user_libelle}` : ''}
                                    </option>
                                );
                            })}
                            {editingId && form.pole && !poles.some(p => p.nom === form.pole) && (
                                <option value={form.pole}>{form.pole} (legacy)</option>
                            )}
                        </Select>

                        <Select
                            label="Service spécifique"
                            value={form.serviceKey}
                            onChange={e => setForm(f => ({ ...f, serviceKey: e.target.value, pole: '' }))}
                            disabled={!!form.pole}
                            helper={
                                form.pole
                                    ? "Désactivé car un pôle entier est déjà choisi ci-dessus."
                                    : services.length === 0
                                        ? "Aucun service défini dans l'organigramme. Saisissez les services dans l'organigramme pour les voir apparaître."
                                        : "L'utilisateur ne verra que les questionnaires de ce service."
                            }
                        >
                            <option value="">— Sélectionner un service précis —</option>
                            {services.map(s => {
                                const cle = `${s.pole}||${s.service}`;
                                const prisParAutre = s.user_id && s.user_id !== editingId;
                                return (
                                    <option key={cle} value={cle} disabled={prisParAutre}>
                                        {s.libelle}{prisParAutre ? ` — déjà attribué à ${s.user_libelle}` : ''}
                                    </option>
                                );
                            })}
                            {editingId && form.serviceKey && !services.some(s => `${s.pole}||${s.service}` === form.serviceKey) && (
                                <option value={form.serviceKey}>{form.serviceKey.replace('||', ' / ')} (legacy)</option>
                            )}
                        </Select>
                    </div>
                    {!editingId && (
                        <Select label="Rôle" value={form.role} onChange={e => setForm(f => ({ ...f, role: e.target.value }))}>
                            <option value="client">Utilisateur standard (client)</option>
                            <option value="client_admin">Responsable entreprise (client_admin)</option>
                        </Select>
                    )}
                </div>

                <div className="flex justify-end gap-2 mt-5 pt-3 border-t border-gray-100">
                    <Button variant="ghost" onClick={() => setShowModal(false)} disabled={saving}>Annuler</Button>
                    <Button onClick={enregistrer} loading={saving}>
                        {saving ? 'Enregistrement...' : (editingId ? 'Enregistrer' : 'Créer et envoyer e-mail')}
                    </Button>
                </div>
            </Modal>
        </div>
    );
}
