/**
 * Page AnalyseDetail — Consultation detaillee d'une analyse :
 *  - En-tete avec statut + score + stats
 *  - Apercu synthese LLM
 *  - Liste filtrable des ecarts (gravite/categorie)
 *  - Telechargement du rapport Word
 *  - Polling tant que l'analyse n'est pas terminee
 */

import { useState, useEffect, useMemo, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useAuth } from '@/contexts/AuthContext';
import { getAnalyse, telechargerRapport, regenererRapport, annulerAnalyse, relancerAnalyse, enrichirAnalyseIA, annulerEnrichissementIA, refaireAnalyse, listVersionsAnalyse } from '@/api/analyses';
import { alertSuccess, alertError, confirmAction } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import { Input, Select } from '@/components/ui/Input';
import Drawer from '@/components/ui/Drawer';
import Modal from '@/components/ui/Modal';
import {
    ArrowDownTrayIcon, ArrowPathIcon, ArrowLeftIcon, CheckCircleIcon,
    ExclamationTriangleIcon, ExclamationCircleIcon, InformationCircleIcon,
    ClockIcon, DocumentMagnifyingGlassIcon, XMarkIcon,
} from '@heroicons/react/24/outline';

const graviteConfig = {
    critique: { icon: ExclamationCircleIcon, color: 'red', label: 'Critique', bg: 'bg-red-50', text: 'text-red-700', border: 'border-red-200' },
    majeur: { icon: ExclamationTriangleIcon, color: 'orange', label: 'Majeur', bg: 'bg-orange-50', text: 'text-orange-700', border: 'border-orange-200' },
    mineur: { icon: InformationCircleIcon, color: 'yellow', label: 'Mineur', bg: 'bg-yellow-50', text: 'text-yellow-700', border: 'border-yellow-200' },
    observation: { icon: InformationCircleIcon, color: 'blue', label: 'Observation', bg: 'bg-blue-50', text: 'text-blue-700', border: 'border-blue-200' },
};

const statutVariant = {
    en_attente: 'gray', en_cours: 'info', terminee: 'success', erreur: 'danger', annulee: 'gray',
};

