/**
 * Page AdminUsers premium — DataTable + SweetAlert2.
 */

import { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '@/contexts/AuthContext';
import api, { getCsrfCookie } from '@/api/client';
import { alertSuccess, alertError, confirmDelete, confirmAction } from '@/utils/alerts';
import DataTableWrapper from '@/components/ui/DataTableWrapper';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import PageHeader from '@/components/ui/PageHeader';
import { Input, Select } from '@/components/ui/Input';
import TableActions, { DeleteAction, EditAction } from '@/components/ui/TableActions';
import Modal from '@/components/ui/Modal';
import { PlusIcon, XMarkIcon, UsersIcon, MagnifyingGlassIcon, UserPlusIcon, PencilSquareIcon } from '@heroicons/react/24/outline';

// API AUDREY : la creation/edition attend `role_id` (id du role) plutot que `role` (string).
const FORM_DEFAULT = { nom: '', prenom: '', email: '', role_id: '', poste: '', telephone: '', client_id: '' };

const ROLE_VARIANTS = {
    admin: 'danger',
    manager: 'warning',
    consultant: 'info',
    client_admin: 'purple',
    client: 'cyan',
};

export default function AdminUsers() {
    const navigate = useNavigate();
    const { user: connectedUser } = useAuth();
    const [users, setUsers] = useState([]);
    const [clients, setClients] = useState([]);
    const [roles, setRoles] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [editingUser, setEditingUser] = useState(null); // null = creation ; user = edition
    const [form, setForm] = useState(FORM_DEFAULT);
    const [saving, setSaving] = useState(false);
    const [filterText, setFilterText] = useState('');

    const labelRole = (name) => roles.find(r => r.name === name)?.name || name;
    const nomRoleId = (id) => roles.find(r => String(r.id) === String(id))?.name || '';

    const charger = () => {
        api.get('/admin/users?per_page=100').then(r => setUsers(r.data.data || [])).finally(() => setLoading(false));
    };
    useEffect(() => {
        charger();
        api.get('/clients?per_page=200').then(r => setClients(r.data.data || [])).catch(() => setClients([]));
        // API AUDREY : /admin/roles-liste retourne [{id, name, description}]
        api.get('/admin/roles-liste').then(r => setRoles(r.data.data || [])).catch(() => setRoles([]));
    }, []);

    const fermerModal = () => {
        setShowModal(false);
        setEditingUser(null);
        setForm(FORM_DEFAULT);
    };

    const ouvrirCreation = () => {
        setEditingUser(null);
        setForm(FORM_DEFAULT);
        setShowModal(true);
    };

    const ouvrirEdition = (user) => {
        setEditingUser(user);
        // AUDREY renvoie un role singulier {id, name} via belongsTo ; on tente plusieurs shapes par compatibilite.
        const roleId = user.role_id ?? user.role?.id ?? user.roles?.[0]?.id ?? '';
        setForm({
            nom: user.nom || '',
            prenom: user.prenom || '',
            email: user.email || '',
            role_id: roleId ? String(roleId) : '',
            poste: user.poste || '',
            telephone: user.telephone || '',
            client_id: user.clients?.[0]?.id ? String(user.clients[0].id) : '',
        });
        setShowModal(true);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        const roleName = nomRoleId(form.role_id);
        if (['client', 'client_admin'].includes(roleName) && !form.client_id) {
            alertError('Veuillez sélectionner une entreprise pour un utilisateur client/client_admin.');
            return;
        }
        if (!form.role_id) {
            alertError('Veuillez sélectionner un rôle.');
            return;
        }
        setSaving(true);
        try {
            await getCsrfCookie();
            const payload = { ...form, role_id: parseInt(form.role_id, 10) };

            if (payload.client_id) {
                payload.client_id = parseInt(payload.client_id);
            } else if (editingUser) {
                // En edition : passer null pour detacher
                payload.client_id = null;
            } else {
                delete payload.client_id;
            }

            if (editingUser) {
                // L'update n'accepte pas `password` ni `is_active` — on les retire avant l'envoi.
                delete payload.password;
                delete payload.is_active;
                await api.put(`/admin/users/${editingUser.id}`, payload);
                alertSuccess('Utilisateur mis à jour');
            } else {
                // Le backend genere lui-meme le mdp temporaire et envoie l'email,
                // on ne transmet pas le champ password (qui est vide dans le form).
                delete payload.password;
                await api.post('/admin/users', payload);
                alertSuccess('Utilisateur créé. Un e-mail avec ses identifiants temporaires lui a été envoyé.');
            }
            fermerModal();
            charger();
        } catch (err) {
            alertError(err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(' ') : (err.response?.data?.message || 'Erreur'));
        } finally {
            setSaving(false);
        }
    };

    const handleToggleActive = async (user) => {
        const actionLabel = user.is_active ? 'désactiver' : 'activer';
        const confirmed = await confirmAction(
            user.is_active
                ? `Désactiver le compte de ${user.prenom} ${user.nom} ? L'utilisateur ne pourra plus se connecter.`
                : `Activer le compte de ${user.prenom} ${user.nom} ? L'utilisateur pourra se connecter à nouveau.`,
            actionLabel.charAt(0).toUpperCase() + actionLabel.slice(1)
        );
        if (!confirmed) return;
        try {
            await getCsrfCookie();
            const r = await api.patch(`/admin/users/${user.id}/toggle-active`);
            alertSuccess(r.data?.message || 'Statut modifié');
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur lors de la modification du statut');
        }
    };

    const handleDelete = async (user) => {
        if (await confirmDelete(`${user.prenom} ${user.nom}`)) {
            try {
                await getCsrfCookie();
                await api.delete(`/admin/users/${user.id}`);
                alertSuccess('Utilisateur supprimé');
                charger();
            } catch (err) {
                alertError(err.response?.data?.message || 'Erreur');
            }
        }
    };

    const columns = useMemo(() => [
        {
            name: 'Utilisateur', grow: 2, sortable: true, selector: row => `${row.prenom} ${row.nom}`,
            cell: row => (
                <div className="flex items-center gap-3 py-1">
                    <div className="w-9 h-9 rounded-full bg-linear-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold text-xs shadow-sm shrink-0">
                        {row.prenom?.charAt(0)}{row.nom?.charAt(0)}
                    </div>
                    <div>
                        <p className="font-medium text-gray-900 text-sm">{row.prenom} {row.nom}</p>
                        <p className="text-xs text-gray-500">{row.email}</p>
                    </div>
                </div>
            ),
        },
        { name: 'Poste', selector: row => row.poste || '-' },
        {
            name: 'Entreprise',
            cell: row => {
                const c = row.clients?.[0];
                return c ? <span className="text-xs text-gray-700">{c.raison_sociale}</span> : <span className="text-xs text-gray-400">-</span>;
            },
        },
        {
            name: 'Rôle',
            width: '180px',
            cell: row => {
                // AUDREY : `role` singulier ({id, name}) ; Spatie : `roles[0]` ; fallback string.
                const roleName = row.role?.name || (typeof row.role === 'string' ? row.role : null) || row.roles?.[0]?.name;
                if (!roleName) return <span className="text-xs text-gray-400">-</span>;
                return <Badge variant={ROLE_VARIANTS[roleName] || 'gray'}>{labelRole(roleName)}</Badge>;
            },
        },
        {
            name: 'Statut', width: '140px',
            cell: row => {
                // 3 etats possibles :
                //  - En attente : compte cree via /inscription, pas encore valide par ASC
                //  - Actif : valide ET activable (peut se connecter)
                //  - Inactif : valide mais desactive manuellement par admin
                if (!row.compte_valide) {
                    return (
                        <Badge variant="warning" dot title="Compte en attente de validation par AS Consulting. À valider via /admin/comptes-en-attente.">
                            En attente
                        </Badge>
                    );
                }
                // Sur sa propre ligne : badge non cliquable pour eviter l'auto-desactivation.
                const estSoiMeme = row.id === connectedUser?.id;
                if (estSoiMeme) {
                    return (
                        <Badge variant={row.is_active ? 'success' : 'danger'} title="Votre propre compte ne peut pas être désactivé.">
                            {row.is_active ? 'Actif' : 'Inactif'}
                        </Badge>
                    );
                }
                return (
                    <button
                        onClick={() => handleToggleActive(row)}
                        title={row.is_active ? 'Cliquer pour désactiver' : 'Cliquer pour activer'}
                        className="cursor-pointer"
                    >
                        <Badge variant={row.is_active ? 'success' : 'danger'}>
                            {row.is_active ? 'Actif' : 'Inactif'}
                        </Badge>
                    </button>
                );
            },
        },
        {
            name: 'Actions', width: '160px', right: true,
            cell: row => {
                // Empeche un utilisateur de supprimer ou desactiver son PROPRE compte
                // (il pourrait s'enfermer dehors). L'edition reste autorisee pour
                // pouvoir modifier ses infos personnelles depuis la liste.
                const estSoiMeme = row.id === connectedUser?.id;
                return (
                    <TableActions>
                        {!estSoiMeme && (
                            <>
                                {row.compte_valide ? (
                                    <button
                                        onClick={() => handleToggleActive(row)}
                                        title={row.is_active ? 'Désactiver le compte' : 'Activer le compte'}
                                        className={`p-1.5 rounded transition-colors ${
                                            row.is_active
                                                ? 'text-amber-600 hover:bg-amber-50'
                                                : 'text-emerald-600 hover:bg-emerald-50'
                                        }`}
                                    >
                                        {row.is_active ? (
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728" /></svg>
                                        ) : (
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
                                        )}
                                    </button>
                                ) : (
                                    <button
                                        onClick={() => navigate('/admin/comptes-en-attente')}
                                        title="Compte en attente de validation. Cliquer pour aller à la page de validation."
                                        className="p-1.5 rounded text-amber-600 hover:bg-amber-50"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </button>
                                )}
                            </>
                        )}
                        <EditAction onClick={() => ouvrirEdition(row)} />
                        {!estSoiMeme && (
                            <DeleteAction onClick={() => handleDelete(row)} />
                        )}
                        {estSoiMeme && (
                            <span
                                className="text-xs text-gray-400 italic ml-1 select-none"
                                title="Votre propre compte : suppression et désactivation indisponibles."
                            >
                                (vous)
                            </span>
                        )}
                    </TableActions>
                );
            },
        },
    ], [clients]);

    const filteredData = users.filter(u =>
        `${u.prenom} ${u.nom}`.toLowerCase().includes(filterText.toLowerCase()) ||
        u.email?.toLowerCase().includes(filterText.toLowerCase())
    );

    return (
        <div className="p-6 lg:p-8 max-w-7xl mx-auto">
            <PageHeader
                title="Utilisateurs"
                subtitle="Gestion des comptes utilisateurs de la plateforme"
                eyebrow="Administration"
                icon={UsersIcon}
                accent="indigo"
            >
                <Button onClick={ouvrirCreation} variant="primary">
                    <PlusIcon className="w-4 h-4" /> Nouvel utilisateur
                </Button>
            </PageHeader>

            <Card variant="elevated" className="overflow-hidden">
                <div className="px-5 py-4 border-b border-gray-100/80 bg-gray-50/40 flex items-center justify-between gap-3 flex-wrap">
                    <p className="text-sm text-gray-600">
                        <span className="font-bold text-gray-900">{filteredData.length}</span> utilisateur{filteredData.length > 1 ? 's' : ''}
                    </p>
                    <Input
                        icon={MagnifyingGlassIcon}
                        value={filterText}
                        onChange={e => setFilterText(e.target.value)}
                        placeholder="Rechercher un utilisateur..."
                        className="w-72"
                    />
                </div>
                <DataTableWrapper columns={columns} data={filteredData} loading={loading} />
            </Card>

            <Modal
                open={showModal}
                onClose={fermerModal}
                title={editingUser ? `Modifier ${editingUser.prenom} ${editingUser.nom}` : 'Nouvel utilisateur'}
                subtitle={editingUser ? 'Mettre à jour les informations du compte' : 'Créer un nouveau compte utilisateur'}
                icon={editingUser ? PencilSquareIcon : UserPlusIcon}
                accent="indigo"
                size="md"
                footer={(
                    <>
                        <Button variant="secondary" type="button" onClick={fermerModal}>Annuler</Button>
                        <Button variant="primary" type="submit" form="user-form" loading={saving}>
                            {saving
                                ? (editingUser ? 'Enregistrement...' : 'Création...')
                                : (editingUser ? 'Enregistrer' : 'Créer')}
                        </Button>
                    </>
                )}
            >
                <form id="user-form" onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <Input label="Prénom *" value={form.prenom} onChange={e => setForm({...form, prenom: e.target.value})} required />
                                <Input label="Nom *" value={form.nom} onChange={e => setForm({...form, nom: e.target.value})} required />
                            </div>
                            <Input label="Email *" type="email" value={form.email} onChange={e => setForm({...form, email: e.target.value})} required />
                            {/* Pas de saisie de mot de passe : le backend genere un mdp
                                temporaire securise et l'envoie par email a l'utilisateur. */}
                            {!editingUser && (
                                <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-900 flex items-start gap-2">
                                    <svg className="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                                    <div>
                                        <p className="font-semibold mb-0.5">Mot de passe automatique</p>
                                        <p>Un mot de passe temporaire sera généré automatiquement et envoyé à l'utilisateur par e-mail. Il devra le modifier lors de sa première connexion.</p>
                                    </div>
                                </div>
                            )}
                            <div className="grid grid-cols-2 gap-4">
                                <Input label="Poste" value={form.poste} onChange={e => setForm({...form, poste: e.target.value})} placeholder="Ex: Consultant senior" />
                                <Input label="Téléphone" value={form.telephone} onChange={e => setForm({...form, telephone: e.target.value})} placeholder="+225 XX XX XX XX" />
                            </div>
                            <Select
                                label="Rôle *"
                                value={form.role_id}
                                onChange={e => {
                                    const nouvelId = e.target.value;
                                    const nouveauNom = nomRoleId(nouvelId);
                                    setForm({
                                        ...form,
                                        role_id: nouvelId,
                                        client_id: ['client','client_admin'].includes(nouveauNom) ? form.client_id : '',
                                    });
                                }}
                                required
                            >
                                <option value="">-- Sélectionner un rôle --</option>
                                {roles.map(r => (
                                    <option key={r.id} value={r.id}>{r.name}</option>
                                ))}
                            </Select>

                            {/* Champ Entreprise : visible et obligatoire si role=client ou client_admin */}
                            {(() => {
                                const roleNomCourant = nomRoleId(form.role_id);
                                return (roleNomCourant === 'client' || roleNomCourant === 'client_admin');
                            })() && (
                                <div>
                                    <Select
                                        label="Entreprise rattachée *"
                                        value={form.client_id}
                                        onChange={e => setForm({...form, client_id: e.target.value})}
                                        required
                                    >
                                        <option value="">-- Sélectionner une entreprise --</option>
                                        {clients.map(c => {
                                            // AUDREY : secteursActivite [{id, nom}] ; legacy : secteur_activite string.
                                            const noms = (c.secteursActivite || []).map(s => s.nom).filter(Boolean);
                                            const secteurTexte = noms.length ? noms.join(', ') : c.secteur_activite;
                                            return (
                                                <option key={c.id} value={c.id}>
                                                    {c.raison_sociale}{secteurTexte ? ` — ${secteurTexte}` : ''}
                                                </option>
                                            );
                                        })}
                                    </Select>
                                    <p className="text-xs text-gray-500 mt-1">
                                        L'utilisateur aura accès à l'espace documentaire de cette entreprise.
                                    </p>
                                    {clients.length === 0 && (
                                        <p className="text-xs text-amber-600 mt-1">
                                            Aucune entreprise existante. Créez-en d'abord dans « Clients ».
                                        </p>
                                    )}
                                </div>
                            )}

                </form>
            </Modal>
        </div>
    );
}
