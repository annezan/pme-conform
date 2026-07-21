/**
 * OrganigrammePage — Methode 2 etape 2.
 *
 * Le client peut :
 *   - uploader un fichier (image/PDF) de son organigramme
 *   - OU saisir une structure arborescente (poles -> services -> postes)
 *
 * Le consultant ASC fige l'organigramme : ce qui declenche la generation
 * IA des questionnaires d'audit.
 */

import { useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import api, { getCsrfCookie } from '@/api/client';
import { suivreGenerationQuestionnaires, regenererTousLesQuestionnaires } from '@/api/questionnaires';
import { useAuth } from '@/contexts/AuthContext';
import { alertSuccess, alertError, confirmAction } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import { ArrowLeftIcon, PlusIcon, TrashIcon, CloudArrowUpIcon, SparklesIcon, BuildingOffice2Icon, ArrowDownTrayIcon, ArrowPathIcon, DocumentIcon, CheckCircleIcon, ExclamationTriangleIcon, ClockIcon } from '@heroicons/react/24/outline';

export default function OrganigrammePage() {
    const { id } = useParams();
    const navigate = useNavigate();
    const { hasPermission } = useAuth();
    // "ASC" = utilisateur cote AS Consulting avec capacite a editer l'organigramme.
    const estASC = hasPermission('update-organigramme');
    const [organigramme, setOrganigramme] = useState(null);
    const [structure, setStructure] = useState([]);
    const [mode, setMode] = useState('formulaire');
    const [fichier, setFichier] = useState(null);
    const [saving, setSaving] = useState(false);
    const [figing, setFiging] = useState(false);
    const [generation, setGeneration] = useState(null); // { etat, total, faits, ... }
    const [nbQuestionnaires, setNbQuestionnaires] = useState(0);
    const pollingRef = useRef(null);

    const charger = async () => {
        try {
            const r = await api.get(`/missions/${id}/organigramme`);
            setOrganigramme(r.data.organigramme);
            setStructure(r.data.organigramme.structure || []);
            setMode(r.data.organigramme.mode || 'formulaire');
            const gen = r.data.organigramme?.metadata?.generation || null;
            setGeneration(gen);
            // Pour les organigrammes figes, on recupere systematiquement le
            // nombre de questionnaires existants (meme si la generation n'a
            // pas de tracking metadata — cas des generations anterieures au
            // tracking, donc le bouton "Tout regenerer" reste disponible).
            if (r.data.organigramme?.statut === 'fige') {
                try {
                    const p = await suivreGenerationQuestionnaires(id);
                    setNbQuestionnaires(p.nb_questionnaires ?? 0);
                } catch { /* silencieux */ }
            }
            if (gen?.etat === 'en_file' || gen?.etat === 'en_cours') {
                demarrerPolling();
            }
        } catch { alertError('Impossible de charger l\'organigramme'); }
    };
    useEffect(() => { charger(); return () => arreterPolling(); }, [id]);

    const arreterPolling = () => {
        if (pollingRef.current) {
            clearInterval(pollingRef.current);
            pollingRef.current = null;
        }
    };

    const demarrerPolling = () => {
        arreterPolling();
        pollingRef.current = setInterval(async () => {
            try {
                const r = await suivreGenerationQuestionnaires(id);
                setGeneration(r.generation);
                setNbQuestionnaires(r.nb_questionnaires ?? 0);
                if (r.generation?.etat === 'termine') {
                    arreterPolling();
                    alertSuccess(`${r.nb_questionnaires} questionnaire(s) generes.`);
                } else if (r.generation?.etat === 'erreur') {
                    arreterPolling();
                    alertError("Generation echouee : " + (r.generation?.message || 'erreur inconnue'));
                }
            } catch {
                // silencieux : on continue de polling, le job tourne peut-etre encore
            }
        }, 5000);
    };

    const enregistrer = async () => {
        setSaving(true);
        try {
            await getCsrfCookie();
            await api.put(`/missions/${id}/organigramme`, { mode, structure });
            alertSuccess('Organigramme enregistré');
            charger();
        } catch (e) { alertError(e.response?.data?.message || 'Erreur'); } finally { setSaving(false); }
    };

    const uploader = async (e) => {
        e.preventDefault();
        if (!fichier) return;
        try {
            await getCsrfCookie();
            const fd = new FormData();
            fd.append('fichier', fichier);
            const r = await api.post(`/missions/${id}/organigramme/upload`, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
            alertSuccess(r.data.message || 'Fichier uploadé');
            setFichier(null);
            charger();
        } catch (e) { alertError(e.response?.data?.message || 'Erreur upload'); }
    };

    const supprimerFichier = async () => {
        if (!(await confirmAction('Supprimer le fichier organigramme uploadé ? La structure générée automatiquement sera réaffichée.', 'Suppression'))) return;
        try {
            await getCsrfCookie();
            await api.delete(`/missions/${id}/organigramme/fichier`);
            alertSuccess('Fichier supprimé');
            charger();
        } catch (e) { alertError(e.response?.data?.message || 'Erreur'); }
    };

    const telechargerFichier = async () => {
        try {
            const r = await api.get(`/missions/${id}/organigramme/fichier`, { responseType: 'blob' });
            const url = window.URL.createObjectURL(r.data);
            const a = document.createElement('a');
            a.href = url;
            a.download = organigramme.fichier_nom_original || 'organigramme';
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(url);
        } catch (e) { alertError(e.response?.data?.message || 'Téléchargement impossible'); }
    };

    const formaterTaille = (octets) => {
        if (!octets) return '';
        if (octets < 1024) return `${octets} o`;
        if (octets < 1024 * 1024) return `${(octets / 1024).toFixed(1)} ko`;
        return `${(octets / (1024 * 1024)).toFixed(1)} Mo`;
    };

    const figer = async () => {
        if (!(await confirmAction('Figer l\'organigramme et lancer la génération IA des questionnaires ? La génération tourne en arrière-plan, vous pouvez fermer cette page.', 'Figeage'))) return;
        setFiging(true);
        try {
            await getCsrfCookie();
            const r = await api.post(`/missions/${id}/organigramme/figer`);
            alertSuccess(r.data.message);
            await charger();        // recupere l'etat fige + metadata.generation
            demarrerPolling();      // suit l'avancement du job
        } catch (e) { alertError(e.response?.data?.message || 'Erreur'); } finally { setFiging(false); }
    };

    const ajouterPole = () => setStructure(s => [...s, { pole: '', services: [{ nom: '', postes: [] }] }]);
    const modifierPole = (i, patch) => setStructure(s => s.map((p, idx) => idx === i ? { ...p, ...patch } : p));
    const supprimerPole = (i) => setStructure(s => s.filter((_, idx) => idx !== i));
    const ajouterService = (pi) => modifierPole(pi, { services: [...(structure[pi].services || []), { nom: '', postes: [] }] });
    const modifierService = (pi, si, patch) => {
        const services = structure[pi].services.map((s, idx) => idx === si ? { ...s, ...patch } : s);
        modifierPole(pi, { services });
    };
    const supprimerService = (pi, si) => {
        const services = structure[pi].services.filter((_, idx) => idx !== si);
        modifierPole(pi, { services });
    };

    if (!organigramme) return <div className="p-8">Chargement...</div>;
    const fige = organigramme.statut === 'fige';

    return (
        <div className="p-6 lg:p-8 max-w-5xl mx-auto">
            <Button variant="ghost" onClick={() => navigate(`/missions/${id}`)} className="mb-3">
                <ArrowLeftIcon className="w-4 h-4" /> Retour mission
            </Button>
            <PageHeader
                title="Organigramme"
                subtitle="Méthode 2 - Étape 2 : structure organisationnelle qui sera utilisée pour générer les questionnaires d'audit"
                eyebrow="IA dynamique · Étape 2/3"
                icon={BuildingOffice2Icon}
                accent="indigo"
            >
                <Badge variant={fige ? 'success' : 'gray'} solid size="md" dot>{fige ? 'Figé' : 'En cours'}</Badge>
            </PageHeader>

            <Card className="p-5 mb-4">
                <div className="flex items-center gap-3 mb-4">
                    <button type="button" onClick={() => setMode('upload')} disabled={fige} className={`px-4 py-2 rounded-lg text-sm font-medium ${mode === 'upload' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'}`}>
                        Uploader un fichier
                    </button>
                </div>

                {mode === 'formulaire' && (
                    <div className="space-y-3">
                        {(structure || []).length === 0 && (
                            <p className="text-sm text-gray-400 italic">Aucun pôle. Cliquez sur "Ajouter un pôle".</p>
                        )}
                        {(structure || []).map((p, pi) => (
                            <div key={pi} className="border border-gray-200 rounded-lg p-4">
                                <div className="flex items-center gap-2 mb-3">
                                    <input type="text" value={p.pole} onChange={e => modifierPole(pi, { pole: e.target.value })} placeholder="Nom du pôle (ex: Pôle RH)" disabled={fige} className="flex-1 px-3 py-2 border border-gray-300 rounded-lg font-medium" />
                                    {!fige && <button type="button" onClick={() => supprimerPole(pi)} className="text-red-600 hover:bg-red-50 p-2 rounded"><TrashIcon className="w-4 h-4" /></button>}
                                </div>
                                <div className="ml-4 space-y-2">
                                    {(p.services || []).map((s, si) => (
                                        <div key={si} className="flex items-center gap-2">
                                            <input type="text" value={s.nom || ''} onChange={e => modifierService(pi, si, { nom: e.target.value })} placeholder="Service (ex: Paie, Recrutement)" disabled={fige} className="flex-1 px-3 py-1.5 border border-gray-300 rounded text-sm" />
                                            <input type="text" value={(s.postes || []).join(', ')} onChange={e => modifierService(pi, si, { postes: e.target.value.split(',').map(x => x.trim()).filter(Boolean) })} placeholder="Postes (séparés par virgule)" disabled={fige} className="flex-1 px-3 py-1.5 border border-gray-300 rounded text-sm" />
                                            {!fige && <button type="button" onClick={() => supprimerService(pi, si)} className="text-red-500 hover:bg-red-50 p-1.5 rounded"><TrashIcon className="w-4 h-4" /></button>}
                                        </div>
                                    ))}
                                    {!fige && <Button type="button" variant="ghost" onClick={() => ajouterService(pi)}><PlusIcon className="w-4 h-4" /> Ajouter un service</Button>}
                                </div>
                            </div>
                        ))}
                        {!fige && <div className="flex justify-end pt-2"><Button onClick={enregistrer} disabled={saving}>{saving ? 'Enregistrement...' : 'Enregistrer'}</Button></div>}
                    </div>
                )}

                {mode === 'upload' && (
                    <div className="space-y-4">
                        {organigramme.fichier_chemin && (
                            <div className="border border-emerald-200 bg-emerald-50 rounded-lg p-4 flex items-center justify-between gap-3 flex-wrap">
                                <div className="flex items-center gap-3 min-w-0">
                                    <DocumentIcon className="w-8 h-8 text-emerald-600 shrink-0" />
                                    <div className="min-w-0">
                                        <p className="font-medium text-gray-900 truncate">
                                            {organigramme.fichier_nom_original || organigramme.fichier_chemin.split('/').pop()}
                                        </p>
                                        <p className="text-xs text-gray-600">
                                            {organigramme.fichier_mime}
                                            {organigramme.fichier_taille_octets ? ` · ${formaterTaille(organigramme.fichier_taille_octets)}` : ''}
                                        </p>
                                        <p className="text-xs text-emerald-700 mt-0.5">Ce fichier supplante l'organigramme généré automatiquement.</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2 shrink-0">
                                    <Button type="button" variant="secondary" size="sm" onClick={telechargerFichier}>
                                        <ArrowDownTrayIcon className="w-4 h-4" /> Télécharger
                                    </Button>
                                    {!fige && (
                                        <Button type="button" variant="danger" size="sm" onClick={supprimerFichier}>
                                            <TrashIcon className="w-4 h-4" /> Supprimer
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}

                        {!fige && (
                            <form onSubmit={uploader} className="space-y-3">
                                <p className="text-xs text-gray-600">
                                    {organigramme.fichier_chemin
                                        ? 'Sélectionnez un nouveau fichier pour remplacer celui-ci :'
                                        : 'Uploadez votre propre organigramme (image, PDF, DOCX, XLSX). Il remplacera l\'organigramme généré automatiquement.'}
                                </p>
                                <div className="relative border-2 border-dashed border-gray-300 rounded-lg p-6 hover:border-blue-400">
                                    <input type="file" accept="image/*,application/pdf,.docx,.xlsx" onChange={e => setFichier(e.target.files[0])} className="absolute inset-0 opacity-0 cursor-pointer" />
                                    <div className="flex flex-col items-center gap-2">
                                        <CloudArrowUpIcon className="w-8 h-8 text-gray-400" />
                                        {fichier ? <span className="text-emerald-700 font-medium">{fichier.name}</span> : <span className="text-gray-500">Cliquez pour sélectionner (image, PDF, DOCX, XLSX)</span>}
                                    </div>
                                </div>
                                <Button type="submit" disabled={!fichier}>
                                    {organigramme.fichier_chemin ? <><ArrowPathIcon className="w-4 h-4" /> Remplacer le fichier</> : <><CloudArrowUpIcon className="w-4 h-4" /> Uploader</>}
                                </Button>
                            </form>
                        )}
                    </div>
                )}
            </Card>

            {estASC && !fige && (
                <Card className="p-5 border-l-4 border-l-purple-500">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <p className="font-semibold text-gray-900 flex items-center gap-2"><SparklesIcon className="w-5 h-5 text-purple-600" /> Figer & générer les questionnaires</p>
                            <p className="text-sm text-gray-600 mt-1">L'IA va analyser votre organigramme et produire automatiquement les questionnaires d'audit pour chaque pôle/service détecté (thèmes : biométrie, vidéo, cartographie, sous-traitance, etc.).</p>
                        </div>
                        <Button variant="success" onClick={figer} disabled={figing}>
                            <SparklesIcon className="w-4 h-4" /> {figing ? 'Génération IA...' : 'Figer & générer'}
                        </Button>
                    </div>
                </Card>
            )}

            {fige && generation && ['en_file', 'en_cours', 'erreur'].includes(generation.etat) && (
                <CarteGeneration generation={generation} nbQuestionnaires={nbQuestionnaires} missionId={id} navigate={navigate} />
            )}

            {/* Cas "termine" OU absence de tracking metadata (generation legacy) :
                meme carte avec bouton "Tout regenerer" disponible des qu'au moins
                un questionnaire existe. */}
            {fige && (!generation || generation.etat === 'termine') && (
                <CarteGenerationTerminee
                    nbQuestionnaires={generation?.etat === 'termine' ? (nbQuestionnaires || generation.faits || 0) : nbQuestionnaires}
                    missionId={id}
                    navigate={navigate}
                />
            )}
        </div>
    );
}

/**
 * CarteGeneration — affichage de la progression du job IA.
 * 4 etats : en_file, en_cours (avec barre), termine, erreur.
 */
function CarteGeneration({ generation, nbQuestionnaires, missionId, navigate }) {
    const etat = generation?.etat || 'inconnu';
    const total = generation?.total || 0;
    const faits = generation?.faits || 0;
    const pct = total > 0 ? Math.min(100, Math.round((faits / total) * 100)) : 0;

    if (etat === 'en_file' || etat === 'en_cours') {
        return (
            <Card className="p-5 border-l-4 border-l-blue-500">
                <div className="flex items-start gap-3 mb-3">
                    <div className="w-9 h-9 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center shrink-0">
                        <SparklesIcon className="w-5 h-5 animate-pulse" />
                    </div>
                    <div className="flex-1 min-w-0">
                        <p className="font-semibold text-gray-900">
                            {etat === 'en_file' ? 'Génération IA en file d\'attente…' : 'Génération IA en cours…'}
                        </p>
                        <p className="text-xs text-gray-600 mt-0.5">
                            {etat === 'en_file'
                                ? 'Le job a été enregistré. Il démarre dès qu\'un worker prend la main.'
                                : `${faits}/${total} questionnaire(s) générés.`}
                        </p>
                    </div>
                    <Badge variant="info" dot>Async</Badge>
                </div>
                {etat === 'en_cours' && total > 0 && (
                    <div className="space-y-1">
                        <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div className="h-full bg-gradient-to-r from-blue-500 to-indigo-600 transition-all" style={{ width: `${pct}%` }} />
                        </div>
                        <p className="text-[11px] text-gray-500 text-right tabular-nums">{pct} %</p>
                    </div>
                )}
                <p className="text-xs text-gray-500 mt-3 flex items-center gap-1.5">
                    <ClockIcon className="w-3.5 h-3.5" />
                    Vous pouvez quitter cette page, la génération continue côté serveur.
                </p>
            </Card>
        );
    }

    if (etat === 'erreur') {
        return (
            <Card className="p-5 border-l-4 border-l-red-500">
                <div className="flex items-start gap-3">
                    <div className="w-9 h-9 rounded-full bg-red-100 text-red-600 flex items-center justify-center shrink-0">
                        <ExclamationTriangleIcon className="w-5 h-5" />
                    </div>
                    <div className="flex-1">
                        <p className="font-semibold text-gray-900">Génération échouée</p>
                        <p className="text-xs text-red-700 mt-0.5">{generation?.message || 'Erreur inconnue côté serveur.'}</p>
                        <p className="text-xs text-gray-500 mt-2">Réessayez depuis le bouton "Figer & générer" après diagnostic.</p>
                    </div>
                </div>
            </Card>
        );
    }

    // termine
    return <CarteGenerationTerminee nbQuestionnaires={nbQuestionnaires || faits} missionId={missionId} navigate={navigate} />;
}

function CarteGenerationTerminee({ nbQuestionnaires, missionId, navigate }) {
    const [regenInfo, setRegenInfo] = useState(null);
    const [regenerating, setRegenerating] = useState(false);

    const lancerRegenTous = async () => {
        if (!(await confirmAction(
            `Régénérer tous les questionnaires non-publiés ? Les questions actuelles seront remplacées par une nouvelle proposition IA. Les questionnaires déjà publiés chez le client seront ignorés.`,
            'Régénération en lot',
        ))) return;
        setRegenerating(true);
        try {
            const r = await regenererTousLesQuestionnaires(missionId);
            setRegenInfo(r);
            alertSuccess(r.message);
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur regeneration');
        } finally {
            setRegenerating(false);
        }
    };

    return (
        <Card className="p-5 border-l-4 border-l-emerald-500">
            <div className="flex items-start justify-between gap-4 flex-wrap">
                <div className="flex items-start gap-3">
                    <div className="w-9 h-9 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                        <CheckCircleIcon className="w-5 h-5" />
                    </div>
                    <div>
                        <p className="font-semibold text-gray-900">Génération terminée</p>
                        <p className="text-xs text-gray-600 mt-0.5">
                            <strong>{nbQuestionnaires}</strong> questionnaire(s) ont été générés depuis cet organigramme.
                        </p>
                        {regenInfo && (
                            <p className="text-xs text-indigo-700 mt-1">
                                {regenInfo.dispatches} régénération(s) lancée(s) en arrière-plan.
                                {regenInfo.sautes_publies > 0 && <> {regenInfo.sautes_publies} publié(s) ignoré(s).</>}
                                {regenInfo.sautes_en_cours > 0 && <> {regenInfo.sautes_en_cours} déjà en cours.</>}
                                {' '}Voir le détail sur la page Questionnaires.
                            </p>
                        )}
                    </div>
                </div>
                <div className="flex items-center gap-2 flex-wrap">
                    <Button variant="secondary" onClick={lancerRegenTous} disabled={regenerating}>
                        <SparklesIcon className="w-4 h-4" /> {regenerating ? 'Lancement…' : 'Tout régénérer'}
                    </Button>
                    <Button onClick={() => navigate(`/missions/${missionId}/questionnaires`)}>
                        Voir les questionnaires
                    </Button>
                </div>
            </div>
        </Card>
    );
}
