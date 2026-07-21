/**
 * Page NouvelleAnalyse — Stepper 4 etapes :
 *  1. Mission (selection d'une mission existante)
 *  2. Sources (documents uploades + reponses des questionnaires/formulaires lies a la mission)
 *  3. Referentiels (auto-selection selon secteur, modifiable)
 *  4. Lancement
 */

import { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '@/api/client';
import { listReferentiels } from '@/api/referentiels';
import { lancerAnalyse } from '@/api/analyses';
import { uploadDocument } from '@/api/documents';
import { listDocumentsDuClient } from '@/api/clientDocuments';
import { alertSuccess, alertError } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import { Input, Select } from '@/components/ui/Input';
import {
    BuildingOffice2Icon, DocumentArrowUpIcon, BookOpenIcon, RocketLaunchIcon,
    CheckCircleIcon, ArrowRightIcon, ArrowLeftIcon, TrashIcon, CloudArrowUpIcon,
    ClipboardDocumentListIcon, MagnifyingGlassCircleIcon,
} from '@heroicons/react/24/outline';

const etapes = [
    { id: 1, nom: 'Mission', icon: BuildingOffice2Icon },
    { id: 2, nom: 'Sources', icon: DocumentArrowUpIcon },
    { id: 3, nom: 'Référentiels', icon: BookOpenIcon },
    { id: 4, nom: 'Lancement', icon: RocketLaunchIcon },
];

export default function NouvelleAnalyse() {
    const navigate = useNavigate();
    const [etape, setEtape] = useState(1);

    // Etape 1 : Mission existante
    const [missions, setMissions] = useState([]);
    const [missionId, setMissionId] = useState('');
    const mission = useMemo(() => missions.find(m => m.id === Number(missionId)) || null, [missions, missionId]);
    const client = mission?.client || null;

    // Etape 2 : Sources (documents + questionnaires)
    const [documentsUploaded, setDocumentsUploaded] = useState([]);
    const [uploadingFiles, setUploadingFiles] = useState([]);
    const [documentsExistants, setDocumentsExistants] = useState([]);
    const [documentsSelectionnes, setDocumentsSelectionnes] = useState([]);
    const [chargementDocs, setChargementDocs] = useState(false);
    const [questionnaires, setQuestionnaires] = useState([]);
    const [questionnairesSelectionnes, setQuestionnairesSelectionnes] = useState([]);
    const [chargementQuestionnaires, setChargementQuestionnaires] = useState(false);

    // Etape 3 : Referentiels
    const [referentiels, setReferentiels] = useState([]);
    const [referentielsSelectionnes, setReferentielsSelectionnes] = useState([]);

    // Etape 4
    const [titreAnalyse, setTitreAnalyse] = useState('');
    const [enrichissementIa, setEnrichissementIa] = useState(true);
    const [lancement, setLancement] = useState(false);

    useEffect(() => {
        api.get('/missions?per_page=200').then(r => setMissions(r.data.data || []));
        listReferentiels({ per_page: 100, statut: 'actif' }).then(r => setReferentiels(r.data || []));
    }, []);

    // Chargement automatique des documents et questionnaires lies a la mission selectionnee
    useEffect(() => {
        if (!missionId || !client) return;
        setChargementDocs(true);
        listDocumentsDuClient(client.id)
            .then(res => {
                const existants = res.data || [];
                setDocumentsExistants(existants);
                setDocumentsSelectionnes(existants.filter(d => d.statut === 'indexe').map(d => d.id));
            })
            .catch(() => setDocumentsExistants([]))
            .finally(() => setChargementDocs(false));

        setChargementQuestionnaires(true);
        api.get(`/missions/${missionId}/questionnaires`)
            .then(res => {
                const qs = res.data?.data || [];
                setQuestionnaires(qs);
                // Pre-cocher tous les questionnaires ayant au moins une reponse
                setQuestionnairesSelectionnes(
                    qs.filter(q => (q.reponses || []).some(r => r.repondu)).map(q => q.id)
                );
            })
            .catch(() => setQuestionnaires([]))
            .finally(() => setChargementQuestionnaires(false));
    }, [missionId, client]);

    // Filtrage des referentiels selon le(s) secteur(s) du client.
    //
    // Laravel serialise les relations Eloquent en snake_case par defaut, donc
    // `secteursActivite()` apparait dans le JSON sous la cle `secteurs_activite`
    // ([{id, nom, pivot}]). On accepte aussi la forme camelCase (anciennes APIs)
    // et la chaine unique `secteur_activite` (schema legacy avant normalisation).
    // Les referentiels transversaux (sans secteur declare) sont toujours inclus.
    const referentielsFiltres = useMemo(() => {
        if (!client) return referentiels;

        const extraireSecteurs = (obj) => {
            const raw = obj?.secteursActivite ?? obj?.secteurs_activite;
            if (!Array.isArray(raw)) return [];
            return raw
                .map(s => typeof s === 'string' ? { id: null, nom: s } : { id: s?.id ?? null, nom: s?.nom ?? '' })
                .filter(s => s.id !== null || s.nom);
        };

        const clientSecteurs = extraireSecteurs(client);
        const clientSecteurIds = new Set(clientSecteurs.map(s => s.id).filter(id => id !== null));
        const clientSecteurNoms = new Set(clientSecteurs.map(s => (s.nom || '').toLowerCase()).filter(Boolean));
        const secteurLegacy = client.secteur_activite?.toLowerCase();

        // Si le client n'a aucun secteur defini, on affiche tout (fallback)
        if (clientSecteurIds.size === 0 && clientSecteurNoms.size === 0 && !secteurLegacy) return referentiels;

        return referentiels.filter(r => {
            const refSecteurs = extraireSecteurs(r);
            const refIds = refSecteurs.map(s => s.id).filter(id => id !== null);
            const refNoms = refSecteurs.map(s => (s.nom || '').toLowerCase()).filter(Boolean);

            // Referentiel transversal (aucun secteur declare) → toujours retenu
            if (refIds.length === 0 && refNoms.length === 0) return true;

            if (refIds.some(id => clientSecteurIds.has(id))) return true;
            if (refNoms.some(n => clientSecteurNoms.has(n))) return true;
            if (secteurLegacy && refNoms.some(n => n.includes(secteurLegacy) || secteurLegacy.includes(n))) return true;

            return false;
        });
    }, [referentiels, client]);

    // Pre-cocher automatiquement les referentiels filtres a l'arrivee sur l'etape 3
    useEffect(() => {
        if (etape !== 3 || referentielsFiltres.length === 0) return;
        setReferentielsSelectionnes(prev => prev.length > 0 ? prev : referentielsFiltres.map(r => r.id));
    }, [etape, referentielsFiltres]);

    const handleFiles = async (files) => {
        for (const file of files) {
            const tmpId = Date.now() + Math.random();
            setUploadingFiles(prev => [...prev, { id: tmpId, name: file.name }]);
            try {
                const fd = new FormData();
                fd.append('fichier', file);
                fd.append('titre', file.name);
                fd.append('type', 'document_client');
                fd.append('mission_id', missionId);
                const res = await uploadDocument(fd);
                setUploadingFiles(prev => prev.filter(u => u.id !== tmpId));
                setDocumentsUploaded(prev => [...prev, res.document]);
            } catch (err) {
                setUploadingFiles(prev => prev.filter(u => u.id !== tmpId));
                alertError(`${file.name} : ${err.response?.data?.message || 'upload échoué'}`);
            }
        }
    };

    const retirerDocument = (id) => setDocumentsUploaded(prev => prev.filter(d => d.id !== id));

    const toggleReferentiel = (id) => setReferentielsSelectionnes(prev =>
        prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]
    );

    const lancer = async () => {
        if (referentielsSelectionnes.length === 0) {
            alertError('Sélectionnez au moins un référentiel.');
            return;
        }
        const idsDocs = [...documentsUploaded.map(d => d.id), ...documentsSelectionnes];
        if (idsDocs.length === 0 && questionnairesSelectionnes.length === 0) {
            alertError('Aucune source. Cochez au moins un document ou un formulaire renseigné.');
            return;
        }

        setLancement(true);
        try {
            const r = await lancerAnalyse({
                mission_id: Number(missionId),
                titre: titreAnalyse || undefined,
                referentiels_ids: referentielsSelectionnes,
                documents_ids: idsDocs,
                questionnaires_ids: questionnairesSelectionnes,
                enrichissement_ia: enrichissementIa,
            });
            alertSuccess('Analyse lancée. Redirection vers le suivi.');
            navigate(`/analyses/${r.analyse.id}`);
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur lors du lancement');
        } finally {
            setLancement(false);
        }
    };

    const peutPasser = () => {
        if (etape === 1) return Boolean(missionId);
        if (etape === 2) return (documentsUploaded.length + documentsSelectionnes.length + questionnairesSelectionnes.length) > 0;
        if (etape === 3) return referentielsSelectionnes.length > 0;
        return true;
    };

    return (
        <div className="p-6 lg:p-8 max-w-5xl mx-auto">
            <PageHeader
                title="Nouvelle analyse d'écarts"
                subtitle="Identifier les manquements réglementaires sur une mission"
                eyebrow="Moteur IA"
                icon={MagnifyingGlassCircleIcon}
                accent="purple"
            />

            {/* Stepper premium */}
            <div className="mb-8 bg-white rounded-2xl ring-1 ring-gray-200/70 shadow-[0_1px_3px_rgba(15,23,42,0.04)] p-6">
                <div className="flex items-center justify-between">
                    {etapes.map((e, idx) => {
                        const Icon = e.icon;
                        const actif = etape === e.id;
                        const done = etape > e.id;
                        return (
                            <div key={e.id} className="flex items-center flex-1">
                                <div className="flex flex-col items-center gap-2 shrink-0">
                                    <div className={`relative w-12 h-12 rounded-2xl flex items-center justify-center transition-all duration-300 ${
                                        done ? 'bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-md shadow-emerald-500/30' :
                                        actif ? 'bg-gradient-to-br from-blue-600 to-indigo-700 text-white shadow-lg shadow-blue-500/30 ring-4 ring-blue-500/15 scale-110' :
                                        'bg-gray-100 text-gray-400'
                                    }`}>
                                        {done ? <CheckCircleIcon className="w-6 h-6" /> : <Icon className="w-5 h-5" />}
                                    </div>
                                    <span className={`text-xs font-bold ${actif ? 'text-blue-700' : done ? 'text-emerald-700' : 'text-gray-400'}`}>
                                        {e.nom}
                                    </span>
                                </div>
                                {idx < etapes.length - 1 && (
                                    <div className={`flex-1 h-1 mx-2 rounded-full transition-colors ${done ? 'bg-gradient-to-r from-emerald-500 to-teal-500' : 'bg-gray-100'}`} />
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>

            <Card className="p-8">
                {etape === 1 && (
                    <EtapeMission
                        missions={missions}
                        missionId={missionId}
                        setMissionId={setMissionId}
                        mission={mission}
                    />
                )}
                {etape === 2 && (
                    <EtapeSources
                        documentsUploaded={documentsUploaded}
                        uploadingFiles={uploadingFiles}
                        onFiles={handleFiles}
                        onRemove={retirerDocument}
                        documentsExistants={documentsExistants}
                        documentsSelectionnes={documentsSelectionnes}
                        onToggleExistant={id => setDocumentsSelectionnes(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id])}
                        chargement={chargementDocs}
                        questionnaires={questionnaires}
                        questionnairesSelectionnes={questionnairesSelectionnes}
                        onToggleQuestionnaire={id => setQuestionnairesSelectionnes(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id])}
                        chargementQuestionnaires={chargementQuestionnaires}
                    />
                )}
                {etape === 3 && (
                    <EtapeReferentiels
                        referentiels={referentielsFiltres}
                        totalReferentiels={referentiels.length}
                        selectionnes={referentielsSelectionnes}
                        onToggle={toggleReferentiel}
                        client={client}
                    />
                )}
                {etape === 4 && (
                    <EtapeLancement
                        titre={titreAnalyse} setTitre={setTitreAnalyse}
                        enrichissementIa={enrichissementIa} setEnrichissementIa={setEnrichissementIa}
                        client={client}
                        mission={mission}
                        documents={[...documentsUploaded, ...documentsExistants.filter(d => documentsSelectionnes.includes(d.id))]}
                        questionnaires={questionnaires.filter(q => questionnairesSelectionnes.includes(q.id))}
                        referentiels={referentiels.filter(r => referentielsSelectionnes.includes(r.id))}
                    />
                )}
            </Card>

            <div className="flex justify-between mt-6">
                <Button variant="secondary" onClick={() => etape > 1 ? setEtape(etape - 1) : navigate('/analyses')} disabled={lancement}>
                    <ArrowLeftIcon className="w-4 h-4" /> {etape === 1 ? 'Annuler' : 'Précédent'}
                </Button>

                {etape < 4 ? (
                    <Button onClick={() => setEtape(etape + 1)} disabled={!peutPasser()}>
                        Suivant <ArrowRightIcon className="w-4 h-4" />
                    </Button>
                ) : (
                    <Button variant="success" onClick={lancer} disabled={lancement || !peutPasser()}>
                        {lancement ? 'Lancement...' : <>Lancer l'analyse <RocketLaunchIcon className="w-4 h-4" /></>}
                    </Button>
                )}
            </div>
        </div>
    );
}

// ---------- Etape 1 : Mission existante ----------
function EtapeMission({ missions, missionId, setMissionId, mission }) {
    return (
        <div className="space-y-6">
            <h2 className="text-xl font-bold text-gray-900">1. Mission à analyser</h2>
            <p className="text-sm text-gray-600">
                L'analyse d'écarts est rattachée à une mission existante. Sélectionnez la mission dans la liste ci-dessous.
                {' '}Si la mission n'existe pas encore, <a href="/missions" className="text-blue-600 underline">créez-la dans le menu Missions</a>.
            </p>

            <Select label="Mission *" value={missionId} onChange={e => setMissionId(e.target.value)}>
                <option value="">-- Sélectionner une mission --</option>
                {missions.map(m => (
                    <option key={m.id} value={m.id}>
                        {m.reference} — {m.titre}
                        {m.client?.raison_sociale ? ` (${m.client.raison_sociale})` : ''}
                    </option>
                ))}
            </Select>

            {mission && (
                <Card className="p-4 bg-blue-50/50 border-blue-200 space-y-2">
                    <div className="flex items-center gap-2 flex-wrap">
                        <span className="font-mono text-xs text-blue-800">{mission.reference}</span>
                        <Badge variant="info">{mission.statut?.replace('_', ' ')}</Badge>
                        <Badge variant={mission.methode === 'methode_2' ? 'purple' : 'gray'}>
                            {mission.methode === 'methode_2' ? 'Méthode 2 - IA dynamique' : 'Méthode 1 - Classique'}
                        </Badge>
                    </div>
                    <p className="font-semibold text-gray-900">{mission.titre}</p>
                    {mission.client?.raison_sociale && (
                        <p className="text-sm text-gray-600">Client : <span className="font-medium">{mission.client.raison_sociale}</span>{(() => {
                            // Laravel serialise la relation en snake_case (secteurs_activite=[{id,nom,pivot}]).
                            const raw = mission.client.secteursActivite || mission.client.secteurs_activite || [];
                            const secteursNoms = (Array.isArray(raw) ? raw : [])
                                .map(s => typeof s === 'string' ? s : s?.nom)
                                .filter(Boolean);
                            const texte = secteursNoms.length ? secteursNoms.join(', ') : (mission.client.secteur_activite || '');
                            return texte ? ` — ${texte}` : '';
                        })()}</p>
                    )}
                </Card>
            )}
        </div>
    );
}

// ---------- Etape 2 : Sources (Documents + Questionnaires) ----------
function EtapeSources({
    documentsUploaded, uploadingFiles, onFiles, onRemove,
    documentsExistants = [], documentsSelectionnes = [], onToggleExistant, chargement,
    questionnaires = [], questionnairesSelectionnes = [], onToggleQuestionnaire, chargementQuestionnaires,
}) {
    const [drag, setDrag] = useState(false);
    const existantsIndexes = documentsExistants.filter(d => d.statut === 'indexe');
    const existantsEnAttente = documentsExistants.filter(d => d.statut !== 'indexe');

    return (
        <div className="space-y-6">
            <h2 className="text-xl font-bold text-gray-900">2. Sources d'analyse</h2>
            <p className="text-sm text-gray-500">
                L'analyse exploite à la fois les documents uploadés par le client et les réponses aux formulaires
                renseignés (par le client ou par l'agent AS Consulting au cours d'un interview).
            </p>

            {/* Section : formulaires/questionnaires renseignés */}
            <div>
                <p className="text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                    <ClipboardDocumentListIcon className="w-4 h-4 text-purple-600" />
                    Formulaires renseignés liés à la mission
                </p>
                {chargementQuestionnaires && (
                    <div className="p-3 bg-purple-50 border border-purple-200 rounded-lg text-xs text-purple-800">Chargement des formulaires...</div>
                )}
                {!chargementQuestionnaires && questionnaires.length === 0 && (
                    <div className="p-3 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-600">
                        Aucun formulaire généré pour cette mission. Le client (ou un agent AS Consulting) peut renseigner des formulaires depuis la fiche mission.
                    </div>
                )}
                {!chargementQuestionnaires && questionnaires.length > 0 && (
                    <div className="border border-gray-200 rounded-xl divide-y divide-gray-100 max-h-80 overflow-y-auto">
                        {questionnaires.map(q => {
                            const coche = questionnairesSelectionnes.includes(q.id);
                            const total = (q.questions || []).length;
                            const repondues = (q.reponses || []).filter(r => r.repondu).length;
                            return (
                                <button
                                    type="button"
                                    key={q.id}
                                    onClick={() => onToggleQuestionnaire(q.id)}
                                    className={`w-full flex items-start gap-3 px-4 py-3 text-left transition-colors ${coche ? 'bg-purple-50' : 'hover:bg-gray-50'}`}
                                >
                                    <div className={`w-5 h-5 mt-0.5 rounded border-2 shrink-0 flex items-center justify-center ${coche ? 'bg-purple-600 border-purple-600' : 'border-gray-300'}`}>
                                        {coche && <CheckCircleIcon className="w-4 h-4 text-white" />}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-gray-900 truncate">{q.titre}</p>
                                        <p className="text-xs text-gray-500 mt-0.5 truncate">
                                            {q.pole}{q.service ? ' / ' + q.service : ''} — {repondues}/{total} réponses
                                        </p>
                                    </div>
                                    <Badge variant={q.statut === 'rempli' || q.statut === 'valide' ? 'success' : 'warning'}>{q.statut}</Badge>
                                </button>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Section : documents existants */}
            {chargement && (
                <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800 flex items-center gap-2">
                    <div className="w-4 h-4 rounded-full border-2 border-blue-500 border-t-transparent animate-spin" />
                    Chargement des documents du client...
                </div>
            )}

            {!chargement && documentsExistants.length > 0 && (
                <div className="border border-gray-200 rounded-xl">
                    <div className="px-4 py-3 border-b border-gray-100 flex items-center justify-between bg-gray-50 rounded-t-xl">
                        <p className="text-sm font-semibold text-gray-700">
                            Documents uploadés par le client ({documentsExistants.length})
                        </p>
                        <div className="flex items-center gap-2 text-xs">
                            <button
                                type="button"
                                onClick={() => existantsIndexes.forEach(d => !documentsSelectionnes.includes(d.id) && onToggleExistant(d.id))}
                                className="text-blue-600 hover:text-blue-800 font-medium"
                            >
                                Tout sélectionner
                            </button>
                            <span className="text-gray-300">|</span>
                            <button
                                type="button"
                                onClick={() => documentsSelectionnes.forEach(id => onToggleExistant(id))}
                                className="text-gray-600 hover:text-gray-800 font-medium"
                            >
                                Tout décocher
                            </button>
                        </div>
                    </div>
                    <div className="max-h-80 overflow-y-auto divide-y divide-gray-100">
                        {existantsIndexes.map(d => {
                            const coche = documentsSelectionnes.includes(d.id);
                            return (
                                <button
                                    type="button"
                                    key={d.id}
                                    onClick={() => onToggleExistant(d.id)}
                                    className={`w-full flex items-start gap-3 px-4 py-3 text-left transition-colors ${coche ? 'bg-blue-50' : 'hover:bg-gray-50'}`}
                                >
                                    <div className={`w-5 h-5 mt-0.5 rounded border-2 shrink-0 flex items-center justify-center ${coche ? 'bg-blue-600 border-blue-600' : 'border-gray-300'}`}>
                                        {coche && <CheckCircleIcon className="w-4 h-4 text-white" />}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-gray-900 truncate">{d.titre}</p>
                                        <p className="text-xs text-gray-500 mt-0.5 truncate">
                                            {d.nom_fichier_original} · {(d.taille_octets / 1024 / 1024).toFixed(2)} Mo · {d.chunks_count} fragments
                                        </p>
                                    </div>
                                    <Badge variant="success">Indexé</Badge>
                                </button>
                            );
                        })}
                        {existantsEnAttente.map(d => (
                            <div key={d.id} className="w-full flex items-start gap-3 px-4 py-3 opacity-60">
                                <div className="w-5 h-5 mt-0.5 rounded border-2 border-gray-200 shrink-0" />
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm text-gray-500 truncate">{d.titre}</p>
                                    <p className="text-xs text-gray-400 mt-0.5">Indexation en cours — non sélectionnable pour le moment</p>
                                </div>
                                <Badge variant="gray">{d.statut}</Badge>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Section : ajouter de nouveaux documents */}
            <div>
                <p className="text-sm font-semibold text-gray-700 mb-2">
                    {documentsExistants.length > 0 ? 'Ajouter de nouveaux documents' : 'Uploader des documents'}
                </p>
                <div
                    onDragOver={e => { e.preventDefault(); setDrag(true); }}
                    onDragLeave={() => setDrag(false)}
                    onDrop={e => { e.preventDefault(); setDrag(false); onFiles(Array.from(e.dataTransfer.files)); }}
                    className={`relative border-2 border-dashed rounded-xl p-8 text-center transition-all cursor-pointer ${drag ? 'border-blue-400 bg-blue-50' : 'border-gray-300 hover:border-blue-400 hover:bg-gray-50'}`}
                >
                    <input
                        type="file"
                        multiple
                        accept=".pdf,.docx,.doc"
                        onChange={e => onFiles(Array.from(e.target.files))}
                        className="absolute inset-0 opacity-0 cursor-pointer"
                    />
                    <CloudArrowUpIcon className="w-10 h-10 text-gray-400 mx-auto mb-2" />
                    <p className="font-medium text-gray-700 text-sm">Glissez-déposez ou cliquez pour sélectionner</p>
                    <p className="text-xs text-gray-400 mt-1">PDF, DOCX (plusieurs fichiers possibles)</p>
                </div>
            </div>

            {uploadingFiles.length > 0 && (
                <div className="space-y-2">
                    {uploadingFiles.map(f => (
                        <div key={f.id} className="flex items-center gap-3 p-3 bg-blue-50 border border-blue-100 rounded-lg text-sm">
                            <div className="w-4 h-4 rounded-full border-2 border-blue-500 border-t-transparent animate-spin" />
                            <span className="flex-1 text-blue-900">{f.name}</span>
                            <span className="text-xs text-blue-600">Upload...</span>
                        </div>
                    ))}
                </div>
            )}

            {documentsUploaded.length > 0 && (
                <div className="space-y-2">
                    <p className="text-sm font-semibold text-gray-700">{documentsUploaded.length} nouveau(x) document(s) ajouté(s)</p>
                    {documentsUploaded.map(d => (
                        <div key={d.id} className="flex items-center gap-3 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm">
                            <CheckCircleIcon className="w-5 h-5 text-emerald-600 shrink-0" />
                            <span className="flex-1 text-gray-900 font-medium truncate">{d.titre || d.nom_fichier_original}</span>
                            <Badge variant="success">{d.statut}</Badge>
                            <button type="button" onClick={() => onRemove(d.id)} className="text-red-500 hover:text-red-700">
                                <TrashIcon className="w-4 h-4" />
                            </button>
                        </div>
                    ))}
                </div>
            )}

            <div className="pt-3 border-t border-gray-100 text-sm text-gray-700 flex flex-wrap gap-x-6 gap-y-1">
                <span>Documents : <span className="font-semibold text-blue-700">{documentsUploaded.length + documentsSelectionnes.length}</span></span>
                <span>Formulaires : <span className="font-semibold text-purple-700">{questionnairesSelectionnes.length}</span></span>
            </div>
        </div>
    );
}

// ---------- Etape 3 : Referentiels ----------
function EtapeReferentiels({ referentiels, totalReferentiels, selectionnes, onToggle, client }) {
    // Laravel serialise la relation en snake_case (secteurs_activite=[{id,nom,pivot}]).
    const rawSecteursClient = client?.secteursActivite || client?.secteurs_activite || [];
    const secteursNoms = (Array.isArray(rawSecteursClient) ? rawSecteursClient : [])
        .map(s => typeof s === 'string' ? s : s?.nom)
        .filter(Boolean);
    const secteurTexte = secteursNoms.length ? secteursNoms.join(', ') : client?.secteur_activite;
    const masqueParFiltre = (totalReferentiels ?? referentiels.length) - referentiels.length;

    return (
        <div className="space-y-5">
            <h2 className="text-xl font-bold text-gray-900">3. Référentiels à appliquer</h2>
            {secteurTexte ? (
                <p className="text-sm text-gray-500">
                    Liste filtrée pour le(s) secteur(s) <span className="font-semibold text-gray-800">{secteurTexte}</span>{masqueParFiltre > 0 ? ` (${masqueParFiltre} référentiel(s) masqué(s) hors secteur)` : ''}.
                </p>
            ) : (
                <p className="text-sm text-gray-500">
                    Aucun secteur défini sur le client : affichage de tous les référentiels disponibles.
                </p>
            )}

            {referentiels.length === 0 && (
                <div className="p-6 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                    {totalReferentiels > 0
                        ? "Aucun référentiel ne correspond au secteur du client. Vérifiez les secteurs renseignés sur le client ou sur les référentiels."
                        : "Aucun référentiel indexé. Ajoutez-en via le menu « Référentiels »."}
                </div>
            )}

            <div className="space-y-2 max-h-[500px] overflow-y-auto">
                {referentiels.map(r => {
                    const actif = selectionnes.includes(r.id);
                    return (
                        <button
                            key={r.id}
                            type="button"
                            onClick={() => onToggle(r.id)}
                            className={`w-full text-left p-4 rounded-lg border-2 transition-all ${actif ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'}`}
                        >
                            <div className="flex items-start gap-3">
                                <div className={`w-5 h-5 rounded border-2 shrink-0 mt-0.5 flex items-center justify-center ${actif ? 'bg-blue-600 border-blue-600' : 'border-gray-300'}`}>
                                    {actif && <CheckCircleIcon className="w-4 h-4 text-white" />}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 mb-1">
                                        <span className="font-mono text-xs font-semibold text-blue-700">{r.code}</span>
                                        <Badge variant="gray">{r.type}</Badge>
                                        {r.autorite && <span className="text-xs text-gray-500">{r.autorite}</span>}
                                    </div>
                                    <p className="font-medium text-gray-900 text-sm">{r.titre}</p>
                                    {(() => {
                                        // Laravel serialise la relation en snake_case ; supporte aussi camelCase / legacy.
                                        const raw = r.secteursActivite || r.secteurs_activite || [];
                                        const liste = (Array.isArray(raw) ? raw : [])
                                            .map(s => typeof s === 'string' ? s : s?.nom)
                                            .filter(Boolean);
                                        return liste.length > 0
                                            ? <p className="text-xs text-gray-500 mt-1">Secteurs : {liste.join(', ')}</p>
                                            : null;
                                    })()}
                                </div>
                            </div>
                        </button>
                    );
                })}
            </div>

            <p className="text-sm text-gray-600 pt-2 border-t">
                <span className="font-semibold">{selectionnes.length}</span> référentiel(s) sélectionné(s).
            </p>
        </div>
    );
}

// ---------- Etape 4 : Recap + Lancement ----------
function EtapeLancement({ titre, setTitre, enrichissementIa, setEnrichissementIa, client, mission, documents, questionnaires, referentiels }) {
    return (
        <div className="space-y-6">
            <h2 className="text-xl font-bold text-gray-900">4. Récapitulatif et lancement</h2>

            <Input
                label="Titre de l'analyse (optionnel)"
                value={titre}
                onChange={e => setTitre(e.target.value)}
                placeholder="Laisser vide pour un titre auto"
            />

            <div className="space-y-3">
                <RecapLigne label="Client" value={client?.raison_sociale || '-'} />
                <RecapLigne label="Secteur" value={(() => {
                    const raw = client?.secteursActivite || client?.secteurs_activite || [];
                    const noms = (Array.isArray(raw) ? raw : [])
                        .map(s => typeof s === 'string' ? s : s?.nom)
                        .filter(Boolean);
                    return noms.length ? noms.join(', ') : (client?.secteur_activite || 'Non renseigné');
                })()} />
                <RecapLigne label="Mission" value={mission ? `${mission.reference} — ${mission.titre}` : '-'} />
                <RecapLigne label="Documents" value={`${documents.length} fichier(s)`} />
                <RecapLigne label="Formulaires" value={`${questionnaires.length} formulaire(s) renseigné(s)`} />
                <RecapLigne label="Référentiels" value={`${referentiels.length} corpus (${referentiels.map(r => r.code).join(', ')})`} />
            </div>

            <div className="pt-2 border-t border-gray-100">
                <label className="block text-sm font-semibold text-gray-800 mb-2">Mode d'analyse</label>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <button
                        type="button"
                        onClick={() => setEnrichissementIa(false)}
                        className={`p-4 border-2 rounded-lg text-left transition-all ${!enrichissementIa ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'}`}
                    >
                        <div className="flex items-center gap-2 mb-1">
                            <span className="text-xl">⚡</span>
                            <span className="font-semibold text-gray-900">Rapide</span>
                            <span className="ml-auto text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-semibold">~ 30 s</span>
                        </div>
                        <p className="text-xs text-gray-600">Détection des écarts et rédaction automatique (sans LLM). Idéal pour un premier diagnostic.</p>
                    </button>
                    <button
                        type="button"
                        onClick={() => setEnrichissementIa(true)}
                        className={`p-4 border-2 rounded-lg text-left transition-all ${enrichissementIa ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'}`}
                    >
                        <div className="flex items-center gap-2 mb-1">
                            <span className="text-xl">🧠</span>
                            <span className="font-semibold text-gray-900">Enrichi (IA)</span>
                            <span className="ml-auto text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">~ 20+ min</span>
                        </div>
                        <p className="text-xs text-gray-600">Le LLM rédige chaque constat. Plus qualitatif mais très lent selon la puissance Ollama.</p>
                    </button>
                </div>
            </div>

            <div className="p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-900">
                <strong>Note :</strong> l'analyse {enrichissementIa ? 'enrichie' : 'rapide'} démarrera en arrière-plan. Vous suivrez la progression sur la page suivante.
            </div>
        </div>
    );
}

function RecapLigne({ label, value }) {
    return (
        <div className="flex justify-between items-start gap-4 py-2 border-b border-gray-100 last:border-0">
            <span className="text-sm font-medium text-gray-500 shrink-0">{label}</span>
            <span className="text-sm text-gray-900 font-medium text-right">{value}</span>
        </div>
    );
}