export default function AnalyseDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const { hasPermission } = useAuth();
    // "Vue client" = utilisateur sans permission de gestion des analyses (pas de
    // Relancer/Enrichir/Regenerer). Detection par view-all-analyses : presente chez
    // les internes (admin/manager/consultant via expansion), absente chez les clients.
    const estClient = !hasPermission('view-all-analyses');
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [filtreGravite, setFiltreGravite] = useState('tous');
    const [filtreCategorie, setFiltreCategorie] = useState('tous');
    const [ecartActif, setEcartActif] = useState(null);
    const [regen, setRegen] = useState(false);
    const [enrichirLoading, setEnrichirLoading] = useState(false);
    const pollingRef = useRef(null);
    const [versions, setVersions] = useState([]);
    const [versionsLoading, setVersionsLoading] = useState(false);
    const [showRefaire, setShowRefaire] = useState(false);
    const [motifRefaire, setMotifRefaire] = useState('');
    const [refaireLoading, setRefaireLoading] = useState(false);

    const chargerVersions = async () => {
        setVersionsLoading(true);
        try {
            const r = await listVersionsAnalyse(id);
            setVersions(r.data || []);
        } catch {
            // Pas bloquant : on affiche la section vide
            setVersions([]);
        } finally {
            setVersionsLoading(false);
        }
    };

    const charger = async () => {
        try {
            const r = await getAnalyse(id);
            setData(r);
        } catch {
            alertError('Impossible de charger l\'analyse');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        charger();
        chargerVersions();
        pollingRef.current = setInterval(() => {
            setData(current => {
                const a = current?.analyse;
                if (!a) return current;
                const enCours = ['en_attente', 'en_cours'].includes(a.statut);
                const enrichissement = a.statut === 'terminee'
                    && (a.etape_courante || '').toLowerCase().includes('enrichissement')
                    && (a.progression_pct ?? 100) < 100;
                if (enCours || enrichissement) {
                    charger();
                }
                return current;
            });
        }, 3000);
        return () => clearInterval(pollingRef.current);
    }, [id]);

    const analyse = data?.analyse;
    const ecarts = analyse?.ecarts || [];

    const ecartsFiltres = useMemo(() => {
        return ecarts.filter(e =>
            (filtreGravite === 'tous' || e.gravite === filtreGravite) &&
            (filtreCategorie === 'tous' || e.categorie === filtreCategorie)
        );
    }, [ecarts, filtreGravite, filtreCategorie]);

    const categoriesDistinctes = useMemo(
        () => [...new Set(ecarts.map(e => e.categorie))].sort(),
        [ecarts]
    );

    const handleTelecharger = async () => {
        try {
            await telechargerRapport(analyse);
        } catch {
            alertError('Impossible de télécharger. Le rapport est peut-être en cours de génération.');
        }
    };

    const handleRegenerer = async () => {
        setRegen(true);
        try {
            await regenererRapport(analyse.id);
            alertSuccess('Rapport régénéré');
            charger();
        } catch {
            alertError('Échec de régénération');
        } finally {
            setRegen(false);
        }
    };

    const handleAnnuler = async () => {
        if (!(await confirmAction('Voulez-vous vraiment annuler l\'analyse en cours ?', 'Annuler l\'analyse'))) return;
        try {
            await annulerAnalyse(analyse.id);
            alertSuccess('Analyse annulée');
            charger();
        } catch {
            alertError('Échec de l\'annulation');
        }
    };

    const handleRelancer = async (modeIa) => {
        // modeIa : false = rapide (~30s, sans LLM), true = enrichi IA (plusieurs minutes)
        const libelle = modeIa ? 'mode enrichi IA (plusieurs minutes)' : 'mode rapide (≈ 30 s)';
        if (!(await confirmAction(
            `Relancer l'analyse en ${libelle} ? Les écarts existants seront supprimés et remplacés.`,
            'Relancer',
        ))) return;
        try {
            const r = await relancerAnalyse(analyse.id, modeIa);
            alertSuccess(r.message || 'Analyse relancée');
            charger();
        } catch {
            alertError('Échec de la relance');
        }
    };

    const handleRefaire = async (e) => {
        e?.preventDefault?.();
        setRefaireLoading(true);
        try {
            await refaireAnalyse(analyse.id, motifRefaire || null);
            alertSuccess('Nouvelle version lancée. La version précédente est archivée dans l\'historique.');
            setShowRefaire(false);
            setMotifRefaire('');
            await charger();
            await chargerVersions();
        } catch (err) {
            alertError(err.response?.data?.message || 'Échec de la relance');
        } finally {
            setRefaireLoading(false);
        }
    };

    const handleEnrichirIA = async () => {
        if (!(await confirmAction('Enrichir les écarts avec l\'IA ? Cette opération peut prendre 20+ minutes selon la puissance d\'Ollama.', 'Enrichissement IA'))) return;
        setEnrichirLoading(true);
        try {
            await enrichirAnalyseIA(analyse.id);
            alertSuccess('Enrichissement IA lancé. Progression visible sur cette page.');
            await charger();
        } catch {
            alertError('Échec du lancement');
        } finally {
            setEnrichirLoading(false);
        }
    };

    const handleAnnulerEnrichissement = async () => {
        if (!(await confirmAction('Annuler l\'enrichissement IA en cours ? Les écarts déjà traités restent enrichis.', 'Annuler l\'enrichissement'))) return;
        try {
            await annulerEnrichissementIA(analyse.id);
            alertSuccess('Annulation demandée. L\'enrichissement s\'arrêtera dans quelques secondes.');
            charger();
        } catch {
            alertError('Échec de l\'annulation');
        }
    };

    if (loading) {
        return (
            <div className="p-8 flex justify-center">
                <div className="w-10 h-10 rounded-full border-4 border-blue-600 border-t-transparent animate-spin" />
            </div>
        );
    }

    if (!analyse) {
        return <div className="p-8 text-center text-gray-500">Analyse introuvable.</div>;
    }

    const enTraitement = ['en_attente', 'en_cours'].includes(analyse.statut);
    const enrichissementEnCours = analyse.statut === 'terminee'
        && (analyse.etape_courante || '').toLowerCase().includes('enrichissement')
        && (analyse.progression_pct ?? 100) < 100;

    return (
        <div className="p-6 lg:p-8 max-w-7xl mx-auto">
            <button onClick={() => navigate('/analyses')} className="inline-flex items-center gap-1.5 text-sm font-medium text-blue-700 hover:text-blue-900 mb-4 transition-colors">
                <ArrowLeftIcon className="w-4 h-4" /> Retour à la liste
            </button>

            <PageHeader
                title={analyse.titre}
                subtitle={`${analyse.reference} · ${analyse.mission?.client?.raison_sociale || ''}`}
                eyebrow="Analyse d'écarts"
                icon={DocumentMagnifyingGlassIcon}
                accent="purple"
            >
                <div className="flex items-center gap-2">
                    {!estClient && enTraitement && (
                        <Button variant="danger" onClick={handleAnnuler}>
                            <XMarkIcon className="w-4 h-4" /> Annuler
                        </Button>
                    )}
                    {!estClient && ['erreur', 'annulee', 'terminee'].includes(analyse.statut) && (
                        <>
                            <Button variant="secondary" onClick={() => handleRelancer(false)} title="Relance rapide sans LLM (~30 s)">
                                <ArrowPathIcon className="w-4 h-4" /> Relancer (rapide)
                            </Button>
                            <Button variant="secondary" onClick={() => handleRelancer(true)} title="Relance avec LLM pour la rédaction de chaque écart (peut prendre plusieurs minutes)">
                                <ArrowPathIcon className="w-4 h-4" /> Relancer (enrichi IA)
                            </Button>
                        </>
                    )}
                    {!estClient && ['erreur', 'annulee', 'terminee'].includes(analyse.statut) && (
                        <Button variant="primary" onClick={() => setShowRefaire(true)} title="Archive la version actuelle et relance l'analyse en prenant en compte les corrections récentes">
                            <ArrowPathIcon className="w-4 h-4" /> Refaire l'analyse
                        </Button>
                    )}
                    {analyse.statut === 'terminee' && (
                        <>
                            {!estClient && !analyse.enrichissement_ia && !enrichissementEnCours && (
                                <Button variant="secondary" onClick={handleEnrichirIA} disabled={enrichirLoading} title="Faire rédiger les écarts par l'IA (20+ min)">
                                    {enrichirLoading ? (
                                        <>
                                            <div className="w-4 h-4 rounded-full border-2 border-gray-500 border-t-transparent animate-spin" />
                                            Lancement...
                                        </>
                                    ) : (
                                        <>🧠 Enrichir (IA)</>
                                    )}
                                </Button>
                            )}
                            {!estClient && enrichissementEnCours && (
                                <Button variant="secondary" disabled title="Enrichissement IA en cours">
                                    <div className="w-4 h-4 rounded-full border-2 border-purple-500 border-t-transparent animate-spin" />
                                    Enrichissement IA en cours...
                                </Button>
                            )}
                            {!estClient && (
                                <Button variant="secondary" onClick={handleRegenerer} disabled={regen}>
                                    <ArrowPathIcon className={`w-4 h-4 ${regen ? 'animate-spin' : ''}`} /> Régénérer
                                </Button>
                            )}
                            {data?.rapport_disponible && (
                                <Button onClick={handleTelecharger}>
                                    <ArrowDownTrayIcon className="w-4 h-4" /> Rapport PowerPoint
                                </Button>
                            )}
                        </>
                    )}
                </div>
            </PageHeader>

            {/* Bandeau statut avec progression */}
            {enTraitement && (
                <div className="mb-6 p-5 bg-blue-50 border border-blue-200 rounded-xl">
                    <div className="flex items-center gap-3 mb-3">
                        <div className="w-5 h-5 rounded-full border-2 border-blue-500 border-t-transparent animate-spin" />
                        <p className="font-semibold text-blue-900">
                            {analyse.statut === 'en_attente' ? 'En attente de traitement...' : 'Analyse en cours...'}
                        </p>
                        <span className="ml-auto text-sm font-bold text-blue-700">{analyse.progression_pct || 0}%</span>
                    </div>
                    <div className="w-full h-2 bg-blue-100 rounded-full overflow-hidden mb-2">
                        <div
                            className="h-full bg-blue-600 transition-all duration-500"
                            style={{ width: `${analyse.progression_pct || 0}%` }}
                        />
                    </div>
                    <p className="text-sm text-blue-700">
                        {analyse.etape_courante || 'Initialisation...'}
                        {analyse.nb_exigences_total > 0 && (
                            <span className="ml-2 text-blue-600">
                                ({analyse.nb_exigences_verifiees || 0}/{analyse.nb_exigences_total} exigences)
                            </span>
                        )}
                    </p>
                    {analyse.statut === 'en_attente' && analyse.enrichissement_ia && (
                        <div className="mt-3 p-3 bg-amber-50 border border-amber-300 rounded-lg text-xs text-amber-900">
                            <p className="font-semibold mb-1">Mode enrichi IA : worker requis</p>
                            <p>
                                Ce mode peut durer 20+ minutes et passe par la file d'attente Laravel.
                                Si rien ne démarre, ouvrez un terminal dans le dossier backend et lancez :
                            </p>
                            <code className="block mt-1 px-2 py-1 bg-amber-100 rounded font-mono text-[11px]">
                                php artisan queue:work --queue=analyses,default
                            </code>
                        </div>
                    )}
                    {analyse.statut === 'en_attente' && !analyse.enrichissement_ia && (
                        <p className="text-xs text-blue-600 mt-2 italic">
                            Le traitement démarre après la réponse HTTP — la progression apparaîtra dans quelques secondes.
                        </p>
                    )}
                </div>
            )}

            {/* Bandeau enrichissement IA en cours (analyse déjà terminée) */}
            {enrichissementEnCours && (
                <div className="mb-6 p-5 bg-purple-50 border border-purple-200 rounded-xl">
                    <div className="flex items-center gap-3 mb-3">
                        <div className="w-5 h-5 rounded-full border-2 border-purple-500 border-t-transparent animate-spin" />
                        <p className="font-semibold text-purple-900">
                            🧠 Enrichissement IA en cours...
                        </p>
                        <span className="ml-auto text-sm font-bold text-purple-700">{analyse.progression_pct || 0}%</span>
                        {!estClient && !analyse.enrichissement_annule && (
                            <button
                                onClick={handleAnnulerEnrichissement}
                                className="text-xs font-medium px-3 py-1.5 rounded-md bg-white border border-purple-300 text-purple-700 hover:bg-purple-100 transition-colors"
                            >
                                <XMarkIcon className="w-3.5 h-3.5 inline mr-1" />
                                Annuler
                            </button>
                        )}
                    </div>
                    <div className="w-full h-2 bg-purple-100 rounded-full overflow-hidden mb-2">
                        <div
                            className="h-full bg-purple-600 transition-all duration-500"
                            style={{ width: `${analyse.progression_pct || 0}%` }}
                        />
                    </div>
                    <p className="text-sm text-purple-700">
                        {analyse.etape_courante || 'Préparation...'}
                    </p>
                    {analyse.enrichissement_annule ? (
                        <p className="text-xs text-amber-700 mt-2 italic">
                            Annulation demandée. L'enrichissement s'arrêtera après l'écart en cours.
                        </p>
                    ) : (
                        <p className="text-xs text-purple-600 mt-2 italic">
                            Le LLM rédige chaque constat et recommandation (timeout 30s par écart, fallback automatique si Ollama est trop lent). Vous pouvez fermer cette page, l'enrichissement continuera en arrière-plan.
                        </p>
                    )}
                </div>
            )}

            {analyse.statut === 'erreur' && (
                <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl flex items-start gap-3">
                    <ExclamationTriangleIcon className="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
                    <div className="flex-1">
                        <p className="font-semibold text-red-900">L'analyse a échoué</p>
                        <p className="text-sm text-red-700 mt-1">{analyse.erreur_message || 'Erreur inconnue.'}</p>
                    </div>
                </div>
            )}

            {analyse.statut === 'annulee' && (
                <div className="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-xl flex items-start gap-3">
                    <XMarkIcon className="w-5 h-5 text-gray-600 shrink-0 mt-0.5" />
                    <div>
                        <p className="font-semibold text-gray-900">Analyse annulée</p>
                        <p className="text-sm text-gray-600 mt-1">Cliquez sur « Relancer » pour relancer l'analyse.</p>
                    </div>
                </div>
            )}

            {/* Stats */}
            <div className="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                <StatCard label="Score" value={analyse.score_conformite !== null ? `${parseFloat(analyse.score_conformite).toFixed(0)}%` : '-'} color={analyse.score_conformite >= 80 ? 'emerald' : analyse.score_conformite >= 60 ? 'amber' : 'red'} />
                <StatCard label="Exigences" value={analyse.nb_exigences_verifiees} color="blue" />
                <StatCard label="Critiques" value={analyse.nb_ecarts_critiques} color="red" />
                <StatCard label="Majeurs" value={analyse.nb_ecarts_majeurs} color="orange" />
                <StatCard label="Mineurs" value={analyse.nb_ecarts_mineurs} color="yellow" />
            </div>

            {/* Synthese IA */}
            {analyse.commentaire_ia && (
                <Card className="p-6 mb-6 bg-gradient-to-br from-blue-50 to-indigo-50 border-blue-200">
                    <div className="flex items-start gap-3">
                        <DocumentMagnifyingGlassIcon className="w-6 h-6 text-blue-700 shrink-0 mt-0.5" />
                        <div>
                            <h3 className="font-semibold text-blue-900 mb-1">Synthèse exécutive</h3>
                            <p className="text-sm text-gray-800 leading-relaxed whitespace-pre-wrap">{analyse.commentaire_ia}</p>
                        </div>
                    </div>
                </Card>
            )}

            {/* Périmètre */}
            <Card className="p-5 mb-6">
                <h3 className="font-semibold text-gray-900 mb-3 text-sm">Périmètre de l'analyse</h3>
                <div className="grid md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <p className="text-xs text-gray-500 mb-1">Référentiels ({data?.referentiels?.length || 0})</p>
                        <div className="flex flex-wrap gap-1">
                            {data?.referentiels?.map(r => <Badge key={r.id} variant="info">{r.code}</Badge>)}
                        </div>
                    </div>
                    <div>
                        <p className="text-xs text-gray-500 mb-1">Documents analysés ({data?.documents?.length || 0})</p>
                        <div className="flex flex-col gap-1">
                            {data?.documents?.map(d => <span key={d.id} className="text-xs text-gray-700 truncate">• {d.titre}</span>)}
                        </div>
                    </div>
                </div>
            </Card>

            {/* Liste des écarts */}
            {analyse.statut === 'terminee' && (
                <Card>
                    <div className="px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <h3 className="font-semibold text-gray-900">
                            Écarts détectés <span className="text-gray-400 font-normal">({ecartsFiltres.length} / {ecarts.length})</span>
                        </h3>
                        <div className="flex items-center gap-2">
                            <Select value={filtreGravite} onChange={e => setFiltreGravite(e.target.value)} className="w-40">
                                <option value="tous">Toutes gravités</option>
                                <option value="critique">Critique</option>
                                <option value="majeur">Majeur</option>
                                <option value="mineur">Mineur</option>
                                <option value="observation">Observation</option>
                            </Select>
                            <Select value={filtreCategorie} onChange={e => setFiltreCategorie(e.target.value)} className="w-44">
                                <option value="tous">Toutes catégories</option>
                                {categoriesDistinctes.map(c => <option key={c} value={c}>{c}</option>)}
                            </Select>
                        </div>
                    </div>

                    {ecartsFiltres.length === 0 ? (
                        <div className="py-16 text-center">
                            <CheckCircleIcon className="w-12 h-12 text-emerald-500 mx-auto mb-3" />
                            <p className="text-gray-600">
                                {ecarts.length === 0 ? 'Aucun écart détecté. Conformité parfaite !' : 'Aucun écart ne correspond aux filtres.'}
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {ecartsFiltres.map(ecart => (
                                <EcartLigne key={ecart.id} ecart={ecart} onClick={() => setEcartActif(ecart)} />
                            ))}
                        </div>
                    )}
                </Card>
            )}

            {/* Historique des versions */}
            <Card className="mt-6 p-5">
                <div className="flex items-center gap-2 mb-3">
                    <ClockIcon className="w-5 h-5 text-gray-500" />
                    <h3 className="font-semibold text-gray-900">Historique des versions</h3>
                    <span className="text-xs text-gray-400">({versions.length})</span>
                </div>
                {versionsLoading ? (
                    <p className="text-sm text-gray-500">Chargement de l'historique...</p>
                ) : versions.length === 0 ? (
                    <p className="text-sm text-gray-400 italic">
                        Aucune version archivée. Lorsque vous cliquerez sur "Refaire l'analyse",
                        la version actuelle sera figée ici.
                    </p>
                ) : (
                    <ul className="divide-y divide-gray-100">
                        {versions.map(v => (
                            <li key={v.id} className="py-3 flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="font-medium text-gray-900 text-sm">
                                        Version {v.numero_version}
                                        {v.motif && <span className="text-gray-500 font-normal"> — {v.motif}</span>}
                                    </p>
                                    <p className="text-xs text-gray-500 mt-0.5">
                                        Archivée le {new Date(v.created_at).toLocaleString('fr-FR')}
                                        {v.auteur && ` · par ${v.auteur.prenom || ''} ${v.auteur.nom || ''}`.trim()}
                                    </p>
                                    <div className="flex flex-wrap gap-3 mt-1.5 text-xs text-gray-600">
                                        <span>Score : <strong>{v.score_conformite ?? '—'}</strong>{v.score_conformite != null && ' %'}</span>
                                        <span>Statut : <strong>{v.statut}</strong></span>
                                        <span>Écarts : {(v.nb_ecarts_critiques || 0) + (v.nb_ecarts_majeurs || 0) + (v.nb_ecarts_mineurs || 0)}</span>
                                        {v.nb_ecarts_critiques > 0 && <Badge variant="danger" size="sm">{v.nb_ecarts_critiques} crit.</Badge>}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            {/* Drawer detail ecart */}
            {ecartActif && (
                <EcartDrawer ecart={ecartActif} onClose={() => setEcartActif(null)} />
            )}

            {/* Modal Refaire l'analyse */}
            <Modal
                open={showRefaire}
                onClose={() => setShowRefaire(false)}
                title="Refaire l'analyse"
                subtitle="La version actuelle sera archivée dans l'historique avant de relancer le moteur."
                icon={ArrowPathIcon}
                accent="indigo"
                size="md"
                footer={(
                    <>
                        <Button variant="secondary" type="button" onClick={() => setShowRefaire(false)}>Annuler</Button>
                        <Button variant="primary" type="submit" form="refaire-form" loading={refaireLoading}>
                            {refaireLoading ? 'Lancement...' : 'Lancer la nouvelle version'}
                        </Button>
                    </>
                )}
            >
                <form id="refaire-form" onSubmit={handleRefaire} className="space-y-3">
                    <Input
                        label="Motif (optionnel)"
                        value={motifRefaire}
                        onChange={e => setMotifRefaire(e.target.value)}
                        placeholder="Ex: Après ajout des preuves justificatives sur les écarts critiques"
                        helper="Permet de retrouver facilement la raison de cette nouvelle itération dans l'historique."
                    />
                    <div className="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-900">
                        Le moteur va inclure automatiquement tous les nouveaux documents uploadés par le client depuis la dernière analyse.
                    </div>
                </form>
            </Modal>
        </div>
    );
}

function StatCard({ label, value, color }) {
    const colors = {
        blue: 'text-blue-700 bg-blue-50',
        emerald: 'text-emerald-700 bg-emerald-50',
        amber: 'text-amber-700 bg-amber-50',
        red: 'text-red-700 bg-red-50',
        orange: 'text-orange-700 bg-orange-50',
        yellow: 'text-yellow-700 bg-yellow-50',
    };
    return (
        <Card className="p-4">
            <p className="text-xs text-gray-500 mb-1">{label}</p>
            <p className={`text-2xl font-bold ${colors[color].split(' ')[0]}`}>{value ?? 0}</p>
        </Card>
    );
}

/**
 * Nettoie les textes extraits de PDFs mal parses :
 *  - remplace les caracteres ligatures mal decodes
 *  - normalise les espaces multiples
 *  - retire les tabulations et retours ligne excessifs
 */
function nettoyerTexte(txt) {
    if (!txt) return '';
    let s = String(txt);
    // Decoder les entites HTML courantes (extraction DOCX)
    s = s
        .replace(/&amp;/g, '&')
        .replace(/&#039;/g, "'")
        .replace(/&quot;/g, '"')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&nbsp;/g, ' ')
        .replace(/&#(\d+);/g, (_, n) => String.fromCharCode(parseInt(n, 10)));
    return s
        .replace(/[ \t\u00a0]+/g, ' ')
        .replace(/ {2,}/g, ' ')
        .replace(/\n{3,}/g, '\n\n')
        .replace(/\[PAGE_BREAK:\d+\]/g, '')
        .trim();
}

function EcartLigne({ ecart, onClick }) {
    const cfg = graviteConfig[ecart.gravite] || graviteConfig.mineur;
    const Icon = cfg.icon;
    const hasSource = ecart.source_fichier || ecart.document?.titre;
    const hasQuestion = !!ecart.question_numero;
    return (
        <button onClick={onClick} className="w-full px-6 py-4 flex items-start gap-4 hover:bg-gray-50 transition-colors text-left">
            <div className={`w-10 h-10 rounded-lg ${cfg.bg} flex items-center justify-center shrink-0`}>
                <Icon className={`w-5 h-5 ${cfg.text}`} />
            </div>
            <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-1 flex-wrap">
                    <Badge variant={cfg.color === 'red' ? 'danger' : cfg.color === 'orange' ? 'warning' : 'info'}>{cfg.label}</Badge>
                    <Badge variant="gray">{ecart.categorie}</Badge>
                    {ecart.article_reference && <span className="text-xs text-gray-500 font-mono">{ecart.article_reference}</span>}
                    {ecart.referentiel?.code && <span className="text-xs text-blue-600 font-mono">{ecart.referentiel.code}</span>}
                    {hasQuestion && (
                        <span className="text-xs font-bold text-white bg-purple-600 px-2 py-0.5 rounded">
                            Q{ecart.question_numero}
                        </span>
                    )}
                </div>
                <p className="font-medium text-gray-900 text-sm">{nettoyerTexte(ecart.titre)}</p>
                {hasSource && (
                    <p className="text-xs text-blue-700 mt-1 font-medium truncate">
                        📄 {ecart.source_fichier || ecart.document?.titre}
                        {hasQuestion && <span> · Question n°{ecart.question_numero}</span>}
                    </p>
                )}
                <p className="text-xs text-gray-500 mt-1 line-clamp-2">{nettoyerTexte(ecart.description_ecart)}</p>
            </div>
            <Badge variant={ecart.statut_correction === 'traite' || ecart.statut_correction === 'accepte_par_client' ? 'success' : ecart.statut_correction === 'en_cours' ? 'warning' : 'gray'}>
                {ecart.statut_correction.replace(/_/g, ' ')}
            </Badge>
        </button>
    );
}

function EcartDrawer({ ecart, onClose }) {
    const cfg = graviteConfig[ecart.gravite] || graviteConfig.mineur;

    const drawerAccent = cfg.color === 'red' ? 'rose' : cfg.color === 'orange' ? 'amber' : 'blue';
    const subtitle = (ecart.source_fichier || ecart.document?.titre)
        ? `${ecart.source_fichier || ecart.document?.titre}${ecart.question_numero ? ` · Question n°${ecart.question_numero}` : ''}`
        : (ecart.referentiel?.code || '');

    return (
        <Drawer
            open={true}
            onClose={onClose}
            title={nettoyerTexte(ecart.titre)}
            subtitle={subtitle}
            icon={cfg.icon}
            accent={drawerAccent}
            size="lg"
        >
            <div className="space-y-5">
                    <div className="flex items-center gap-2 flex-wrap">
                        <Badge variant={cfg.color === 'red' ? 'danger' : cfg.color === 'orange' ? 'warning' : 'info'}>{cfg.label}</Badge>
                        <Badge variant="gray">{ecart.categorie}</Badge>
                        <Badge variant="gray">{ecart.type_ecart.replace(/_/g, ' ')}</Badge>
                        {ecart.score_similarite !== null && (
                            <span className="text-xs text-gray-500">Similarité: {(parseFloat(ecart.score_similarite) * 100).toFixed(0)}%</span>
                        )}
                    </div>

                    <Section titre="Exigence du référentiel">
                        <p className="text-sm text-gray-700 whitespace-pre-wrap bg-gray-50 p-4 rounded-lg border border-gray-200 italic leading-relaxed">
                            {nettoyerTexte(ecart.exigence_referentiel)}
                        </p>
                        {ecart.referentiel && (
                            <p className="text-xs text-gray-500 mt-2">
                                <span className="font-mono">{ecart.referentiel.code}</span> · {ecart.referentiel.titre}
                                {ecart.article_reference && ` · ${ecart.article_reference}`}
                            </p>
                        )}
                    </Section>

                    <Section titre="Constat">
                        <p className="text-sm text-gray-800 leading-relaxed whitespace-pre-wrap">{nettoyerTexte(ecart.description_ecart)}</p>
                    </Section>

                    {ecart.risque && (
                        <Section titre="Risque">
                            <p className="text-sm text-rose-900 bg-rose-50 border border-rose-200 p-4 rounded-lg leading-relaxed whitespace-pre-wrap">
                                {nettoyerTexte(ecart.risque)}
                            </p>
                        </Section>
                    )}

                    {/* Documents concernés : tous les documents qui présentent cet écart */}
                    {(() => {
                        const sources = (ecart.documents_sources && ecart.documents_sources.length > 0)
                            ? ecart.documents_sources
                            : (ecart.extrait_document || ecart.question_texte
                                ? [{
                                    document_id: ecart.document?.id,
                                    titre: ecart.source_fichier || ecart.document?.titre,
                                    extrait_document: ecart.extrait_document,
                                    question_numero: ecart.question_numero,
                                    question_texte: ecart.question_texte,
                                    reponse_client: ecart.reponse_client,
                                    score_similarite: ecart.score_similarite,
                                    type_ecart: ecart.type_ecart,
                                }]
                                : []);
                        if (sources.length === 0) return null;
                        return (
                            <Section titre={`Documents concernés (${sources.length})`}>
                                <div className="space-y-3">
                                    {sources.map((s, idx) => (
                                        <div key={idx} className="border border-gray-200 rounded-lg overflow-hidden">
                                            <div className="px-4 py-2 bg-gray-50 border-b border-gray-200 flex items-center justify-between gap-2">
                                                <p className="text-sm font-semibold text-gray-900 truncate">{s.titre || s.nom_fichier || 'Document'}</p>
                                                <div className="flex items-center gap-2 shrink-0">
                                                    <Badge variant="gray">{(s.type_ecart || '').replace(/_/g, ' ')}</Badge>
                                                    {s.score_similarite != null && (
                                                        <span className="text-xs text-gray-500">{(parseFloat(s.score_similarite) * 100).toFixed(0)}%</span>
                                                    )}
                                                </div>
                                            </div>
                                            {s.question_numero ? (
                                                <div className="bg-purple-50 p-4 space-y-2">
                                                    <div>
                                                        <p className="text-xs font-semibold text-purple-800 mb-1 uppercase">Question n°{s.question_numero}</p>
                                                        <p className="text-sm text-gray-900 font-medium">{nettoyerTexte(s.question_texte)}</p>
                                                    </div>
                                                    {s.reponse_client && (
                                                        <div>
                                                            <p className="text-xs font-semibold text-purple-800 mb-1 uppercase">Réponse du client</p>
                                                            <p className={`text-sm whitespace-pre-wrap ${s.reponse_client.includes('non repondue') ? 'text-red-700 italic' : 'text-gray-800'}`}>
                                                                {nettoyerTexte(s.reponse_client)}
                                                            </p>
                                                        </div>
                                                    )}
                                                </div>
                                            ) : s.extrait_document ? (
                                                <blockquote className="text-sm text-gray-700 italic bg-amber-50 p-4 whitespace-pre-wrap leading-relaxed">
                                                    « {nettoyerTexte(s.extrait_document)} »
                                                </blockquote>
                                            ) : (
                                                <p className="text-sm text-gray-500 italic p-4">Aucune preuve documentaire trouvée dans ce document.</p>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </Section>
                        );
                    })()}

                    {ecart.recommandation && (
                        <Section titre="Recommandation">
                            <p className="text-sm text-emerald-900 bg-emerald-50 border border-emerald-200 p-4 rounded-lg font-medium leading-relaxed whitespace-pre-wrap">
                                {nettoyerTexte(ecart.recommandation)}
                            </p>
                        </Section>
                    )}

            </div>
        </Drawer>
    );
}

function Section({ titre, children }) {
    return (
        <div>
            <h4 className="font-semibold text-gray-900 text-sm mb-2">{titre}</h4>
            {children}
        </div>
    );
}

