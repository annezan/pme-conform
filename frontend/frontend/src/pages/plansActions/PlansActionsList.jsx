/**
 * Page PlansActionsList — Liste des plans d'actions.
 * Visible client (ses plans) et consultant (plans de ses clients).
 */

import { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '@/contexts/AuthContext';
import { listPlansActions, STATUT_PLAN } from '@/api/plansActions';
import { alertError } from '@/utils/alerts';
import DataTableWrapper from '@/components/ui/DataTableWrapper';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import PageHeader from '@/components/ui/PageHeader';
import { Select } from '@/components/ui/Input';
import TableActions, { ViewAction } from '@/components/ui/TableActions';
import { PlusIcon, FlagIcon } from '@heroicons/react/24/outline';

export default function PlansActionsList() {
    const navigate = useNavigate();
    const { hasPermission } = useAuth();
    // "Vue client" = utilisateur sans permission de gerer les plans (pas de
    // selection client, affichage simplifie).
    const estClient = !hasPermission('view-all-plans-actions');
    const peutCreer = hasPermission('create-plans-actions');

    const [plans, setPlans] = useState([]);
    const [loading, setLoading] = useState(true);
    const [filtreStatut, setFiltreStatut] = useState('');

    const charger = async () => {
        setLoading(true);
        try {
            const params = { per_page: 100 };
            if (filtreStatut) params.statut = filtreStatut;
            const r = await listPlansActions(params);
            setPlans(r.data || []);
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur de chargement');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { charger(); }, [filtreStatut]);

    const columns = useMemo(() => [
        {
            name: 'Référence',
            width: '140px',
            selector: row => row.reference,
            cell: row => <span className="font-mono text-xs font-semibold text-blue-700">{row.reference}</span>,
        },
        {
            name: 'Titre',
            grow: 3,
            cell: row => (
                <div className="py-2">
                    <p className="font-medium text-gray-900 text-sm">{row.titre}</p>
                    {row.objectif && <p className="text-xs text-gray-500 mt-0.5 line-clamp-1">{row.objectif}</p>}
                </div>
            ),
        },
        !estClient && {
            name: 'Client',
            selector: row => row.client?.raison_sociale || '-',
            width: '180px',
            cell: row => <span className="text-xs text-gray-700">{row.client?.raison_sociale || '-'}</span>,
        },
        {
            name: 'Actions',
            width: '100px',
            center: true,
            cell: row => <span className="text-sm font-semibold text-gray-900">{row.items_count || 0}</span>,
        },
        {
            name: 'Statut',
            width: '140px',
            selector: row => row.statut,
            cell: row => {
                const cfg = STATUT_PLAN[row.statut] || {};
                return <Badge variant={cfg.color}>{cfg.label}</Badge>;
            },
        },
        {
            name: 'Proposé par',
            width: '150px',
            selector: row => row.proposeur ? `${row.proposeur.prenom || ''} ${row.proposeur.nom || ''}`.trim() : '-',
            cell: row => <span className="text-xs text-gray-600">{row.proposeur ? `${row.proposeur.prenom || ''} ${row.proposeur.nom || ''}`.trim() : '-'}</span>,
        },
        {
            name: 'Date',
            width: '110px',
            selector: row => row.created_at,
            cell: row => <span className="text-xs text-gray-500">{new Date(row.created_at).toLocaleDateString('fr-FR')}</span>,
        },
        {
            name: '',
            width: '70px',
            right: true,
            cell: row => (
                <TableActions>
                    <ViewAction onClick={() => navigate(`/plans-actions/${row.id}`)} />
                </TableActions>
            ),
        },
    ].filter(Boolean), [navigate, estClient]);

    return (
        <div className="p-6 lg:p-8 max-w-7xl mx-auto">
            <PageHeader
                title="Plans d'actions"
                subtitle={estClient ? 'Plans proposés par votre consultant ASC' : 'Plans d\'actions proposés aux clients'}
                eyebrow="Pilotage conformité"
                icon={FlagIcon}
                accent="amber"
            >
                {peutCreer && (
                    <Button onClick={() => navigate('/plans-actions/nouveau')} variant="primary">
                        <PlusIcon className="w-4 h-4" /> Nouveau plan
                    </Button>
                )}
            </PageHeader>

            <Card variant="elevated" className="overflow-hidden">
                <div className="px-5 py-4 border-b border-gray-100/80 bg-gray-50/40 flex items-center justify-between gap-3 flex-wrap">
                    <p className="text-sm text-gray-600">
                        <span className="font-bold text-gray-900">{plans.length}</span> plan{plans.length > 1 ? 's' : ''}
                    </p>
                    <Select value={filtreStatut} onChange={e => setFiltreStatut(e.target.value)} className="w-48">
                        <option value="">Tous les statuts</option>
                        <option value="propose">Proposés</option>
                        <option value="accepte_client">Acceptés</option>
                        <option value="en_cours">En cours</option>
                        <option value="cloture">Clôturés</option>
                        <option value="rejete">Rejetés</option>
                    </Select>
                </div>
                <DataTableWrapper columns={columns} data={plans} loading={loading} onRowClicked={row => navigate(`/plans-actions/${row.id}`)} />
            </Card>
        </div>
    );
}
