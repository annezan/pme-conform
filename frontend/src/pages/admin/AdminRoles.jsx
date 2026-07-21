/**
 * Page AdminRoles — CRUD des roles + permissions (API AUDREY).
 *
 * Endpoints utilises :
 *  - GET    /admin/roles                          (paginé : per_page, search, active_only)
 *  - POST   /admin/roles                          (name, description, is_active, permissions: [ids])
 *  - GET    /admin/roles/{role}                   (avec permissions)
 *  - PUT    /admin/roles/{role}                   (sync permissions inclus)
 *  - DELETE /admin/roles/{role}                   (422 si attribue a des users)
 *  - PATCH  /admin/roles/{role}/toggle-active
 *  - GET    /admin/permissions                    (paginé : per_page, group, search)
 *
 * NB : a la difference de l'ancienne API, les permissions sont referencees par ID
 * (et non par name), et le role n'a pas de "libelle" — uniquement name + description.
 */

import { useState, useEffect, useMemo } from 'react';
import api, { getCsrfCookie } from '@/api/client';
import { alertSuccess, alertError, confirmDelete, confirmAction } from '@/utils/alerts';
import DataTableWrapper from '@/components/ui/DataTableWrapper';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import PageHeader from '@/components/ui/PageHeader';
import { Input, Textarea } from '@/components/ui/Input';
import TableActions, { DeleteAction, ToggleAction, EditAction } from '@/components/ui/TableActions';
import Modal from '@/components/ui/Modal';
import { PlusIcon, XMarkIcon, KeyIcon, MagnifyingGlassIcon, PencilSquareIcon } from '@heroicons/react/24/outline';

const FORM_DEFAULT = { name: '', description: '', is_active: true, permissions: [] };

