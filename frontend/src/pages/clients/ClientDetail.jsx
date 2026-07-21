/**
 * Page ClientDetail premium — Fiche entreprise avec hero, KPI, infos
 * organisationnelles, missions associees et consultants.
 */

import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import api from '@/api/client';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import StatCard from '@/components/ui/StatCard';
import EmptyState from '@/components/ui/EmptyState';
import Loader from '@/components/ui/Loader';
import {
    ArrowLeftIcon, BuildingOffice2Icon, ClipboardDocumentListIcon, MapPinIcon,
    EnvelopeIcon, PhoneIcon, UserIcon, UsersIcon, GlobeAltIcon,
    ChevronRightIcon, IdentificationIcon, BriefcaseIcon, CheckBadgeIcon,
} from '@heroicons/react/24/outline';

const statutCfg = {
    prospect: { label: 'Prospect', variant: 'warning' },
    actif: { label: 'Actif', variant: 'success' },
    inactif: { label: 'Inactif', variant: 'gray' },
    archive: { label: 'Archivé', variant: 'danger' },
};

const missionStatutVariant = {
    brouillon: 'gray',
    en_cours: 'info',
    en_revue: 'warning',
    termine: 'success',
    archive: 'gray',
};

export default function ClientDetail() {
    const { id } = useParams();
    const [client, setClient] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.get(`/clients/${id}`)
            .then(r => setClient(r.data.client))
            .finally(() => setLoading(false));
    }, [id]);

    if (loading) return <Loader />;
    if (!client) {
        return (
            <div className="p-8 max-w-7xl mx-auto">
                <EmptyState icon={BuildingOffice2Icon} title="Client introuvable" description="Cette entreprise n'existe pas ou vous n'y avez pas accès." accent="rose">
                    <Link to="/clients"><Button as="span" variant="primary">Retour aux clients</Button></Link>
                </EmptyState>
            </div>
        );
    }

    // Eloquent serialise la relation `secteursActivite` en snake_case → `secteurs_activite`,
    // valeur = [{id, nom, pivot}]. On accepte aussi la forme camelCase (anciens clients API)
    // et la chaine unique `secteur_activite` (schema legacy).
    const secteursRaw = client.secteursActivite || client.secteurs_activite || [];
    const secteurs = Array.isArray(secteursRaw) && secteursRaw.length > 0
        ? secteursRaw.map(s => typeof s === 'string' ? s : s?.nom).filter(Boolean)
        : (client.secteur_activite ? [client.secteur_activite] : []);
    const statut = statutCfg[client.statut] || statutCfg.prospect;
    const initiale = client.raison_sociale?.charAt(0)?.toUpperCase() || '?';

    return (
        <div className="p-6 lg:p-8 max-w-7xl mx-auto">
            <Link to="/clients" className="inline-flex items-center gap-1.5 text-sm font-medium text-blue-700 hover:text-blue-900 mb-4 transition-colors">
                <ArrowLeftIcon className="w-4 h-4" /> Retour aux clients
            </Link>

            {/* HERO */}
            <section className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-blue-900 to-indigo-900 px-6 lg:px-10 py-8 lg:py-10 mb-6 text-white shadow-xl shadow-blue-900/10">
                <div className="absolute -top-20 -right-20 w-72 h-72 bg-blue-500/30 rounded-full blur-3xl pointer-events-none" />
                <div className="absolute -bottom-32 -left-32 w-96 h-96 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none" />
                <div
                    className="absolute inset-0 opacity-[0.06] pointer-events-none"
                    style={{
                        backgroundImage: 'linear-gradient(rgba(255,255,255,0.5) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.5) 1px, transparent 1px)',
                        backgroundSize: '48px 48px',
                    }}
                />

                <div className="relative flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div className="flex items-start gap-4 min-w-0 flex-1">
                        <div className="shrink-0 w-16 h-16 rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/20 flex items-center justify-center text-2xl font-bold">
                            {initiale}
                        </div>
                        <div className="min-w-0">
                            <div className="flex items-center gap-2 flex-wrap mb-2">
                                <Badge variant={statut.variant} solid size="md" dot>{statut.label}</Badge>
                                {client.type_structure && <Badge variant="info" solid size="md">{client.type_structure}</Badge>}
                            </div>
                            <h1 className="text-3xl lg:text-4xl font-bold tracking-tight leading-tight">{client.raison_sociale}</h1>
                            <div className="flex items-center gap-3 mt-2 text-blue-100/80 text-sm flex-wrap">
                                {client.sigle && <span className="font-mono text-blue-200">{client.sigle}</span>}
                                {client.ville && (
                                    <span className="flex items-center gap-1">
                                        <MapPinIcon className="w-4 h-4" />
                                        {client.ville}{client.pays ? `, ${client.pays}` : ''}
                                    </span>
                                )}
                            </div>
                            {secteurs.length > 0 && (
                                <div className="flex items-center gap-1.5 flex-wrap mt-3">
                                    {secteurs.slice(0, 4).map(s => (
                                        <span key={s} className="text-[11px] font-medium px-2.5 py-1 rounded-full bg-white/10 ring-1 ring-white/15 backdrop-blur">{s}</span>
                                    ))}
                                    {secteurs.length > 4 && (
                                        <span className="text-[11px] text-white/70">+{secteurs.length - 4}</span>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Link to={`/missions?client_id=${client.id}`}>
                            <Button as="span" size="lg" variant="primary" className="!bg-white/10 !text-white !ring-1 !ring-white/20 !backdrop-blur hover:!bg-white/20 hover:!from-white/10 hover:!via-white/10 hover:!to-white/10 !shadow-none">
                                <ClipboardDocumentListIcon className="w-4 h-4" />
                                Toutes les missions
                            </Button>
                        </Link>
                    </div>
                </div>
            </section>

            {/* KPI */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <StatCard titre="Missions" valeur={client.missions?.length || 0} icon={ClipboardDocumentListIcon} couleur="blue" soustitre="Dossiers ouverts" />
                <StatCard titre="Consultants" valeur={client.utilisateurs?.length || 0} icon={UsersIcon} couleur="indigo" soustitre="ASC assignés" />
                <StatCard titre="Secteurs" valeur={secteurs.length} icon={BriefcaseIcon} couleur="purple" soustitre="Activités couvertes" />
                <StatCard titre="Effectif" valeur={client.effectif || '—'} icon={UserIcon} couleur="cyan" soustitre="Collaborateurs" />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
                {/* Infos client */}
                <Card variant="elevated" className="overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                        <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                            <IdentificationIcon className="w-4.5 h-4.5 text-white" />
                        </div>
                        <h2 className="text-base font-bold text-gray-900">Informations</h2>
                    </div>
                    <dl className="p-5 space-y-3 text-sm">
                        <InfoRow icon={BuildingOffice2Icon} label="Sigle" value={client.sigle} />
                        {secteurs.length > 0 && (
                            <div className="flex items-start gap-3">
                                <div className="shrink-0 w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center">
                                    <BriefcaseIcon className="w-4 h-4 text-gray-400" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <dt className="text-[10px] uppercase tracking-wider font-semibold text-gray-500">
                                        Secteur{secteurs.length > 1 ? 's' : ''} d'activité
                                    </dt>
                                    <dd className="mt-1 flex flex-wrap gap-1.5">
                                        {secteurs.map(s => (
                                            <Badge key={s} variant="info" size="sm">{s}</Badge>
                                        ))}
                                    </dd>
                                </div>
                            </div>
                        )}
                        {client.description_activite && (
                            <div className="flex items-start gap-3">
                                <div className="shrink-0 w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center">
                                    <ClipboardDocumentListIcon className="w-4 h-4 text-gray-400" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <dt className="text-[10px] uppercase tracking-wider font-semibold text-gray-500">Description de l'activité</dt>
                                    <dd className="text-sm text-gray-800 whitespace-pre-line">{client.description_activite}</dd>
                                </div>
                            </div>
                        )}
                        <InfoRow icon={MapPinIcon} label="Ville" value={client.ville} />
                        <InfoRow icon={GlobeAltIcon} label="Pays" value={client.pays} />
                        <InfoRow icon={EnvelopeIcon} label="Email" value={client.email} />
                        <InfoRow icon={PhoneIcon} label="Téléphone" value={client.telephone} />
                        <InfoRow icon={UserIcon} label="Contact principal" value={client.contact_principal_nom} />
                        <InfoRow icon={EnvelopeIcon} label="Email contact" value={client.contact_principal_email} />
                    </dl>
                </Card>

                {/* Missions */}
                <Card variant="elevated" className="lg:col-span-2 overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center">
                                <ClipboardDocumentListIcon className="w-4.5 h-4.5 text-white" />
                            </div>
                            <div>
                                <h2 className="text-base font-bold text-gray-900">Missions</h2>
                                <p className="text-xs text-gray-500">{client.missions?.length || 0} mission(s) ouverte(s) pour ce client</p>
                            </div>
                        </div>
                        <Link to={`/missions?client_id=${client.id}`} className="text-sm text-blue-700 hover:text-blue-900 font-semibold flex items-center gap-1">
                            Tout voir <ChevronRightIcon className="w-3.5 h-3.5" />
                        </Link>
                    </div>
                    <div className="p-4">
                        {(client.missions?.length || 0) === 0 ? (
                            <EmptyState icon={ClipboardDocumentListIcon} title="Aucune mission" description="Aucun dossier n'a encore été ouvert pour ce client." accent="emerald" compact />
                        ) : (
                            <div className="space-y-2">
                                {client.missions.map(m => (
                                    <Link key={m.id} to={`/missions/${m.id}`} className="group flex items-center gap-3 p-3 rounded-xl border border-gray-100 hover:border-blue-200 hover:bg-blue-50/30 transition-all">
                                        <div className="shrink-0 w-9 h-9 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center">
                                            <ClipboardDocumentListIcon className="w-4 h-4" />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-semibold text-gray-900 truncate group-hover:text-blue-700 transition-colors">{m.titre}</p>
                                            <p className="text-[11px] text-gray-500 font-mono mt-0.5">{m.reference}</p>
                                        </div>
                                        <Badge variant={missionStatutVariant[m.statut] || 'gray'} size="sm" dot>{m.statut?.replace('_', ' ')}</Badge>
                                        <ChevronRightIcon className="w-4 h-4 text-gray-300 group-hover:text-blue-600 group-hover:translate-x-0.5 transition-all" />
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                </Card>
            </div>

            {/* Consultants */}
            {client.utilisateurs?.length > 0 && (
                <Card variant="elevated" className="overflow-hidden mt-5">
                    <div className="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                        <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-purple-500 to-fuchsia-600 flex items-center justify-center">
                            <UsersIcon className="w-4.5 h-4.5 text-white" />
                        </div>
                        <h2 className="text-base font-bold text-gray-900">Consultants assignés ({client.utilisateurs.length})</h2>
                    </div>
                    <div className="p-5 flex flex-wrap gap-2">
                        {client.utilisateurs.map(u => (
                            <span key={u.id} className="inline-flex items-center gap-2 px-3 py-2 bg-gradient-to-br from-blue-50 to-indigo-50 ring-1 ring-blue-100 rounded-xl text-sm">
                                <div className="w-7 h-7 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center text-xs font-bold shadow-sm">
                                    {u.prenom?.charAt(0)}{u.nom?.charAt(0)}
                                </div>
                                <span className="font-semibold text-gray-800">{u.prenom} {u.nom}</span>
                            </span>
                        ))}
                    </div>
                </Card>
            )}
        </div>
    );
}

function InfoRow({ icon: Icon, label, value }) {
    if (!value) return null;
    return (
        <div className="flex items-start gap-3">
            <div className="shrink-0 w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center">
                <Icon className="w-4 h-4 text-gray-400" />
            </div>
            <div className="min-w-0 flex-1">
                <dt className="text-[10px] uppercase tracking-wider font-semibold text-gray-500">{label}</dt>
                <dd className="text-sm text-gray-800 font-medium truncate">{value}</dd>
            </div>
        </div>
    );
}
