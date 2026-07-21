/**
 * Page MissionsList premium — DataTable + Modal premium + SweetAlert2.
 */

import { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import api, { getCsrfCookie } from '@/api/client';
import { alertSuccess, alertError, confirmDelete } from '@/utils/alerts';
import DataTableWrapper from '@/components/ui/DataTableWrapper';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import PageHeader from '@/components/ui/PageHeader';
import Modal from '@/components/ui/Modal';
import { Input, Select } from '@/components/ui/Input';
import TableActions, { ViewAction, DeleteAction } from '@/components/ui/TableActions';
import {
    PlusIcon, MagnifyingGlassIcon, ClipboardDocumentListIcon,
    ClipboardDocumentCheckIcon, SparklesIcon,
} from '@heroicons/react/24/outline';

const statutVariant = { brouillon: 'gray', en_cours: 'info', en_revue: 'warning', termine: 'success', archive: 'gray' };
const prioriteVariant = { basse: 'gray', normale: 'info', haute: 'warning', urgente: 'danger' };

const METHODES = [
    {
        key: 'methode_1',
        label: 'Méthode 1 - Classique',
        desc: 'ASC conçoit les questionnaires, le client les remplit et upload sur /mes-documents.',
        icon: ClipboardDocumentCheckIcon,
        accent: 'blue',
        ring: 'ring-blue-200 bg-blue-50/40',
        ringActive: 'ring-blue-600 bg-blue-50 shadow-md shadow-blue-500/10',
    },
    {
        key: 'methode_2',
        label: 'Méthode 2 - IA dynamique',
        desc: 'Matrice initiale → organigramme → questionnaires générés par IA pour chaque pôle.',
        icon: SparklesIcon,
        accent: 'purple',
        ring: 'ring-purple-200 bg-purple-50/40',
        ringActive: 'ring-purple-600 bg-purple-50 shadow-md shadow-purple-500/10',
    },
];

export default function MissionsList() {
    const navigate = useNavigate();
    const [missions, setMissions] = useState([]);
    const [clients, setClients] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [form, setForm] = useState({ client_id: '', titre: '', methode: 'methode_1', priorite: 'normale', description: '' });
    const [saving, setSaving] = useState(false);
    const [filterText, setFilterText] = useState('');

    const charger = () => {
        Promise.all([api.get('/missions?per_page=100'), api.get('/clients?per_page=100')])
            .then(([m, c]) => { setMissions(m.data.data || []); setClients(c.data.data || []); })
            .finally(() => setLoading(false));
    };
    useEffect(() => { charger(); }, []);

    const handleCreate = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            await getCsrfCookie();
            await api.post('/missions', { ...form, type: 'audit_conformite' });
            alertSuccess('Mission créée avec succès');
            setShowModal(false);
            setForm({ client_id: '', titre: '', methode: 'methode_1', priorite: 'normale', description: '' });
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur lors de la création');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (mission) => {
        if (await confirmDelete(mission.titre)) {
            try {
                await getCsrfCookie();
                await api.delete(`/missions/${mission.id}`);
                alertSuccess('Mission supprimée');
                charger();
            } catch {
                alertError('Erreur lors de la suppression');
            }
        }
    };

    const columns = useMemo(() => [
        {
            name: 'Référence',
            selector: row => row.reference,
            sortable: true,
            width: '140px',
            cell: row => <span className="font-mono text-xs font-semibold text-blue-700">{row.reference}</span>,
        },
        {
            name: 'Titre',
            selector: row => row.titre,
            sortable: true,
            grow: 2,
            cell: row => (
                <div className="py-2.5">
                    <p className="font-semibold text-gray-900 text-sm leading-snug">{row.titre}</p>
                    {row.methode && (
                        <p className="text-[11px] text-gray-500 mt-0.5">
                            {row.methode === 'methode_3' ? 'Audit Flash' : row.methode === 'methode_2' ? 'IA dynamique' : 'Classique'}
                        </p>
                    )}
                </div>
            ),
        },
        { name: 'Client', selector: row => row.client?.raison_sociale || '-', sortable: true },
        { name: 'Responsable', selector: row => row.responsable ? `${row.responsable.prenom} ${row.responsable.nom}` : '-', sortable: true, width: '160px' },
        {
            name: 'Statut',
            width: '130px',
            cell: row => <Badge variant={statutVariant[row.statut] || 'gray'} size="sm" dot>{row.statut?.replace('_', ' ')}</Badge>,
        },
        {
            name: 'Priorité',
            width: '110px',
            cell: row => <Badge variant={prioriteVariant[row.priorite] || 'gray'} size="sm">{row.priorite}</Badge>,
        },
        {
            name: 'Actions',
            width: '120px',
            right: true,
            cell: row => (
                <TableActions>
                    <ViewAction onClick={() => navigate(`/missions/${row.id}`)} />
                    <DeleteAction onClick={() => handleDelete(row)} />
                </TableActions>
            ),
        },
    ], []);

    const filteredData = missions.filter(m =>
        m.titre?.toLowerCase().includes(filterText.toLowerCase()) ||
        m.reference?.toLowerCase().includes(filterText.toLowerCase()) ||
        m.client?.raison_sociale?.toLowerCase().includes(filterText.toLowerCase())
    );

    return (
        <div className="p-6 lg:p-8 max-w-7xl mx-auto">
            <PageHeader
                title="Missions"
                subtitle="Dossiers de conformité ouverts pour vos clients"
                eyebrow="Portefeuille opérationnel"
                icon={ClipboardDocumentListIcon}
                accent="emerald"
            >
                <Button onClick={() => setShowModal(true)} variant="primary">
                    <PlusIcon className="w-4 h-4" /> Nouvelle mission
                </Button>
            </PageHeader>

            <Card variant="elevated" className="overflow-hidden">
                <div className="px-5 py-4 border-b border-gray-100/80 bg-gray-50/40 flex items-center justify-between gap-3 flex-wrap">
                    <p className="text-sm text-gray-600">
                        <span className="font-bold text-gray-900">{filteredData.length}</span> mission{filteredData.length > 1 ? 's' : ''}
                    </p>
                    <Input
                        icon={MagnifyingGlassIcon}
                        value={filterText}
                        onChange={e => setFilterText(e.target.value)}
                        placeholder="Rechercher une mission..."
                        className="w-72"
                    />
                </div>
                <DataTableWrapper
                    columns={columns}
                    data={filteredData}
                    loading={loading}
                    onRowClicked={row => navigate(`/missions/${row.id}`)}
                />
            </Card>

            {/* Modal premium */}
            <Modal
                open={showModal}
                onClose={() => setShowModal(false)}
                title="Nouvelle mission"
                subtitle="Créez un dossier de conformité pour un client"
                icon={PlusIcon}
                accent="emerald"
                size="lg"
                footer={(
                    <>
                        <Button variant="secondary" type="button" onClick={() => setShowModal(false)}>Annuler</Button>
                        <Button variant="primary" type="submit" form="mission-create-form" loading={saving}>
                            {saving ? 'Création...' : 'Créer la mission'}
                        </Button>
                    </>
                )}
            >
                <form id="mission-create-form" onSubmit={handleCreate} className="space-y-5">
                    <Select label="Client" required value={form.client_id} onChange={e => setForm({...form, client_id: e.target.value})}>
                        <option value="">Sélectionner un client</option>
                        {clients.map(c => <option key={c.id} value={c.id}>{c.raison_sociale}</option>)}
                    </Select>

                    <Input
                        label="Titre"
                        required
                        value={form.titre}
                        onChange={e => setForm({...form, titre: e.target.value})}
                        placeholder="Ex: Audit RGPD trimestriel"
                    />

                    <Select label="Priorité" value={form.priorite} onChange={e => setForm({...form, priorite: e.target.value})}>
                        <option value="basse">Basse</option>
                        <option value="normale">Normale</option>
                        <option value="haute">Haute</option>
                        <option value="urgente">Urgente</option>
                    </Select>

                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-2">
                            Méthode de travail <span className="text-red-500">*</span>
                        </label>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                            {METHODES.map(m => {
                                const Icon = m.icon;
                                const isActive = form.methode === m.key;
                                return (
                                    <button
                                        key={m.key}
                                        type="button"
                                        onClick={() => setForm({...form, methode: m.key})}
                                        className={`relative overflow-hidden text-left p-3 rounded-xl ring-1 transition-all duration-200 hover:-translate-y-0.5 ${isActive ? m.ringActive : m.ring + ' hover:ring-gray-300'}`}
                                    >
                                        <div className={`absolute -top-6 -right-6 w-16 h-16 rounded-full bg-${m.accent}-500/10 blur-2xl pointer-events-none`} />
                                        <div className={`w-8 h-8 rounded-lg bg-${m.accent}-100 flex items-center justify-center mb-2`}>
                                            <Icon className={`w-4 h-4 text-${m.accent}-700`} />
                                        </div>
                                        <p className="font-bold text-gray-900 text-sm leading-tight">{m.label}</p>
                                        <p className="text-[11px] text-gray-600 mt-1 leading-snug">{m.desc}</p>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