export default function AdminRoles() {
    const [roles, setRoles] = useState([]);
    const [permissions, setPermissions] = useState([]); // [{id, name, group, is_active}]
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState(FORM_DEFAULT);
    const [saving, setSaving] = useState(false);
    const [filterText, setFilterText] = useState('');

    const charger = () => {
        setLoading(true);
        api.get('/admin/roles', { params: { per_page: 100, active_only: false } })
            .then(r => setRoles(r.data.data || []))
            .catch(() => alertError('Impossible de charger les rôles'))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        charger();
        // Toutes les permissions actives (paginé 200 — largement suffisant)
        api.get('/admin/permissions', { params: { per_page: 200, active_only: true } })
            .then(r => setPermissions(r.data.data || []))
            .catch(() => setPermissions([]));
    }, []);

    // Permissions regroupees par "group" pour l'affichage dans le modal
    const permissionsGroupees = useMemo(() => {
        const map = new Map();
        permissions.forEach(p => {
            const g = p.group || 'autres';
            if (!map.has(g)) map.set(g, []);
            map.get(g).push(p);
        });
        return Array.from(map.entries())
            .sort(([a], [b]) => a.localeCompare(b))
            .map(([groupe, perms]) => ({ groupe, permissions: perms }));
    }, [permissions]);

    const fermerModal = () => {
        setShowModal(false);
        setEditing(null);
        setForm(FORM_DEFAULT);
    };

    const ouvrirCreation = () => {
        setEditing(null);
        setForm(FORM_DEFAULT);
        setShowModal(true);
    };

    const ouvrirEdition = async (role) => {
        try {
            const r = await api.get(`/admin/roles/${role.id}`);
            const full = r.data.data;
            setEditing(full);
            setForm({
                name: full.name || '',
                description: full.description || '',
                is_active: full.is_active !== false,
                permissions: (full.permissions || []).map(p => p.id),
            });
            setShowModal(true);
        } catch {
            alertError('Impossible de charger le rôle');
        }
    };

    const togglePermission = (id) => {
        setForm(f => ({
            ...f,
            permissions: f.permissions.includes(id)
                ? f.permissions.filter(p => p !== id)
                : [...f.permissions, id],
        }));
    };

    const toggleGroupe = (groupe) => {
        const ids = (groupe.permissions || []).map(p => p.id);
        const tousCoches = ids.every(id => form.permissions.includes(id));
        setForm(f => ({
            ...f,
            permissions: tousCoches
                ? f.permissions.filter(p => !ids.includes(p))
                : [...new Set([...f.permissions, ...ids])],
        }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            await getCsrfCookie();
            const payload = {
                name: form.name.trim(),
                description: form.description || null,
                is_active: !!form.is_active,
                permissions: form.permissions,
            };
            if (editing) {
                await api.put(`/admin/roles/${editing.id}`, payload);
                alertSuccess('Rôle mis à jour');
            } else {
                await api.post('/admin/roles', payload);
                alertSuccess('Rôle créé');
            }
            fermerModal();
            charger();
        } catch (err) {
            alertError(err.response?.data?.errors
                ? Object.values(err.response.data.errors).flat().join(' ')
                : (err.response?.data?.message || 'Erreur'));
        } finally {
            setSaving(false);
        }
    };

    const toggleActive = async (role) => {
        const action = role.is_active ? 'désactiver' : 'activer';
        if (await confirmAction(`Voulez-vous ${action} le rôle "${role.name}" ?`)) {
            try {
                await getCsrfCookie();
                await api.patch(`/admin/roles/${role.id}/toggle-active`);
                alertSuccess(`Rôle ${action === 'activer' ? 'activé' : 'désactivé'}`);
                charger();
            } catch {
                alertError('Erreur');
            }
        }
    };

    const handleDelete = async (role) => {
        if (await confirmDelete(role.name)) {
            try {
                await getCsrfCookie();
                await api.delete(`/admin/roles/${role.id}`);
                alertSuccess('Rôle supprimé');
                charger();
            } catch (err) {
                alertError(err.response?.data?.message || 'Erreur');
            }
        }
    };

    const columns = useMemo(() => [
        {
            name: 'Nom', grow: 2, sortable: true,
            selector: row => row.name,
            cell: row => (
                <div className="py-1">
                    <p className="font-medium text-gray-900 text-sm">{row.name}</p>
                </div>
            ),
        },
        {
            name: 'Description', grow: 3,
            selector: row => row.description || '-',
            cell: row => <span className="text-xs text-gray-600 line-clamp-2">{row.description || '-'}</span>,
        },
        {
            name: 'Permissions', width: '130px',
            cell: row => <Badge variant="purple">{(row.permissions || []).length}</Badge>,
        },
        {
            name: 'Statut', width: '110px',
            cell: row => <Badge variant={row.is_active ? 'success' : 'gray'}>{row.is_active ? 'Actif' : 'Inactif'}</Badge>,
        },
        {
            name: 'Actions', width: '160px', right: true,
            cell: row => (
                <TableActions>
                    <EditAction onClick={() => ouvrirEdition(row)} />
                    <ToggleAction onClick={() => toggleActive(row)} active={row.is_active} label={row.is_active ? 'Désactiver' : 'Activer'} />
                    <DeleteAction onClick={() => handleDelete(row)} />
                </TableActions>
            ),
        },
    ], []);

    const filtered = roles.filter(r =>
        (r.name || '').toLowerCase().includes(filterText.toLowerCase()) ||
        (r.description || '').toLowerCase().includes(filterText.toLowerCase())
    );

    return (
        <div className="p-6 lg:p-8 max-w-7xl mx-auto">
            <PageHeader
                title="Rôles"
                subtitle="Gérez les rôles et leurs permissions"
                eyebrow="Administration"
                icon={KeyIcon}
                accent="indigo"
            >
                <Button onClick={ouvrirCreation} variant="primary">
                    <PlusIcon className="w-4 h-4" /> Nouveau rôle
                </Button>
            </PageHeader>

            <Card variant="elevated" className="overflow-hidden">
                <div className="px-5 py-4 border-b border-gray-100/80 bg-gray-50/40 flex items-center justify-between gap-3 flex-wrap">
                    <p className="text-sm text-gray-600">
                        <span className="font-bold text-gray-900">{filtered.length}</span> rôle{filtered.length > 1 ? 's' : ''}
                    </p>
                    <Input
                        icon={MagnifyingGlassIcon}
                        value={filterText}
                        onChange={e => setFilterText(e.target.value)}
                        placeholder="Rechercher un rôle..."
                        className="w-72"
                    />
                </div>
                <DataTableWrapper columns={columns} data={filtered} loading={loading} />
            </Card>

            <Modal
                open={showModal}
                onClose={fermerModal}
                title={editing ? `Modifier "${editing.name}"` : 'Nouveau rôle'}
                subtitle={editing ? 'Modifier le périmètre et les permissions' : 'Définir un nouveau rôle et ses permissions'}
                icon={editing ? PencilSquareIcon : KeyIcon}
                accent="indigo"
                size="xl"
                footer={(
                    <>
                        <Button variant="secondary" type="button" onClick={fermerModal}>Annuler</Button>
                        <Button variant="primary" type="submit" form="role-form" loading={saving}>
                            {saving ? (editing ? 'Enregistrement...' : 'Création...') : (editing ? 'Enregistrer' : 'Enregistrer')}
                        </Button>
                    </>
                )}
            >
                <form id="role-form" onSubmit={handleSubmit} className="space-y-4">
                            <Input
                                label="Nom *"
                                value={form.name}
                                onChange={e => setForm({...form, name: e.target.value})}
                                required
                                placeholder="Ex: auditeur_externe"
                            />
                            <Textarea
                                label="Description"
                                value={form.description}
                                onChange={e => setForm({...form, description: e.target.value})}
                                rows={3}
                                placeholder="Décrivez le périmètre et les responsabilités de ce rôle..."
                            />
                            <label className="flex items-center gap-2 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    checked={form.is_active}
                                    onChange={e => setForm({...form, is_active: e.target.checked})}
                                    className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                Rôle actif
                            </label>

                            <div className="pt-4 border-t border-gray-100">
                                <div className="flex items-center justify-between mb-3">
                                    <h4 className="text-sm font-semibold text-gray-800">Permissions ({form.permissions.length})</h4>
                                </div>

                                {permissionsGroupees.length === 0 ? (
                                    <p className="text-sm text-gray-500 italic">Chargement des permissions...</p>
                                ) : (
                                    <div className="space-y-3 max-h-80 overflow-y-auto pr-1">
                                        {permissionsGroupees.map(groupe => {
                                            const ids = (groupe.permissions || []).map(p => p.id);
                                            const tousCoches = ids.length > 0 && ids.every(id => form.permissions.includes(id));
                                            const partielsCoches = ids.some(id => form.permissions.includes(id));
                                            return (
                                                <div key={groupe.groupe} className="border border-gray-200 rounded-lg overflow-hidden">
                                                    <button
                                                        type="button"
                                                        onClick={() => toggleGroupe(groupe)}
                                                        className="w-full flex items-center justify-between px-4 py-2.5 bg-gray-50 hover:bg-gray-100 transition-colors"
                                                    >
                                                        <span className="font-semibold text-sm text-gray-800 capitalize">{groupe.groupe}</span>
                                                        <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${tousCoches ? 'bg-emerald-100 text-emerald-700' : partielsCoches ? 'bg-amber-100 text-amber-700' : 'bg-gray-200 text-gray-600'}`}>
                                                            {ids.filter(id => form.permissions.includes(id)).length}/{ids.length}
                                                        </span>
                                                    </button>
                                                    <div className="p-3 grid grid-cols-2 gap-2 bg-white">
                                                        {(groupe.permissions || []).map(p => (
                                                            <label key={p.id} className="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                                                <input
                                                                    type="checkbox"
                                                                    checked={form.permissions.includes(p.id)}
                                                                    onChange={() => togglePermission(p.id)}
                                                                    className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                                />
                                                                <span className="font-mono">{p.name}</span>
                                                            </label>
                                                        ))}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>

                </form>
            </Modal>
        </div>
    );
}
