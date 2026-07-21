/**
 * Page AnalysesList — Liste des analyses d'ecarts lancees.
 * Polling leger pour suivre les statuts en temps quasi-reel.
 */

import { useState, useEffect, useMemo, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '@/contexts/AuthContext';
import { listAnalyses, deleteAnalyse } from '@/api/analyses';
import { alertSuccess, alertError, confirmDelete } from '@/utils/alerts';
import DataTableWrapper from '@/components/ui/DataTableWrapper';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import PageHeader from '@/components/ui/PageHeader';
import TableActions, { ViewAction, DeleteAction } from '@/components/ui/TableActions';
import StatCard from '@/components/ui/StatCard';
import { Input } from '@/components/ui/Input';
import { PlusIcon, MagnifyingGlassCircleIcon, MagnifyingGlassIcon, CheckCircleIcon, ExclamationTriangleIcon, ClockIcon } from '@heroicons/react/24/outline';

const statutVariant = {
    en_attente: 'gray',
    en_cours: 'info',
    terminee: 'success',
    erreur: 'danger',
    annulee: 'gray',
};

const statutLabel = {
    en_attente: 'En attente',
    en_cours: 'En cours',
    terminee: 'Terminée',
    erreur: 'Erreur',
    annulee: 'Annulée',
};

export default function AnalysesList() {
    const navigate = useNavigate();
    const { hasPermission } = useAuth();
    // "Vue client" = utilisateur sans permission de creer des analyses : on cache
    // les boutons Nouvelle analyse / Supprimer.
    const estClient = !hasPermission('create-analyses');
    const [analyses, setAnalyses] = useState([]);
    const [loading, setLoading] = useState(true);
    const [filterText, setFilterText] = useState('');
    const pollingRef = useRef(null);

    const charger = async () => {
        try {
            const r = await listAnalyses({ per_page: 50 });
            setAnalyses(r.data || []);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        charger();
        // Polling toutes les 4s si au moins une analyse en cours/attente
        pollingRef.current = setInterval(() => {
            setAnalyses(current => {
                if (current.some(a => ['en_attente', 'en_cours'].includes(a.statut))) {
                    charger();
                }
                return current;
            });
        }, 4000);
        return () => clearInterval(pollingRef.current);
    }, []);

    const handleDelete = async (analyse) => {
        if (await confirmDelete(analyse.reference)) {
            try {
                await deleteAnalyse(analyse.id);
                alertSuccess('Analyse supprimée');
                charger();
            } catch {
                alertError('Erreur lors de la suppression');
            }
        }
    };

    const scoreBadge = (score) => {
        if (score === null || score === undefined) return <span className="text-gray-400 text-xs">-</span>;
        const s = parseFloat(score);
        const color = s >= 80 ? 'text-emerald-700 bg-emerald-50' : s >= 60 ? 'text-amber-700 bg-amber-50' : 'text-red-700 bg-red-50';
        return <span className={`px-2 py-1 rounded-md font-semibold text-xs ${color}`}>{s.toFixed(0)}%</span>;
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
            name: 'Client / Mission',
            grow: 2,
            cell: row => (
                <div className="py-2">
                    <p className="font-medium text-gray-900 text-sm">{row.mission?.client?.raison_sociale || '-'}</p>
                    <p className="text-xs text-gray-500 mt-0.5">{row.mission?.titre || row.titre}</p>
                </div>
            ),
        },
        {
            name: 'Statut',
            selector: row => row.statut,
            width: '130px',
            cell: row => <Badge variant={statutVariant[row.statut]}>{statutLabel[row.statut]}</Badge>,
        },
        {
            name: 'Écarts',
            width: '170px',
            cell: row => {
                if (!['terminee', 'en_cours'].includes(row.statut)) return <span className="text-gray-400 text-xs">-</span>;
                return (
                    <div className="flex items-center gap-1.5 text-xs">
                        {row.nb_ecarts_critiques > 0 && <span className="px-1.5 py-0.5 rounded bg-red-100 text-red-700 font-semibold">{row.nb_ecarts_critiques} C</span>}
                        {row.nb_ecarts_majeurs > 0 && <span className="px-1.5 py-0.5 rounded bg-orange-100 text-orange-700 font-semibold">{row.nb_ecarts_majeurs} M</span>}
                        {row.nb_ecarts_mineurs > 0 && <span className="px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-700 font-semibold">{row.nb_ecarts_mineurs} m</span>}
                        {row.nb_ecarts_critiques + row.nb_ecarts_majeurs + row.nb_ecarts_mineurs === 0 && row.statut === 'terminee' && (
                            <CheckCircleIcon className="w-5 h-5 text-emerald-500" />
                        )}
                    </div>
                );
            },
        },
        {
            name: 'Score',
            width: '90px',
            center: true,
            cell: row => scoreBadge(row.score_conformite),
        },
        {
            name: 'Lancée par',
            width: '150px',
            selector: row => row.lanceur ? `${row.lanceur.prenom || ''} ${row.lanceur.nom || ''}`.trim() : '-',
        },
        {
            name: 'Date',
            width: '130px',
            selector: row => row.created_at,
            cell: row => <span className="text-xs text-gray-500">{new Date(row.created_at).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })}</span>,
        },
        {
            name: 'Actions',
            width: '110px',
            right: true,
            cell: row => (
                <TableActions>
                    <ViewAction onClick={() => navigate(`/analyses/${row.id}`)} />
                    {!estClient && <DeleteAction onClick={() => handleDelete(row)} />}
                </TableActions>
            ),
        },
    ], [navigate, estClient]);

    const filtered = analyses.filter(a =>
        a.reference?.toLowerCase().includes(filterText.toLowerCase()) ||
        a.titre?.toLowerCase().includes(filterText.toLowerCase()) ||
        a.mission?.client?.raison_sociale?.toLowerCase().includes(filterText.toLowerCase())
    );

    const stats = useMemo(() => ({
        total: analyses.length,
        enCours: analyses.filter(a => a.statut === 'en_cours' || a.statut === 'en_attente').length,
        terminees: analyses.filter(a => a.statut === 'terminee').length,
        erreurs: analyses.filter(a => a.statut === 'erreur').length,
    }), [analyses]);

    return (
        <div className="p-6 lg:p-8 max-w-7xl mx-auto">
            <PageHeader
                title={estClient ? 'Mes analyses' : 'Analyses d\'écarts'}
                subtitle={estClient ? 'Consultation des écarts et recommandations' : 'Détection automatique des manquements de conformité'}
                eyebrow={estClient ? 'Conformité' : 'Moteur IA'}
                icon={MagnifyingGlassCircleIcon}
                accent="purple"
            >
                {!estClient && (
                    <Button onClick={() => navigate('/analyses/nouvelle')} variant="primary">
                        <PlusIcon className="w-4 h-4" /> Nouvelle analyse
                    </Button>
                )}
            </PageHeader>

            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <StatCard titre="Total" valeur={stats.total} icon={MagnifyingGlassCircleIcon} couleur="blue" soustitre="Analyses lancées" />
                <StatCard titre="En cours" valeur={stats.enCours} icon={ClockIcon} couleur="amber" soustitre="En traitement" />
                <StatCard titre="Terminées" valeur={stats.terminees} icon={CheckCircleIcon} couleur="emerald" soustitre="Résultats prêts" />
                <StatCard titre="Erreurs" valeur={stats.erreurs} icon={ExclamationTriangleIcon} couleur="rose" soustitre="À relancer" />
            </div>

            <Card variant="elevated" className="overflow-hidden">
                <div className="px-5 py-4 border-b border-gray-100/80 bg-gray-50/40 flex items-center justify-between gap-3 flex-wrap">
                    <p className="text-sm text-gray-600">
                        <span className="font-bold text-gray-900">{filtered.length}</span> analyse{filtered.length > 1 ? 's' : ''}
                    </p>
                    <Input
                        icon={MagnifyingGlassIcon}
                        value={filterText}
                        onChange={e => setFilterText(e.target.value)}
                        placeholder="Rechercher une analyse..."
                        className="w-72"
                    />
                </div>
                <DataTableWrapper columns={columns} data={filtered} loading={loading} onRowClicked={row => navigate(`/analyses/${row.id}`)} />
            </Card>
        </div>
    );
}

function StatMini({ icon: Icon, label, value, color }) {
    const colors = {
        blue: 'bg-blue-50 text-blue-700',
        amber: 'bg-amber-50 text-amber-700',
        emerald: 'bg-emerald-50 text-emerald-700',
        red: 'bg-red-50 text-red-700',
    };
    return (
        <Card className="p-4 flex items-center gap-3">
            <div className={`w-10 h-10 rounded-lg flex items-center justify-center ${colors[color]}`}>
                <Icon className="w-5 h-5" />
            </div>
            <div>
                <p className="text-xs text-gray-500">{label}</p>
                <p className="text-xl font-bold text-gray-900">{value}</p>
            </div>
        </Card>
    );
}
