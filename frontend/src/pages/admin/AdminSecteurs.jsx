/**
 * Page AdminSecteurs — CRUD des secteurs d'activite (API AUDREY).
 *
 * Endpoints utilises :
 *  - GET    /secteurs-activite              (paginé : per_page, search, is_actif)
 *  - POST   /secteurs-activite              (nom, description, code, is_actif)
 *  - GET    /secteurs-activite/{id}
 *  - PUT    /secteurs-activite/{id}
 *  - DELETE /secteurs-activite/{id}         (422 si encore utilise par clients/referentiels)
 *  - PATCH  /secteurs-activite/{id}/toggle-actif
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
import { PlusIcon, XMarkIcon, TagIcon, MagnifyingGlassIcon, PencilSquareIcon } from '@heroicons/react/24/outline';

const FORM_DEFAULT = { nom: '', code: '', description: '', is_actif: true };

export default function AdminSecteurs() {
    const [secteurs, setSecteurs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState(FORM_DEFAULT);
    const [saving, setSaving] = useState(false);
    const [filterText, setFilterText] = useState('');

    const charger = () => {
        setLoading(true);
        api.get('/secteurs-activite', { params: { per_page: 100 } })
            .then(r => setSecteurs(r.data.data || []))
            .catch(() => alertError('Impossible de charger les secteurs'))
            .finally(() => setLoading(false));
    };
    useEffect(() => { charger(); }, []);

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

    const ouvrirEdition = (secteur) => {
        setEditing(secteur);
        setForm({
            nom: secteur.nom || '',
            code: secteur.code || '',
            description: secteur.description || '',
            is_actif: !!secteur.is_actif,
        });
        setShowModal(true);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!form.description || form.description.trim().length < 10) {
            alertError('La description du secteur est obligatoire (10 caractères minimum).');
            return;
        }
        setSaving(true);
        try {
            await getCsrfCookie();
            const payload = {
                nom: form.nom.trim(),
                code: form.code?.trim() || null,
                description: form.description.trim(),
                is_actif: !!form.is_actif,
            };
            if (editing) {
                await api.put(`/secteurs-activite/${editing.id}`, payload);
                alertSuccess('Secteur mis à jour');
            } else {
                await api.post('/secteurs-activite', payload);
                alertSuccess('Secteur créé');
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

    const toggleActive = async (secteur) => {
        const action = secteur.is_actif ? 'désactiver' : 'activer';
        if (await confirmAction(`Voulez-vous ${action} le secteur "${secteur.nom}" ?`)) {
            try {
                await getCsrfCookie();
                await api.patch(`/secteurs-activite/${secteur.id}/toggle-actif`);
                alertSuccess(`Secteur ${action === 'activer' ? 'activé' : 'désactivé'}`);
                charger();
            } catch {
                alertError('Erreur');
            }
        }
    };

    const handleDelete = async (secteur) => {
        if (await confirmDelete(secteur.nom)) {
            try {
                await getCsrfCookie();
                await api.delete(`/secteurs-activite/${secteur.id}`);
                alertSuccess('Secteur supprimé');
                charger();
            } catch (err) {
                const details = err.response?.data?.details;
                const baseMsg = err.response?.data?.message || 'Erreur';
                const suffix = details
                    ? ` (${details.clients_count ?? 0} client(s), ${details.referentiels_count ?? 0} référentiel(s))`
                    : '';
                alertError(baseMsg + suffix);
            }
        }
    };

    const columns = useMemo(() => [
        {
            name: 'Nom', grow: 2, sortable: true,
            selector: row => row.nom,
            cell: row => (
                <div className="py-1">
                    <p className="font-medium text-gray-900 text-sm">{row.nom}</p>
                    {row.code && <p className="text-xs text-gray-400 font-mono">{row.code}</p>}
                </div>
            ),
        },
        {
            name: 'Description', grow: 3,
            selector: row => row.description || '-',
            cell: row => <span className="text-xs text-gray-600 line-clamp-2">{row.description || '-'}</span>,
        },
        {
            name: 'Statut', width: '100px',
            cell: row => <Badge variant={row.is_actif ? 'success' : 'gray'}>{row.is_actif ? 'Actif' : 'Inactif'}</Badge>,
        },
        {
            name: 'Actions', width: '160px', right: true,
            cell: row => (
                <TableActions>
                    <EditAction onClick={() => ouvrirEdition(row)} />
                    <ToggleAction onClick={() => toggleActive(row)} active={row.is_actif} label={row.is_actif ? 'Désactiver' : 'Activer'} />
                    <DeleteAction onClick={() => handleDelete(row)} />
                </TableActions>
            ),
        },
    ], []);

    const filtered = secteurs.filter(s =>
        s.nom?.toLowerCase().includes(filterText.toLowerCase()) ||
        s.code?.toLowerCase().includes(filterText.toLowerCase()) ||
        s.description?.toLowerCase().includes(filterText.toLowerCase())
    );

    return (
        <div className="p-6 lg:p-8 max-w-7xl mx-auto">
            <PageHeader
                title="Secteurs d'activité"
                subtitle="Liste de référence utilisée par tous les selects de la plateforme"
                eyebrow="Administration"
                icon={TagIcon}
                accent="cyan"
            >
                <Button onClick={ouvrirCreation} variant="primary">
                    <PlusIcon className="w-4 h-4" /> Nouveau secteur
                </Button>
            </PageHeader>

            <Card variant="elevated" className="overflow-hidden">
                <div className="px-5 py-4 border-b border-gray-100/80 bg-gray-50/40 flex items-center justify-between gap-3 flex-wrap">
                    <p className="text-sm text-gray-600">
                        <span className="font-bold text-gray-900">{filtered.length}</span> secteur{filtered.length > 1 ? 's' : ''}
                    </p>
                    <Input
                        icon={MagnifyingGlassIcon}
                        value={filterText}
                        onChange={e => setFilterText(e.target.value)}
                        placeholder="Rechercher un secteur..."
                        className="w-72"
                    />
                </div>
                <DataTableWrapper columns={columns} data={filtered} loading={loading} />
            </Card>

            <Modal
                open={showModal}
                onClose={fermerModal}
                title={editing ? `Modifier "${editing.nom}"` : 'Nouveau secteur d\'activité'}
                subtitle={editing ? 'Mettre à jour le secteur' : 'Ajouter un secteur à la liste de référence'}
                icon={editing ? PencilSquareIcon : TagIcon}
                accent="cyan"
                size="md"
                footer={(
                    <>
                        <Button variant="secondary" type="button" onClick={fermerModal}>Annuler</Button>
                        <Button variant="primary" type="submit" form="secteur-form" loading={saving}>
                            {saving ? (editing ? 'Enregistrement...' : 'Création...') : (editing ? 'Enregistrer' : 'Créer')}
                        </Button>
                    </>
                )}
            >
                <form id="secteur-form" onSubmit={handleSubmit} className="space-y-4">
                            <Input
                                label="Nom *"
                                value={form.nom}
                                onChange={e => setForm({...form, nom: e.target.value})}
                                required
                                placeholder="Ex: Banque & Finance"
                            />
                            <Input
                                label="Code (optionnel)"
                                value={form.code}
                                onChange={e => setForm({...form, code: e.target.value})}
                                placeholder="Ex: BANQ"
                            />
                            <Textarea
                                label="Description"
                                required
                                value={form.description}
                                onChange={e => setForm({...form, description: e.target.value})}
                                rows={3}
                                placeholder="Description du secteur (10 caractères minimum)..."
                                helper="Obligatoire — exploitée par le moteur d'analyse pour rattacher les référentiels."
                            />
                            <label className="flex items-center gap-2 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    checked={form.is_actif}
                                    onChange={e => setForm({...form, is_actif: e.target.checked})}
                                    className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                Secteur actif (visible dans les selects)
                            </label>

                </form>
            </Modal>
        </div>
    );
}
