/**
 * Page AscClientDetail — Vue 360 d'un client pour le consultant ASC.
 */

import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { getVueClient } from '@/api/ascPortefeuille';
import { alertError } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import StatCardPrem from '@/components/ui/StatCard';
import EmptyState from '@/components/ui/EmptyState';
import Loader from '@/components/ui/Loader';
import {
    ArrowLeftIcon, BuildingOffice2Icon, ClipboardDocumentListIcon, ShieldCheckIcon,
    DocumentDuplicateIcon, MagnifyingGlassCircleIcon, FlagIcon, PlusIcon,
    ChevronRightIcon, MapPinIcon, RectangleGroupIcon,
} from '@heroicons/react/24/outline';

export default function AscClientDetail() {
    const { clientId } = useParams();
    const navigate = useNavigate();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [activeTab, setActiveTab] = useState('synthese');

    useEffect(() => {
        getVueClient(clientId)
            .then(setData)
            .catch(err => alertError(err.response?.data?.message || 'Erreur'))
            .finally(() => setLoading(false));
    }, [clientId]);

    if (loading) return <Loader />;
    if (!data) {
        return (
            <div className="p-8 max-w-7xl mx-auto">
                <EmptyState icon={BuildingOffice2Icon} title="Client introuvable" description="Cette entreprise n'est pas dans votre portefeuille." accent="rose">
                    <button onClick={() => navigate('/asc/portefeuille')}><Button as="span" variant="primary">Retour au portefeuille</Button></button>
                </EmptyState>
            </div>
        );
    }

    const { client, traitements, signatures, registres_kyc, analyses, plans_actions, stats } = data;

    const tabs = [
        { key: 'synthese', label: 'Synthèse' },
        { key: 'traitements', label: `Traitements (${traitements.length})` },
        { key: 'signatures', label: `Signatures (${signatures.length})` },
        { key: 'registres', label: `Registres KYC (${registres_kyc.length})` },
        { key: 'analyses', label: `Analyses (${analyses.length})` },
        { key: 'plans', label: `Plans d'action (${plans_actions.length})` },
    ];

    return (
        <div className="p-6 lg:p-8 max-w-7xl mx-auto">
            <button onClick={() => navigate('/asc/portefeuille')} className="inline-flex items-center gap-1.5 text-sm font-medium text-blue-700 hover:text-blue-900 mb-4 transition-colors">
                <ArrowLeftIcon className="w-4 h-4" /> Retour au portefeuille
            </button>

            <PageHeader
                title={client.raison_sociale}
                subtitle={[client.sigle, client.secteur_activite, client.ville].filter(Boolean).join(' · ')}
                eyebrow="Vue 360 client"
                icon={BuildingOffice2Icon}
                accent="blue"
            >
                <Button variant="primary" onClick={() => navigate('/plans-actions/nouveau')}>
                    <PlusIcon className="w-4 h-4" /> Nouveau plan d'action
                </Button>
            </PageHeader>

            {/* Stats synthetiques */}
            <div className="grid grid-cols-2 md:grid-cols-6 gap-3 mb-6">
                <StatCardPrem titre="Traitements" valeur={`${stats.traitements_valides}/${stats.traitements_total}`} icon={ClipboardDocumentListIcon} couleur="blue" soustitre="Validés / total" />
                <StatCardPrem titre="Signatures" valeur={stats.signatures_actives} icon={ShieldCheckIcon} couleur="emerald" soustitre="Chartes actives" />
                <StatCardPrem titre="Registres KYC" valeur={stats.registres_kyc} icon={DocumentDuplicateIcon} couleur="purple" soustitre="Éditions" />
                <StatCardPrem titre="Analyses" valeur={analyses.length} icon={MagnifyingGlassCircleIcon} couleur="cyan" soustitre="Écarts traités" />
                <StatCardPrem titre="Plans actifs" valeur={stats.plans_actions_actifs} icon={FlagIcon} couleur="orange" soustitre="En cours" />
                <StatCardPrem titre="Plans clôturés" valeur={stats.plans_actions_clotures} icon={FlagIcon} couleur="emerald" soustitre="Terminés" />
            </div>

            {/* Tabs premium */}
            <div className="flex gap-1 mb-5 p-1 bg-gray-100/60 rounded-2xl ring-1 ring-gray-200/60 overflow-x-auto">
                {tabs.map(t => {
                    const isActive = activeTab === t.key;
                    return (
                        <button
                            key={t.key}
                            onClick={() => setActiveTab(t.key)}
                            className={`px-4 py-2 text-sm font-semibold rounded-xl transition-all whitespace-nowrap ${isActive ? 'bg-white text-blue-700 shadow-md shadow-blue-500/10 ring-1 ring-blue-100' : 'text-gray-600 hover:text-gray-900 hover:bg-white/60'}`}
                        >
                            {t.label}
                        </button>
                    );
                })}
            </div>

            {activeTab === 'synthese' && (
                <Card variant="elevated" className="overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                        <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                            <BuildingOffice2Icon className="w-4.5 h-4.5 text-white" />
                        </div>
                        <h3 className="font-bold text-gray-900">Informations entreprise</h3>
                    </div>
                    <div className="p-5 space-y-5">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <InfoLigne label="Raison sociale" value={client.raison_sociale} />
                            <InfoLigne label="Sigle" value={client.sigle} />
                            <InfoLigne label="Secteur" value={client.secteur_activite} />
                            <InfoLigne label="Type structure" value={client.type_structure} />
                            <InfoLigne label="RCCM" value={client.numero_rccm} />
                            <InfoLigne label="Compte contribuable" value={client.numero_cc} />
                            <InfoLigne label="Effectif" value={client.effectif} />
                            <InfoLigne label="CA (MF CFA)" value={client.chiffre_affaires_mfcfa} />
                            <InfoLigne label="Ville" value={client.ville} />
                            <InfoLigne label="Pays" value={client.pays} />
                            <InfoLigne label="Email" value={client.email} />
                            <InfoLigne label="Téléphone" value={client.telephone} />
                        </div>

                        {client.utilisateurs?.length > 0 && (
                            <div className="pt-5 border-t border-gray-100">
                                <h3 className="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3">Utilisateurs rattachés ({client.utilisateurs.length})</h3>
                                <div className="flex flex-wrap gap-2">
                                    {client.utilisateurs.map(u => (
                                        <span key={u.id} className="inline-flex items-center gap-2 px-3 py-2 bg-gradient-to-br from-blue-50 to-indigo-50 ring-1 ring-blue-100 rounded-xl text-sm">
                                            <div className="w-7 h-7 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center text-xs font-bold shadow-sm">
                                                {(u.prenom || '')[0]}{(u.nom || '')[0]}
                                            </div>
                                            <div className="min-w-0">
                                                <p className="text-sm font-semibold text-gray-800 truncate">{u.prenom} {u.nom}</p>
                                                <p className="text-[11px] text-gray-500 truncate">{u.email}</p>
                                            </div>
                                        </span>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </Card>
            )}

            {activeTab === 'traitements' && (
                <ListeCards items={traitements} emptyMsg="Aucun traitement enregistré.">
                    {t => (
                        <div className="flex items-center justify-between" onClick={() => navigate(`/traitements/${t.id}`)}>
                            <div>
                                <p className="font-medium text-gray-900">{t.nom}</p>
                                <p className="text-xs text-gray-500 font-mono">{t.reference}</p>
                            </div>
                            <Badge variant={t.statut === 'valide' ? 'success' : t.statut === 'brouillon' ? 'gray' : 'purple'}>{t.statut}</Badge>
                        </div>
                    )}
                </ListeCards>
            )}

            {activeTab === 'signatures' && (
                <ListeCards items={signatures} emptyMsg="Aucune signature.">
                    {s => (
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="font-medium text-gray-900">{s.charte?.titre}</p>
                                <p className="text-xs text-gray-500">
                                    {s.user?.prenom} {s.user?.nom} · {new Date(s.signee_le).toLocaleString('fr-FR')}
                                </p>
                            </div>
                            <Badge variant={s.statut === 'signee' ? 'success' : 'gray'}>{s.statut}</Badge>
                        </div>
                    )}
                </ListeCards>
            )}

            {activeTab === 'registres' && (
                <ListeCards items={registres_kyc} emptyMsg="Aucun registre généré.">
                    {r => (
                        <div>
                            <p className="font-medium text-gray-900">{r.reference}</p>
                            <p className="text-xs text-gray-500">{r.nb_traitements} traitements · {new Date(r.created_at).toLocaleString('fr-FR')}</p>
                        </div>
                    )}
                </ListeCards>
            )}

            {activeTab === 'analyses' && (
                <ListeCards items={analyses} emptyMsg="Aucune analyse d'écarts.">
                    {a => (
                        <div className="flex items-center justify-between cursor-pointer" onClick={() => navigate(`/analyses/${a.id}`)}>
                            <div>
                                <p className="font-medium text-gray-900">{a.titre}</p>
                                <p className="text-xs text-gray-500">{a.reference} · {a.nb_exigences_verifiees || 0} exigences vérifiées</p>
                            </div>
                            <div className="flex items-center gap-2">
                                {a.score_conformite !== null && <Badge variant="info">{a.score_conformite}%</Badge>}
                                <Badge variant={a.statut === 'terminee' ? 'success' : 'gray'}>{a.statut}</Badge>
                            </div>
                        </div>
                    )}
                </ListeCards>
            )}

            {activeTab === 'plans' && (
                <ListeCards items={plans_actions} emptyMsg="Aucun plan d'action.">
                    {p => (
                        <div className="flex items-center justify-between cursor-pointer" onClick={() => navigate(`/plans-actions/${p.id}`)}>
                            <div>
                                <p className="font-medium text-gray-900">{p.titre}</p>
                                <p className="text-xs text-gray-500">{p.reference} · {p.items_count || 0} actions</p>
                            </div>
                            <Badge variant={p.statut === 'cloture' ? 'success' : p.statut === 'en_cours' ? 'info' : 'gray'}>{p.statut}</Badge>
                        </div>
                    )}
                </ListeCards>
            )}
        </div>
    );
}

function InfoLigne({ label, value }) {
    return (
        <div className="p-3 rounded-lg bg-gray-50/60 ring-1 ring-gray-100">
            <p className="text-[10px] uppercase tracking-wider font-semibold text-gray-500">{label}</p>
            <p className="text-sm text-gray-900 font-semibold mt-0.5">{value || '—'}</p>
        </div>
    );
}

function ListeCards({ items, emptyMsg, children }) {
    if (items.length === 0) {
        return (
            <EmptyState icon={RectangleGroupIcon} title="Rien à afficher" description={emptyMsg} accent="gray" compact />
        );
    }
    return (
        <div className="space-y-2">
            {items.map((it, i) => (
                <Card key={it.id || i} variant="elevated" className="p-4 hover:-translate-y-0.5 hover:shadow-md hover:ring-blue-200 transition-all">
                    {children(it)}
                </Card>
            ))}
        </div>
    );
}
