/**
 * AuditFlashLibres — Vue ASC premium : liste tous les Audit Flash en
 * self-service des clients du portefeuille, avec score et zone de risque.
 */

import { useEffect, useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { listAuditFlashLibresAdmin } from '@/api/auditFlash';
import { alertError } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import StatCard from '@/components/ui/StatCard';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import EmptyState from '@/components/ui/EmptyState';
import { Input, Select } from '@/components/ui/Input';
import {
    BoltIcon, ChartBarIcon, MagnifyingGlassIcon, FunnelIcon,
    BuildingOffice2Icon, CheckCircleIcon, ExclamationTriangleIcon, FireIcon,
    ChevronRightIcon,
} from '@heroicons/react/24/outline';

const ZONE_CFG = {
    conforme: { label: 'Conforme', variant: 'success', icon: CheckCircleIcon, accent: 'from-emerald-500 to-teal-600' },
    danger: { label: 'Zone danger', variant: 'warning', icon: ExclamationTriangleIcon, accent: 'from-amber-500 to-orange-600' },
    rouge: { label: 'Zone rouge', variant: 'danger', icon: FireIcon, accent: 'from-rose-500 to-red-600' },
};

const STATUT_CFG = {
    brouillon: { label: 'Brouillon', variant: 'gray' },
    envoye: { label: 'À remplir', variant: 'info' },
    rempli: { label: 'Rempli', variant: 'success' },
    valide: { label: 'Validé', variant: 'success' },
};

export default function AuditFlashLibres() {
    const navigate = useNavigate();
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [filterText, setFilterText] = useState('');
    const [filterZone, setFilterZone] = useState('');
    const [filterStatut, setFilterStatut] = useState('');

    useEffect(() => {
        listAuditFlashLibresAdmin()
            .then(r => setItems(r.data || []))
            .catch(e => alertError(e.response?.data?.message || 'Impossible de charger les Audit Flash'))
            .finally(() => setLoading(false));
    }, []);

    const filtered = useMemo(() => items.filter(it => {
        if (filterZone && it.zone !== filterZone) return false;
        if (filterStatut && it.statut !== filterStatut) return false;
        const txt = filterText.toLowerCase().trim();
        if (!txt) return true;
        return (it.client?.raison_sociale || '').toLowerCase().includes(txt)
            || (it.client?.sigle || '').toLowerCase().includes(txt);
    }), [items, filterText, filterZone, filterStatut]);

    const stats = useMemo(() => {
        const total = items.length;
        const rouge = items.filter(i => i.zone === 'rouge').length;
        const danger = items.filter(i => i.zone === 'danger').length;
        const conforme = items.filter(i => i.zone === 'conforme').length;
        const remplis = items.filter(i => i.statut === 'rempli' || i.statut === 'valide').length;
        return { total, rouge, danger, conforme, remplis };
    }, [items]);

    return (
        <div className="p-6 lg:p-8 max-w-7xl mx-auto">
            <PageHeader
                title="Audit Flash"
                subtitle="Résultats des auto-évaluations Audit Flash lancées en self-service par vos clients."
                eyebrow="Vue ASC consolidée"
                icon={BoltIcon}
                accent="rose"
            >
                <Badge variant="rose" solid size="md" dot>{items.length} client{items.length > 1 ? 's' : ''}</Badge>
            </PageHeader>

            {/* KPI */}
            <div className="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                <StatCard
                    titre="Total"
                    valeur={stats.total}
                    icon={BuildingOffice2Icon}
                    couleur="blue"
                    soustitre="Clients évalués"
                />
                <StatCard
                    titre="Finalisés"
                    valeur={stats.remplis}
                    icon={CheckCircleIcon}
                    couleur="cyan"
                    soustitre="Questionnaires complets"
                />
                <StatCard
                    titre="Conformes"
                    valeur={stats.conforme}
                    icon={CheckCircleIcon}
                    couleur="emerald"
                    soustitre="Zone verte"
                />
                <StatCard
                    titre="Zone danger"
                    valeur={stats.danger}
                    icon={ExclamationTriangleIcon}
                    couleur="amber"
                    soustitre="Vulnérabilités"
                />
                <StatCard
                    titre="Zone rouge"
                    valeur={stats.rouge}
                    icon={FireIcon}
                    couleur="rose"
                    soustitre="Urgence absolue"
                />
            </div>

            {/* Filtres + liste */}
            <Card variant="elevated" className="overflow-hidden">
                <div className="px-5 py-4 border-b border-gray-100/80 bg-gray-50/40 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                    <div className="flex items-center gap-2 flex-wrap">
                        <Input
                            icon={MagnifyingGlassIcon}
                            value={filterText}
                            onChange={e => setFilterText(e.target.value)}
                            placeholder="Rechercher un client..."
                            className="w-72"
                        />
                        <Select
                            value={filterZone}
                            onChange={e => setFilterZone(e.target.value)}
                            className="w-48"
                        >
                            <option value="">Toutes les zones</option>
                            <option value="conforme">Zone conforme</option>
                            <option value="danger">Zone danger</option>
                            <option value="rouge">Zone rouge</option>
                        </Select>
                        <Select
                            value={filterStatut}
                            onChange={e => setFilterStatut(e.target.value)}
                            className="w-44"
                        >
                            <option value="">Tous statuts</option>
                            <option value="envoye">À remplir</option>
                            <option value="rempli">Rempli</option>
                            <option value="valide">Validé</option>
                        </Select>
                    </div>
                    <div className="flex items-center gap-2 text-xs text-gray-500">
                        <FunnelIcon className="w-3.5 h-3.5" />
                        {filtered.length} résultat{filtered.length > 1 ? 's' : ''}
                    </div>
                </div>

                {loading ? (
                    <div className="py-16 flex items-center justify-center">
                        <div className="relative">
                            <div className="w-10 h-10 rounded-full border-[3px] border-gray-200" />
                            <div className="absolute top-0 left-0 w-10 h-10 rounded-full border-[3px] border-blue-600 border-t-transparent animate-spin" />
                        </div>
                    </div>
                ) : filtered.length === 0 ? (
                    <EmptyState
                        icon={BoltIcon}
                        title="Aucun Audit Flash"
                        description="Aucun client n'a encore lancé d'Audit Flash en self-service correspondant aux filtres."
                        accent="rose"
                    />
                ) : (
                    <div className="divide-y divide-gray-100/80">
                        {filtered.map(it => {
                            const zone = ZONE_CFG[it.zone] || ZONE_CFG.danger;
                            const ZoneIcon = zone.icon;
                            const statut = STATUT_CFG[it.statut] || STATUT_CFG.envoye;
                            const aDesReponses = it.repondues > 0;
                            const pct = it.total_questions > 0 ? Math.round((it.repondues / it.total_questions) * 100) : 0;
                            return (
                                <div key={it.id} className="group px-5 py-4 flex items-center gap-4 hover:bg-blue-50/40 transition-colors">
                                    <div className={`shrink-0 w-12 h-12 rounded-2xl bg-gradient-to-br ${zone.accent} flex items-center justify-center shadow-md shadow-blue-500/10`}>
                                        <ZoneIcon className="w-6 h-6 text-white" />
                                    </div>

                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2 flex-wrap mb-1">
                                            <p className="font-bold text-gray-900 truncate text-sm">
                                                {it.client?.raison_sociale || '—'}
                                            </p>
                                            {it.client?.sigle && <span className="text-xs text-gray-400 font-mono">({it.client.sigle})</span>}
                                            <Badge variant={statut.variant} size="sm" dot>{statut.label}</Badge>
                                            <Badge variant={zone.variant} size="sm" solid>{zone.label}</Badge>
                                        </div>
                                        <div className="flex items-center gap-3 text-xs text-gray-500">
                                            <span className="tabular-nums">
                                                <span className="font-semibold text-gray-700">Score {it.score_total}</span>/{it.score_max}
                                            </span>
                                            <span>·</span>
                                            <span className="tabular-nums">{it.repondues}/{it.total_questions} questions</span>
                                            {it.rempli_a && (
                                                <>
                                                    <span>·</span>
                                                    <span>{new Date(it.rempli_a).toLocaleDateString('fr-FR')}</span>
                                                </>
                                            )}
                                        </div>
                                        {/* Barre de progression */}
                                        <div className="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden max-w-md">
                                            <div className={`h-full bg-gradient-to-r ${zone.accent} transition-all`} style={{ width: `${pct}%` }} />
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2 shrink-0">
                                        {aDesReponses && (
                                            <Button
                                                variant="success"
                                                size="sm"
                                                onClick={() => navigate(`/questionnaires-generes/${it.id}/audit-flash-resultat`)}
                                            >
                                                <ChartBarIcon className="w-4 h-4" />
                                                Résultat
                                            </Button>
                                        )}
                                        <ChevronRightIcon className="w-4 h-4 text-gray-300 group-hover:text-blue-600 group-hover:translate-x-0.5 transition-all hidden sm:block" />
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </Card>
        </div>
    );
}
