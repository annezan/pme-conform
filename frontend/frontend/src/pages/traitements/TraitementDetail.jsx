/**
 * Page TraitementDetail — Consultation detaillee d'une fiche + historique
 * des modifications (timeline).
 */

import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
    getTraitement, validerTraitement, archiverTraitement, supprimerTraitement,
    getHistoriqueTraitement,
} from '@/api/traitements';
import { alertSuccess, alertError, confirmAction, confirmDelete } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import EmptyState from '@/components/ui/EmptyState';
import Loader from '@/components/ui/Loader';
import {
    ArrowLeftIcon, CheckCircleIcon, ArchiveBoxIcon, PencilSquareIcon,
    TrashIcon, ClockIcon, UserCircleIcon, ClipboardDocumentListIcon,
    BuildingOffice2Icon, ShieldCheckIcon, DocumentTextIcon,
} from '@heroicons/react/24/outline';

const statutVariant = { brouillon: 'gray', valide: 'success', archive: 'purple' };
const statutLabel = { brouillon: 'Brouillon', valide: 'Validé', archive: 'Archivé' };

export default function TraitementDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [histoOuverte, setHistoOuverte] = useState(false);
    const [timeline, setTimeline] = useState([]);

    const charger = async () => {
        setLoading(true);
        try {
            const r = await getTraitement(id);
            setData(r);
        } catch {
            alertError('Impossible de charger le traitement');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { charger(); }, [id]);

    const handleValider = async () => {
        if (!(await confirmAction('Valider ce traitement ? Il entrera dans le registre officiel.', 'Validation'))) return;
        try {
            await validerTraitement(id);
            alertSuccess('Traitement validé');
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur');
        }
    };

    const handleArchiver = async () => {
        if (!(await confirmAction('Archiver ce traitement ? Il ne pourra plus être modifié.', 'Archivage'))) return;
        try {
            await archiverTraitement(id, 'Archivage manuel');
            alertSuccess('Traitement archivé');
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur');
        }
    };

    const handleDelete = async () => {
        if (!(await confirmDelete(data.traitement.reference))) return;
        try {
            await supprimerTraitement(id);
            alertSuccess('Traitement supprimé');
            navigate('/traitements');
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur');
        }
    };

    const ouvrirHistorique = async () => {
        if (!histoOuverte) {
            try {
                const r = await getHistoriqueTraitement(id);
                setTimeline(r.timeline || []);
            } catch { alertError('Historique indisponible'); }
        }
        setHistoOuverte(!histoOuverte);
    };

    if (loading) return <Loader />;
    if (!data) {
        return (
            <div className="p-8 max-w-5xl mx-auto">
                <EmptyState icon={ClipboardDocumentListIcon} title="Traitement introuvable" description="Cette fiche n'existe pas ou vous n'y avez plus accès." accent="rose">
                    <button onClick={() => navigate('/traitements')}><Button as="span" variant="primary">Retour aux traitements</Button></button>
                </EmptyState>
            </div>
        );
    }

    const { traitement, peut_modifier, peut_valider, peut_archiver, peut_supprimer } = data;

    return (
        <div className="p-6 lg:p-8 max-w-5xl mx-auto">
            <button onClick={() => navigate('/traitements')} className="inline-flex items-center gap-1.5 text-sm font-medium text-blue-700 hover:text-blue-900 mb-4 transition-colors">
                <ArrowLeftIcon className="w-4 h-4" /> Retour à la liste
            </button>

            <PageHeader
                title={traitement.designation || traitement.nom}
                subtitle={`${traitement.reference}${traitement.client?.raison_sociale ? ' · ' + traitement.client.raison_sociale : ''}`}
                eyebrow="Registre MOBISOFT"
                icon={ClipboardDocumentListIcon}
                accent="indigo"
            >
                <div className="flex items-center gap-2 flex-wrap">
                    <Badge variant={statutVariant[traitement.statut] || 'gray'} solid size="md" dot>{statutLabel[traitement.statut] || traitement.statut}</Badge>
                    {peut_modifier && (
                        <Button variant="secondary" size="sm" onClick={() => navigate(`/traitements/${id}/modifier`)}>
                            <PencilSquareIcon className="w-4 h-4" /> Modifier
                        </Button>
                    )}
                    {peut_valider && (
                        <Button variant="success" size="sm" onClick={handleValider}>
                            <CheckCircleIcon className="w-4 h-4" /> Valider
                        </Button>
                    )}
                    {peut_archiver && traitement.statut !== 'archive' && (
                        <Button variant="secondary" size="sm" onClick={handleArchiver}>
                            <ArchiveBoxIcon className="w-4 h-4" /> Archiver
                        </Button>
                    )}
                    {peut_supprimer && (
                        <Button variant="danger" size="sm" onClick={handleDelete}>
                            <TrashIcon className="w-4 h-4" /> Supprimer
                        </Button>
                    )}
                </div>
            </PageHeader>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <InfoCard label="Saisi par" value={formatUser(traitement.saisi_par)} icon={UserCircleIcon} accent="blue" />
                <InfoCard label="Validé par" value={traitement.valide_par ? formatUser(traitement.valide_par) : '—'} icon={ShieldCheckIcon} accent="emerald" />
                <InfoCard label="Direction / Pôle" value={traitement.direction_pole || '—'} icon={BuildingOffice2Icon} accent="indigo" />
            </div>

            {/* Sections MOBISOFT */}
            <div className="space-y-5">
                {traitement.description && (
                    <Section titre="Description de la finalité">
                        <p className="text-sm text-gray-800 leading-relaxed whitespace-pre-wrap">{traitement.description}</p>
                    </Section>
                )}

                {(traitement.services_charges?.length > 0 || traitement.sources?.length > 0 || traitement.code_finalite) && (
                    <Section titre="Identification">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            {traitement.code_finalite && <div><span className="text-gray-500">Code finalité :</span> <span className="font-semibold">{traitement.code_finalite}</span></div>}
                            {traitement.date_creation_fiche && <div><span className="text-gray-500">Créé le :</span> <span className="font-semibold">{new Date(traitement.date_creation_fiche).toLocaleDateString('fr-FR')}</span></div>}
                            {traitement.services_charges?.length > 0 && <div className="md:col-span-2"><p className="text-xs text-gray-500 mb-1">Services chargés</p><ChipList items={traitement.services_charges} variant="gray" /></div>}
                            {traitement.sources?.length > 0 && <div className="md:col-span-2"><p className="text-xs text-gray-500 mb-1">Sources</p><ChipList items={traitement.sources} variant="gray" /></div>}
                        </div>
                    </Section>
                )}

                {traitement.supports?.length > 0 && (
                    <Section titre="Supports du traitement">
                        <SubTable rows={traitement.supports} columns={[
                            { key: 'categorie', label: 'Catégorie' },
                            { key: 'type', label: 'Type' },
                            { key: 'marque_version', label: 'Marque/Version' },
                            { key: 'precision', label: 'Précision' },
                        ]} />
                    </Section>
                )}

                {traitement.actes?.length > 0 && (
                    <Section titre="Actes & base légale">
                        <SubTable rows={traitement.actes} columns={[
                            { key: 'acte', label: 'Acte' },
                            { key: 'base_legale', label: 'Base légale' },
                            { key: 'precision', label: 'Précision' },
                        ]} />
                    </Section>
                )}

                {traitement.personnes?.length > 0 && (
                    <Section titre="Personnes concernées">
                        <SubTable rows={traitement.personnes} columns={[
                            { key: 'categorie', label: 'Catégorie' },
                            { key: 'documentation_source', label: 'Documentation source' },
                        ]} />
                    </Section>
                )}

                {traitement.categories_donnees?.length > 0 && (
                    <Section titre="Catégories de données">
                        <SubTable rows={traitement.categories_donnees} columns={[
                            { key: 'categorie_principale', label: 'Catégorie' },
                            { key: 'detail', label: 'Détail' },
                            { key: 'origine', label: 'Origine' },
                            { key: 'est_sensible', label: 'Sensible', render: (v) => v ? <Badge variant="danger">Oui</Badge> : '-' },
                        ]} />
                    </Section>
                )}

                {traitement.transferts?.length > 0 && (
                    <Section titre="Transferts hors CEDEAO">
                        <SubTable rows={traitement.transferts} columns={[
                            { key: 'organe', label: 'Organe' },
                            { key: 'pays', label: 'Pays' },
                            { key: 'garantie', label: 'Garantie' },
                            { key: 'sens_groupe', label: 'Sens / Groupe' },
                        ]} />
                    </Section>
                )}

                {traitement.mesures_securite?.length > 0 && (
                    <Section titre="Mesures de sécurité">
                        <SubTable rows={traitement.mesures_securite} columns={[
                            { key: 'categorie', label: 'Catégorie' },
                            { key: 'description', label: 'Description' },
                        ]} />
                    </Section>
                )}

                {/* Historique */}
                <Card className="p-5">
                    <button
                        onClick={ouvrirHistorique}
                        className="w-full flex items-center justify-between text-left"
                    >
                        <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                            <ClockIcon className="w-5 h-5 text-gray-500" />
                            Historique des modifications
                        </h3>
                        <span className="text-sm text-blue-600">{histoOuverte ? 'Masquer' : 'Afficher'}</span>
                    </button>

                    {histoOuverte && (
                        <div className="mt-5 space-y-4">
                            {timeline.length === 0 ? (
                                <p className="text-sm text-gray-400 italic">Aucune révision enregistrée.</p>
                            ) : timeline.map((rev, idx) => (
                                <div key={rev.id} className="flex gap-4">
                                    <div className="flex flex-col items-center">
                                        <div className={`w-3 h-3 rounded-full ${idx === 0 ? 'bg-blue-600' : 'bg-gray-300'}`} />
                                        {idx < timeline.length - 1 && <div className="w-0.5 flex-1 bg-gray-200 min-h-6" />}
                                    </div>
                                    <div className="flex-1 pb-4">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <span className="text-sm font-semibold text-gray-900">
                                                {rev.auteur?.nom_complet || 'Utilisateur inconnu'}
                                            </span>
                                            <span className="text-xs text-gray-500">
                                                {new Date(rev.date).toLocaleString('fr-FR')}
                                            </span>
                                        </div>
                                        {rev.commentaire && (
                                            <p className="text-xs text-gray-600 italic mt-1">{rev.commentaire}</p>
                                        )}
                                        {rev.changements?.length > 0 && (
                                            <ul className="mt-2 text-xs text-gray-700 space-y-1">
                                                {rev.changements.slice(0, 5).map((ch, i) => (
                                                    <li key={i}>• <strong>{ch.label}</strong> modifié</li>
                                                ))}
                                                {rev.changements.length > 5 && (
                                                    <li className="text-gray-400">+ {rev.changements.length - 5} autre(s) changement(s)</li>
                                                )}
                                            </ul>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
            </div>
        </div>
    );
}

function InfoCard({ label, value, icon: Icon, accent = 'blue' }) {
    const accents = {
        blue: 'from-blue-500 to-indigo-600',
        emerald: 'from-emerald-500 to-teal-600',
        indigo: 'from-indigo-500 to-purple-600',
    };
    return (
        <Card variant="elevated" className="p-4 flex items-center gap-3">
            <div className={`shrink-0 w-11 h-11 rounded-xl bg-gradient-to-br ${accents[accent] || accents.blue} flex items-center justify-center shadow-md shadow-blue-500/10`}>
                <Icon className="w-5 h-5 text-white" />
            </div>
            <div className="min-w-0">
                <p className="text-[11px] uppercase tracking-wider font-semibold text-gray-500">{label}</p>
                <p className="text-sm font-bold text-gray-900 truncate">{value}</p>
            </div>
        </Card>
    );
}

function Section({ titre, children }) {
    return (
        <Card variant="elevated" className="overflow-hidden">
            <div className="px-5 py-3.5 border-b border-gray-100 bg-gray-50/40">
                <h3 className="font-bold text-gray-900 text-sm uppercase tracking-wider">{titre}</h3>
            </div>
            <div className="p-5">{children}</div>
        </Card>
    );
}

function SubTable({ rows = [], columns = [] }) {
    if (!rows || rows.length === 0) return <p className="text-sm text-gray-400 italic">Aucune entrée</p>;
    return (
        <div className="overflow-x-auto rounded-xl ring-1 ring-gray-200/60">
            <table className="w-full text-sm">
                <thead className="bg-gradient-to-b from-slate-50 to-slate-100 text-[11px] text-slate-600 uppercase tracking-wider font-semibold">
                    <tr>{columns.map(c => <th key={c.key} className="px-3 py-2.5 text-left">{c.label}</th>)}</tr>
                </thead>
                <tbody className="divide-y divide-gray-100 bg-white">
                    {rows.map((r, i) => (
                        <tr key={i} className="hover:bg-blue-50/40 transition-colors">
                            {columns.map(c => (
                                <td key={c.key} className="px-3 py-2.5 text-gray-800">
                                    {c.render ? c.render(r[c.key], r) : (r[c.key] ?? '-')}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function ChipList({ items = [], variant = 'gray' }) {
    if (!items || items.length === 0) return <span className="text-sm text-gray-400 italic">Non renseigné</span>;
    return (
        <div className="flex flex-wrap gap-1.5">
            {items.map((it, i) => <Badge key={i} variant={variant}>{String(it).replace(/_/g, ' ')}</Badge>)}
        </div>
    );
}

function formatUser(u) {
    if (!u) return '-';
    return `${u.prenom || ''} ${u.nom || ''}`.trim() || '-';
}
