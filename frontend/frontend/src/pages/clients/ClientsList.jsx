/**
 * Page ClientsList premium — Liste + creation client.
 *
 * API AUDREY :
 *  - GET  /secteurs-activite-liste            → [{id, nom, code}]
 *  - POST /clients                            payload { secteurs_activite_ids: [int], ... }
 *  - GET  /clients/{id}                       client.secteursActivite = [{id, nom}]
 */

import { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import Select from 'react-select';
import api, { getCsrfCookie } from '@/api/client';
import { alertSuccess, alertError, confirmDelete } from '@/utils/alerts';
import DataTableWrapper from '@/components/ui/DataTableWrapper';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import PageHeader from '@/components/ui/PageHeader';
import Modal from '@/components/ui/Modal';
import { Input, Textarea } from '@/components/ui/Input';
import TableActions, { ViewAction, EditAction, DeleteAction } from '@/components/ui/TableActions';
import { PlusIcon, MagnifyingGlassIcon, BuildingOffice2Icon, PencilSquareIcon } from '@heroicons/react/24/outline';

const statutVariant = { prospect: 'warning', actif: 'success', inactif: 'gray', archive: 'danger' };

const FORM_INITIAL = {
    raison_sociale: '',
    description_activite: '',
    secteurs_activite_ids: [],
    pays: 'Côte d\'Ivoire',
    adresse: '',
    ville: '',
    email: '',
    telephone: '',
    contact_principal_nom: '',
    contact_principal_email: '',
};

export default function ClientsList() {
    const navigate = useNavigate();
    const [clients, setClients] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [editId, setEditId] = useState(null); // null = creation, sinon ID du client en cours d'edition
    const [form, setForm] = useState(FORM_INITIAL);
    const [saving, setSaving] = useState(false);
    const [filterText, setFilterText] = useState('');

    const [paysOptions, setPaysOptions] = useState([]);
    const [secteurOptions, setSecteurOptions] = useState([]);

    const charger = () => {
        api.get('/clients?per_page=100').then(r => setClients(r.data.data || [])).finally(() => setLoading(false));
    };

    useEffect(() => {
        charger();
        Promise.all([
            api.get('/ref/pays').then(r => r.data.data || []),
            api.get('/secteurs-activite-liste').then(r => r.data.data || []),
        ]).then(([pays, secteurs]) => {
            setPaysOptions(pays.map(p => ({ value: p, label: p })));
            setSecteurOptions(secteurs.map(s => ({ value: s.id, label: s.nom })));
        }).catch(() => alertError('Impossible de charger les listes de référence'));
    }, []);

    const ouvrirCreation = () => {
        setEditId(null);
        setForm(FORM_INITIAL);
        setShowModal(true);
    };

    const ouvrirEdition = async (client) => {
        // L'endpoint /clients (index) ne fait pas l'eager-loading des secteurs.
        // On va donc chercher la fiche complete pour pre-remplir correctement.
        setEditId(client.id);
        setShowModal(true);
        setForm({ ...FORM_INITIAL, raison_sociale: client.raison_sociale || '' });
        try {
            const r = await api.get(`/clients/${client.id}`);
            const full = r.data.client || client;
            // Laravel renvoie la relation en snake_case (secteurs_activite = [{id, nom, pivot}]).
            const secteursRaw = full.secteursActivite || full.secteurs_activite || [];
            const secteursOpts = secteursRaw
                .filter(s => s && typeof s === 'object' && s.id)
                .map(s => ({ value: s.id, label: s.nom }));
            setForm({
                raison_sociale: full.raison_sociale || '',
                description_activite: full.description_activite || '',
                secteurs_activite_ids: secteursOpts,
                pays: full.pays || 'Côte d\'Ivoire',
                adresse: full.adresse || '',
                ville: full.ville || '',
                email: full.email || '',
                telephone: full.telephone || '',
                contact_principal_nom: full.contact_principal_nom || '',
                contact_principal_email: full.contact_principal_email || '',
                statut: full.statut || 'prospect',
            });
        } catch {
            alertError('Impossible de charger la fiche du client');
        }
    };

    const fermerModal = () => {
        setShowModal(false);
        setEditId(null);
        setForm(FORM_INITIAL);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!form.description_activite || form.description_activite.trim().length < 10) {
            alertError('Veuillez décrire l\'activité de l\'entreprise (10 caractères minimum).');
            return;
        }
        setSaving(true);
        try {
            await getCsrfCookie();
            const payload = {
                ...form,
                secteurs_activite_ids: (form.secteurs_activite_ids || []).map(s => s.value),
            };
            if (editId) {
                await api.put(`/clients/${editId}`, payload);
                alertSuccess('Client mis à jour');
            } else {
                payload.statut = 'prospect';
                await api.post('/clients', payload);
                alertSuccess('Client créé avec succès');
            }
            fermerModal();
            charger();
        } catch (err) {
            const msg = err.response?.data?.errors
                ? Object.values(err.response.data.errors).flat().join(' ')
                : (err.response?.data?.message || 'Erreur lors de l\'enregistrement');
            alertError(msg);
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (client) => {
        if (await confirmDelete(client.raison_sociale)) {
            try {
                await getCsrfCookie();
                await api.delete(`/clients/${client.id}`);
                alertSuccess('Client supprimé');
                charger();
            } catch {
                alertError('Erreur lors de la suppression');
            }
        }
    };

    const columns = useMemo(() => [
        {
            name: 'Raison sociale',
            selector: row => row.raison_sociale,
            sortable: true,
            cell: row => (
                <div className="py-2.5 flex items-center gap-3">
                    <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-100 to-indigo-100 text-blue-700 flex items-center justify-center font-bold text-sm shrink-0">
                        {row.raison_sociale?.charAt(0)?.toUpperCase() || '?'}
                    </div>
                    <div className="min-w-0">
                        <p className="font-semibold text-gray-900 truncate">{row.raison_sociale}</p>
                        {row.sigle && <p className="text-[11px] text-gray-500 font-mono">{row.sigle}</p>}
                    </div>
                </div>
            ),
            grow: 2,
        },
        {
            name: 'Secteur(s)',
            selector: row => {
                // Laravel serialise la relation en snake_case (secteurs_activite),
                // valeur = [{id, nom, pivot}]. Le legacy peut aussi etre une chaine.
                const raw = row.secteursActivite || row.secteurs_activite || [];
                if (Array.isArray(raw) && raw.length) {
                    return raw.map(s => typeof s === 'string' ? s : s?.nom).filter(Boolean).join(', ') || '-';
                }
                return row.secteur_activite || '-';
            },
            sortable: true,
            grow: 2,
        },
        { name: 'Pays', selector: row => row.pays || '-', sortable: true, width: '140px' },
        { name: 'Ville', selector: row => row.ville || '-', sortable: true, width: '120px' },
        { name: 'Missions', selector: row => row.missions_count || 0, sortable: true, width: '100px', center: true },
        {
            name: 'Statut',
            sortable: true,
            width: '120px',
            cell: row => <Badge variant={statutVariant[row.statut] || 'gray'} size="sm" dot>{row.statut}</Badge>,
        },
        {
            name: 'Actions',
            width: '140px',
            right: true,
            cell: row => (
                <TableActions>
                    <ViewAction onClick={() => navigate(`/clients/${row.id}`)} />
                    <EditAction onClick={() => ouvrirEdition(row)} />
                    <DeleteAction onClick={() => handleDelete(row)} />
                </TableActions>
            ),
        },
    ], []);

    const filteredData = clients.filter(c => {
        const t = filterText.toLowerCase();
        const raw = c.secteursActivite || c.secteurs_activite || [];
        const secteursTexte = (Array.isArray(raw) ? raw : [])
            .map(s => typeof s === 'string' ? s : s?.nom || '')
            .concat(c.secteur_activite || '')
            .join(' ')
            .toLowerCase();
        return c.raison_sociale?.toLowerCase().includes(t)
            || secteursTexte.includes(t)
            || c.ville?.toLowerCase().includes(t)
            || c.pays?.toLowerCase().includes(t);
    });

    return (
        <div className="p-6 lg:p-8 max-w-7xl mx-auto">
            <PageHeader
                title="Clients"
                subtitle="Entreprises accompagnées par AS Consulting"
                eyebrow="Portefeuille"
                icon={BuildingOffice2Icon}
                accent="blue"
            >
                <Button onClick={ouvrirCreation} variant="primary">
                    <PlusIcon className="w-4 h-4" /> Nouveau client
                </Button>
            </PageHeader>

            <Card variant="elevated" className="overflow-hidden">
                <div className="px-5 py-4 border-b border-gray-100/80 bg-gray-50/40 flex items-center justify-between gap-3 flex-wrap">
                    <p className="text-sm text-gray-600">
                        <span className="font-bold text-gray-900">{filteredData.length}</span> client{filteredData.length > 1 ? 's' : ''}
                    </p>
                    <Input
                        icon={MagnifyingGlassIcon}
                        value={filterText}
                        onChange={e => setFilterText(e.target.value)}
                        placeholder="Rechercher un client..."
                        className="w-72"
                    />
                </div>
                <DataTableWrapper
                    columns={columns}
                    data={filteredData}
                    loading={loading}
                    onRowClicked={row => navigate(`/clients/${row.id}`)}
                />
            </Card>

            <Modal
                open={showModal}
                onClose={fermerModal}
                title={editId ? 'Modifier le client' : 'Nouveau client'}
                subtitle={editId ? 'Mettez à jour les informations de l\'entreprise' : 'Ajoutez une entreprise à votre portefeuille'}
                icon={editId ? PencilSquareIcon : BuildingOffice2Icon}
                accent="blue"
                size="lg"
                footer={(
                    <>
                        <Button variant="secondary" type="button" onClick={fermerModal}>Annuler</Button>
                        <Button variant="primary" type="submit" form="client-form" loading={saving}>
                            {saving ? 'Enregistrement...' : (editId ? 'Enregistrer' : 'Créer le client')}
                        </Button>
                    </>
                )}
            >
                <form id="client-form" onSubmit={handleSubmit} className="space-y-4">
                    <Input
                        label="Raison sociale"
                        required
                        value={form.raison_sociale}
                        onChange={e => setForm({ ...form, raison_sociale: e.target.value })}
                        placeholder="Nom de l'entreprise"
                    />

                    <Textarea
                        label="Description de l'activité"
                        required
                        rows={3}
                        value={form.description_activite}
                        onChange={e => setForm({ ...form, description_activite: e.target.value })}
                        placeholder="Décrivez l'activité principale de l'entreprise (10 caractères minimum)."
                        helper="Champ obligatoire — exploité par le moteur d'analyse pour cibler les référentiels."
                    />

                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1.5">Secteur(s) d'activité <span className="text-red-500">*</span></label>
                        <Select
                            isMulti
                            options={secteurOptions}
                            value={form.secteurs_activite_ids}
                            onChange={(val) => setForm({ ...form, secteurs_activite_ids: val || [] })}
                            placeholder="Choisissez un ou plusieurs secteurs"
                            classNamePrefix="rs"
                            noOptionsMessage={() => 'Aucun secteur disponible'}
                        />
                        <p className="text-xs text-gray-500 mt-1.5">
                            Les secteurs sont gérés dans <span className="font-medium">Admin → Secteurs d'activité</span>.
                        </p>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-semibold text-gray-700 mb-1.5">Pays <span className="text-red-500">*</span></label>
                            <Select
                                options={paysOptions}
                                value={paysOptions.find(p => p.value === form.pays) || null}
                                onChange={(val) => setForm({ ...form, pays: val?.value || '' })}
                                placeholder="Choisissez un pays"
                                classNamePrefix="rs"
                            />
                        </div>
                        <Input label="Ville" value={form.ville} onChange={e => setForm({ ...form, ville: e.target.value })} placeholder="Ex: Abidjan" />
                    </div>

                    <Textarea label="Adresse" value={form.adresse} onChange={e => setForm({ ...form, adresse: e.target.value })} placeholder="Adresse complète (rue, quartier, code postal...)" rows={2} />

                    <div className="grid grid-cols-2 gap-4">
                        <Input label="Email" type="email" value={form.email} onChange={e => setForm({ ...form, email: e.target.value })} placeholder="contact@entreprise.ci" />
                        <Input label="Téléphone" value={form.telephone} onChange={e => setForm({ ...form, telephone: e.target.value })} placeholder="+225 XX XX XX XX" />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <Input label="Contact principal" value={form.contact_principal_nom} onChange={e => setForm({ ...form, contact_principal_nom: e.target.value })} placeholder="Nom complet" />
                        <Input label="Email contact" type="email" value={form.contact_principal_email} onChange={e => setForm({ ...form, contact_principal_email: e.target.value })} placeholder="email@contact.ci" />
                    </div>

                    {editId && (
                        <div>
                            <label className="block text-sm font-semibold text-gray-700 mb-1.5">Statut</label>
                            <select
                                value={form.statut || 'prospect'}
                                onChange={e => setForm({ ...form, statut: e.target.value })}
                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                            >
                                <option value="prospect">Prospect</option>
                                <option value="actif">Actif</option>
                                <option value="inactif">Inactif</option>
                                <option value="archive">Archivé</option>
                            </select>
                        </div>
                    )}
                </form>
            </Modal>
        </div>
    );
}
