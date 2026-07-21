/**
 * Page MesDocuments — Espace client pour gerer ses documents.
 *
 *  - Liste des documents uploades par le client (via ses missions rattachees)
 *  - Upload d'un nouveau document (zone drag-drop)
 *  - Previsualisation du texte extrait
 *  - Suppression (soft delete)
 */

import { useState, useEffect, useMemo, useRef } from 'react';
import {
    listMesDocuments, uploaderMonDocument, getMonDocument, supprimerMonDocument, initialiserEspaceClient,
} from '@/api/clientDocuments';
import api from '@/api/client';
import { useAuth } from '@/contexts/AuthContext';
import { alertSuccess, alertError, confirmDelete } from '@/utils/alerts';
import DataTableWrapper from '@/components/ui/DataTableWrapper';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import PageHeader from '@/components/ui/PageHeader';
import { Input, Select } from '@/components/ui/Input';
import TableActions, { ViewAction, DeleteAction } from '@/components/ui/TableActions';
import Modal from '@/components/ui/Modal';
import Drawer from '@/components/ui/Drawer';
import {
    CloudArrowUpIcon, DocumentTextIcon, CheckCircleIcon, XMarkIcon,
    ClockIcon, BuildingOffice2Icon, ExclamationTriangleIcon,
    ChevronDownIcon, ChevronRightIcon,
} from '@heroicons/react/24/outline';

function decoderTexte(txt) {
    if (!txt) return '';
    return String(txt)
        .replace(/&amp;/g, '&')
        .replace(/&#039;/g, "'")
        .replace(/&quot;/g, '"')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&nbsp;/g, ' ')
        .replace(/&#(\d+);/g, (_, n) => String.fromCharCode(parseInt(n, 10)))
        .replace(/[ \t ]+/g, ' ')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

const statutVariant = {
    en_attente: 'gray',
    en_traitement: 'info',
    indexe: 'success',
    erreur: 'danger',
    archive: 'gray',
};
const statutLabel = {
    en_attente: 'En attente',
    en_traitement: 'Traitement...',
    indexe: 'Indexé',
    erreur: 'Erreur',
    archive: 'Archivé',
};

export default function MesDocuments() {
    const { hasPermission } = useAuth();
    // ASC = utilisateur avec view-portefeuille : voit tous les clients groupes.
    const estAsc = hasPermission('view-portefeuille');
    const [docs, setDocs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [filterText, setFilterText] = useState('');
    const [uploadActif, setUploadActif] = useState(false);
    const [dragOver, setDragOver] = useState(false);
    const [uploadEnCours, setUploadEnCours] = useState([]); // [{id, name}]
    const [documentActif, setDocumentActif] = useState(null); // preview
    const [message, setMessage] = useState(null);
    const [groupesOuverts, setGroupesOuverts] = useState({});
    // Pour ASC : liste des clients disponibles + selection courante pour l'upload
    const [clientsDisponibles, setClientsDisponibles] = useState([]);
    const [uploadClientId, setUploadClientId] = useState('');
    const [initForm, setInitForm] = useState({
        raison_sociale: '', sigle: '', secteur_activite: '', ville: '',
        pays: 'Côte d\'Ivoire', telephone: '', email: '',
    });
    const [initLoading, setInitLoading] = useState(false);
    const [secteursOptions, setSecteursOptions] = useState([]);
    const pollingRef = useRef(null);

    useEffect(() => {
        api.get('/ref/secteurs')
            .then(r => setSecteursOptions(r.data.data || []))
            .catch(() => setSecteursOptions([]));
        // ASC : charge la liste des clients pour le selecteur upload
        if (estAsc) {
            api.get('/clients?per_page=500')
                .then(r => setClientsDisponibles(r.data.data || []))
                .catch(() => setClientsDisponibles([]));
        }
    }, [estAsc]);

    const charger = async (showLoader = true) => {
        if (showLoader) setLoading(true);
        try {
            const r = await listMesDocuments();
            setDocs(r.data || []);
            setMessage(r.message || null);
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur de chargement');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        charger();
        pollingRef.current = setInterval(() => {
            setDocs(current => {
                if (current.some(d => ['en_attente', 'en_traitement'].includes(d.statut))) {
                    charger(false);
                }
                return current;
            });
        }, 3000);
        return () => clearInterval(pollingRef.current);
    }, []);

    const handleFiles = async (files) => {
        // Cote ASC : on impose le choix du client cible AVANT tout upload.
        if (estAsc && !uploadClientId) {
            alertError("Sélectionnez d'abord le client cible.");
            return;
        }
        for (const file of files) {
            const tmp = Date.now() + Math.random();
            setUploadEnCours(prev => [...prev, { id: tmp, name: file.name }]);
            try {
                const fd = new FormData();
                fd.append('fichier', file);
                fd.append('titre', file.name);
                if (estAsc && uploadClientId) {
                    fd.append('client_id', uploadClientId);
                }
                await uploaderMonDocument(fd);
                alertSuccess(`${file.name} uploadé`);
            } catch (err) {
                alertError(`${file.name} : ${err.response?.data?.message || 'upload échoué'}`);
            } finally {
                setUploadEnCours(prev => prev.filter(u => u.id !== tmp));
            }
        }
        charger(false);
    };

    const handleDrop = (e) => {
        e.preventDefault();
        setDragOver(false);
        handleFiles(Array.from(e.dataTransfer.files));
    };

    const handleDelete = async (doc) => {
        if (await confirmDelete(doc.titre)) {
            try {
                await supprimerMonDocument(doc.id);
                alertSuccess('Document supprimé');
                charger(false);
            } catch (err) {
                alertError(err.response?.data?.message || 'Erreur suppression');
            }
        }
    };

    const handlePreview = async (doc) => {
        try {
            const r = await getMonDocument(doc.id);
            setDocumentActif({ ...r.document, contenu_apercu: r.contenu_apercu });
        } catch {
            alertError('Impossible de charger le document');
        }
    };

    const handleInitialiser = async (e) => {
        e.preventDefault();
        if (!initForm.raison_sociale.trim()) {
            alertError('La raison sociale est obligatoire.');
            return;
        }
        setInitLoading(true);
        try {
            await initialiserEspaceClient(initForm);
            alertSuccess('Espace initialisé avec succès. Vous pouvez maintenant uploader vos documents.');
            setMessage(null);
            await charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur lors de l\'initialisation');
        } finally {
            setInitLoading(false);
        }
    };

    const columns = useMemo(() => [
        {
            name: 'Titre',
            selector: row => row.titre,
            sortable: true,
            grow: 3,
            cell: row => (
                <div className="py-2">
                    <p className="font-medium text-gray-900 text-sm">{row.titre}</p>
                    <p className="text-xs text-gray-500 mt-0.5">{row.nom_fichier_original}</p>
                </div>
            ),
        },
        {
            name: 'Taille',
            selector: row => row.taille_octets,
            sortable: true,
            width: '100px',
            cell: row => <span className="text-xs text-gray-600">{(row.taille_octets / 1024 / 1024).toFixed(2)} Mo</span>,
        },
        {
            name: 'Statut',
            selector: row => row.statut,
            width: '150px',
            cell: row => <Badge variant={statutVariant[row.statut]}>{statutLabel[row.statut]}</Badge>,
        },
        {
            name: 'Contenu',
            width: '200px',
            cell: row => {
                if (row.is_questionnaire) {
                    const total = row.nb_questions ?? 0;
                    const repondues = row.nb_questions_repondues ?? 0;
                    const nonRepondues = total - repondues;
                    const pct = total > 0 ? Math.round((repondues / total) * 100) : 0;
                    return (
                        <div className="py-1">
                            <div className="flex items-center gap-2 mb-1">
                                <Badge variant="purple">Questionnaire</Badge>
                                <span className="text-xs font-bold text-gray-900">{pct}%</span>
                            </div>
                            <div className="text-xs text-gray-600">
                                <span className="text-emerald-700 font-semibold">{repondues}</span>
                                {' / '}
                                <span className="font-semibold">{total}</span>
                                {' répondues'}
                                {nonRepondues > 0 && (
                                    <span className="text-red-600 ml-1">({nonRepondues} vide{nonRepondues > 1 ? 's' : ''})</span>
                                )}
                            </div>
                        </div>
                    );
                }
                return row.chunks_count > 0
                    ? <span className="text-xs font-semibold text-emerald-700">{row.chunks_count} fragments</span>
                    : <span className="text-xs text-gray-400">-</span>;
            },
        },
        {
            name: 'Uploadé le',
            width: '150px',
            selector: row => row.created_at,
            cell: row => <span className="text-xs text-gray-500">{new Date(row.created_at).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })}</span>,
        },
        {
            name: 'Actions',
            width: '110px',
            right: true,
            cell: row => (
                <TableActions>
                    <ViewAction onClick={() => handlePreview(row)} />
                    <DeleteAction onClick={() => handleDelete(row)} />
                </TableActions>
            ),
        },
    ], []);

    const filtered = docs.filter(d =>
        d.titre?.toLowerCase().includes(filterText.toLowerCase()) ||
        d.nom_fichier_original?.toLowerCase().includes(filterText.toLowerCase())
    );

    // Regroupement par client : utilise uniquement quand estAsc est vrai.
    // Pour le client lui-meme, un seul groupe = vue plate.
    const documentsParClient = useMemo(() => {
        const map = new Map();
        for (const d of filtered) {
            const id = d.client?.id || 0;
            const nom = d.client?.raison_sociale || 'Sans entreprise rattachée';
            if (!map.has(id)) map.set(id, { id, raison_sociale: nom, items: [] });
            map.get(id).items.push(d);
        }
        return Array.from(map.values()).sort((a, b) => a.raison_sociale.localeCompare(b.raison_sociale));
    }, [filtered]);

    // Au chargement initial, on ouvre tous les groupes
    useEffect(() => {
        if (estAsc && documentsParClient.length > 0 && Object.keys(groupesOuverts).length === 0) {
            const init = {};
            for (const g of documentsParClient) init[g.id] = true;
            setGroupesOuverts(init);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [estAsc, documentsParClient.length]);

    const toggleGroupe = (id) => setGroupesOuverts(o => ({ ...o, [id]: !o[id] }));
    const toutOuvrir = () => setGroupesOuverts(Object.fromEntries(documentsParClient.map(g => [g.id, true])));
    const toutFermer = () => setGroupesOuverts(Object.fromEntries(documentsParClient.map(g => [g.id, false])));

    const stats = useMemo(() => {
        const questionnaires = docs.filter(d => d.is_questionnaire);
        return {
            total: docs.length,
            indexe: docs.filter(d => d.statut === 'indexe').length,
            enCours: docs.filter(d => ['en_attente', 'en_traitement'].includes(d.statut)).length,
            nbQuestionnaires: questionnaires.length,
            totalQuestions: questionnaires.reduce((acc, d) => acc + (d.nb_questions ?? 0), 0),
            totalRepondues: questionnaires.reduce((acc, d) => acc + (d.nb_questions_repondues ?? 0), 0),
        };
    }, [docs]);
    const totalNonRepondues = stats.totalQuestions - stats.totalRepondues;

    if (message && docs.length === 0 && !loading) {
        return (
            <div className="p-6 lg:p-8 max-w-3xl mx-auto">
                <PageHeader
                    title="Bienvenue sur votre espace documentaire"
                    subtitle="Avant de commencer, renseignez les informations de votre entreprise"
                />

                <Card className="p-8">
                    <div className="flex items-start gap-4 mb-6 pb-6 border-b border-gray-100">
                        <div className="w-12 h-12 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center shrink-0">
                            <BuildingOffice2Icon className="w-6 h-6" />
                        </div>
                        <div>
                            <h2 className="font-semibold text-gray-900 text-lg">Initialisation de votre espace</h2>
                            <p className="text-sm text-gray-500 mt-1">
                                Ces informations permettent de rattacher vos documents à votre entreprise et d'orienter les analyses de conformité vers les référentiels pertinents de votre secteur.
                            </p>
                        </div>
                    </div>

                    <form onSubmit={handleInitialiser} className="space-y-4">
                        <Input
                            label="Raison sociale *"
                            value={initForm.raison_sociale}
                            onChange={e => setInitForm({...initForm, raison_sociale: e.target.value})}
                            placeholder="Ex: ACME Côte d'Ivoire SA"
                            required
                        />

                        <div className="grid grid-cols-2 gap-4">
                            <Input
                                label="Sigle (optionnel)"
                                value={initForm.sigle}
                                onChange={e => setInitForm({...initForm, sigle: e.target.value})}
                                placeholder="Ex: ACME CI"
                            />
                            <Select
                                label="Secteur d'activité"
                                value={initForm.secteur_activite}
                                onChange={e => setInitForm({...initForm, secteur_activite: e.target.value})}
                            >
                                <option value="">-- Choisir --</option>
                                {secteursOptions.map(s => (
                                    <option key={s} value={s}>{s}</option>
                                ))}
                            </Select>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <Input
                                label="Ville"
                                value={initForm.ville}
                                onChange={e => setInitForm({...initForm, ville: e.target.value})}
                                placeholder="Ex: Abidjan"
                            />
                            <Input
                                label="Pays"
                                value={initForm.pays}
                                onChange={e => setInitForm({...initForm, pays: e.target.value})}
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <Input
                                label="Téléphone"
                                value={initForm.telephone}
                                onChange={e => setInitForm({...initForm, telephone: e.target.value})}
                                placeholder="+225 XX XX XX XX"
                            />
                            <Input
                                label="Email entreprise"
                                type="email"
                                value={initForm.email}
                                onChange={e => setInitForm({...initForm, email: e.target.value})}
                                placeholder="contact@entreprise.ci"
                            />
                        </div>

                        <div className="pt-4 border-t border-gray-100 flex justify-end">
                            <Button type="submit" disabled={initLoading}>
                                {initLoading ? (
                                    <>
                                        <div className="w-4 h-4 rounded-full border-2 border-white border-t-transparent animate-spin" />
                                        Initialisation...
                                    </>
                                ) : (
                                    <>Créer mon espace documentaire</>
                                )}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        );
    }

    return (
        <div className="p-6 lg:p-8 max-w-6xl mx-auto">
            <PageHeader
                title="Mes documents"
                subtitle="Dépôt et gestion de vos documents pour les analyses de conformité"
                eyebrow="Espace client"
                icon={CloudArrowUpIcon}
                accent="blue"
            >
                <Button onClick={() => setUploadActif(true)} variant="primary">
                    <CloudArrowUpIcon className="w-4 h-4" /> Uploader
                </Button>
            </PageHeader>

            <div className="grid grid-cols-2 md:grid-cols-3 gap-4 mb-4">
                <Card className="p-4 flex items-center gap-3">
                    <div className="w-10 h-10 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center">
                        <DocumentTextIcon className="w-5 h-5" />
                    </div>
                    <div>
                        <p className="text-xs text-gray-500">Total documents</p>
                        <p className="text-xl font-bold text-gray-900">{stats.total}</p>
                    </div>
                </Card>
                <Card className="p-4 flex items-center gap-3">
                    <div className="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
                        <CheckCircleIcon className="w-5 h-5" />
                    </div>
                    <div>
                        <p className="text-xs text-gray-500">Indexés</p>
                        <p className="text-xl font-bold text-gray-900">{stats.indexe}</p>
                    </div>
                </Card>
                <Card className="p-4 flex items-center gap-3">
                    <div className="w-10 h-10 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center">
                        <ClockIcon className="w-5 h-5" />
                    </div>
                    <div>
                        <p className="text-xs text-gray-500">En traitement</p>
                        <p className="text-xl font-bold text-gray-900">{stats.enCours}</p>
                    </div>
                </Card>
            </div>

            {/* Recap questionnaires */}
            {stats.nbQuestionnaires > 0 && (
                <Card className="p-5 mb-6 bg-gradient-to-br from-purple-50 to-blue-50 border-purple-200">
                    <div className="flex items-start gap-4 flex-wrap">
                        <div className="w-12 h-12 rounded-xl bg-purple-600 text-white flex items-center justify-center shrink-0">
                            <DocumentTextIcon className="w-6 h-6" />
                        </div>
                        <div className="flex-1 min-w-[220px]">
                            <h3 className="font-semibold text-gray-900 mb-1">
                                Récapitulatif des questionnaires
                            </h3>
                            <p className="text-xs text-gray-600">
                                {stats.nbQuestionnaires} questionnaire{stats.nbQuestionnaires > 1 ? 's' : ''} détecté{stats.nbQuestionnaires > 1 ? 's' : ''} dans votre espace documentaire.
                            </p>
                        </div>
                        <div className="flex items-center gap-4 flex-wrap">
                            <div className="text-center">
                                <p className="text-2xl font-bold text-gray-900">{stats.totalQuestions}</p>
                                <p className="text-xs text-gray-500 uppercase">Questions</p>
                            </div>
                            <div className="text-center">
                                <p className="text-2xl font-bold text-emerald-700">{stats.totalRepondues}</p>
                                <p className="text-xs text-emerald-700 uppercase">Répondues</p>
                            </div>
                            <div className="text-center">
                                <p className={`text-2xl font-bold ${totalNonRepondues > 0 ? 'text-red-600' : 'text-gray-400'}`}>{totalNonRepondues}</p>
                                <p className="text-xs text-red-600 uppercase">Non répondues</p>
                            </div>
                            <div className="text-center">
                                <p className="text-2xl font-bold text-blue-700">
                                    {stats.totalQuestions > 0 ? Math.round((stats.totalRepondues / stats.totalQuestions) * 100) : 0}%
                                </p>
                                <p className="text-xs text-blue-700 uppercase">Couverture</p>
                            </div>
                        </div>
                    </div>
                    {totalNonRepondues > 0 && (
                        <div className="mt-4 pt-4 border-t border-purple-100 text-xs text-amber-700 flex items-start gap-2">
                            <ExclamationTriangleIcon className="w-4 h-4 shrink-0 mt-0.5" />
                            <span>
                                <strong>{totalNonRepondues} question{totalNonRepondues > 1 ? 's' : ''} sans réponse.</strong> Les analyses d'écarts détecteront automatiquement ces manques comme points de non-conformité.
                            </span>
                        </div>
                    )}
                </Card>
            )}

            <Card className="mb-4">
                <div className="px-6 py-4 flex items-center justify-between flex-wrap gap-3">
                    <div className="flex items-center gap-3 flex-wrap">
                        <p className="text-sm text-gray-500">
                            <span className="font-bold text-gray-900">{filtered.length}</span> document{filtered.length > 1 ? 's' : ''}
                            {estAsc && documentsParClient.length > 0 && (
                                <> sur <span className="font-bold text-gray-900">{documentsParClient.length}</span> entreprise{documentsParClient.length > 1 ? 's' : ''}</>
                            )}
                        </p>
                        {estAsc && documentsParClient.length > 1 && (
                            <div className="flex items-center gap-1 text-xs">
                                <button onClick={toutOuvrir} className="px-2 py-1 rounded hover:bg-blue-50 text-blue-700 font-medium">Tout déplier</button>
                                <span className="text-gray-300">|</span>
                                <button onClick={toutFermer} className="px-2 py-1 rounded hover:bg-blue-50 text-blue-700 font-medium">Tout replier</button>
                            </div>
                        )}
                    </div>
                    <input
                        type="text"
                        value={filterText}
                        onChange={e => setFilterText(e.target.value)}
                        placeholder="Rechercher..."
                        className="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 outline-none w-64"
                    />
                </div>
            </Card>

            {/* Vue groupee par client (ASC) ou table plate (client) */}
            {!estAsc && (
                <Card>
                    <DataTableWrapper columns={columns} data={filtered} loading={loading} />
                </Card>
            )}
            {estAsc && (
                <>
                    {loading && (
                        <Card className="p-8 text-center">
                            <p className="text-sm text-gray-500">Chargement...</p>
                        </Card>
                    )}
                    {!loading && documentsParClient.length === 0 && (
                        <Card className="p-10 text-center">
                            <p className="text-sm text-gray-500">Aucun document ne correspond à vos critères.</p>
                        </Card>
                    )}
                    <div className="space-y-3">
                        {!loading && documentsParClient.map(groupe => {
                            const ouvert = groupesOuverts[groupe.id] !== false;
                            const nbIndexes = groupe.items.filter(d => d.statut === 'indexe').length;
                            const nbEnCours = groupe.items.filter(d => ['en_attente', 'en_traitement'].includes(d.statut)).length;
                            return (
                                <Card key={groupe.id} variant="elevated" className="overflow-hidden">
                                    <button
                                        type="button"
                                        onClick={() => toggleGroupe(groupe.id)}
                                        className="w-full flex items-center justify-between gap-3 px-5 py-3 bg-gradient-to-r from-blue-50/60 via-indigo-50/40 to-white hover:from-blue-100 transition"
                                    >
                                        <div className="flex items-center gap-3 min-w-0">
                                            {ouvert
                                                ? <ChevronDownIcon className="w-4 h-4 text-blue-700 shrink-0" />
                                                : <ChevronRightIcon className="w-4 h-4 text-blue-700 shrink-0" />}
                                            <BuildingOffice2Icon className="w-5 h-5 text-blue-700 shrink-0" />
                                            <span className="font-semibold text-gray-900 truncate">{groupe.raison_sociale}</span>
                                            <Badge variant="info">{groupe.items.length}</Badge>
                                        </div>
                                        <div className="flex items-center gap-2 shrink-0">
                                            {nbIndexes > 0 && <Badge variant="success">{nbIndexes} indexé{nbIndexes > 1 ? 's' : ''}</Badge>}
                                            {nbEnCours > 0 && <Badge variant="warning">{nbEnCours} en cours</Badge>}
                                        </div>
                                    </button>
                                    {ouvert && (
                                        <DataTableWrapper
                                            columns={columns}
                                            data={groupe.items}
                                            loading={false}
                                            pagination={groupe.items.length > 10}
                                        />
                                    )}
                                </Card>
                            );
                        })}
                    </div>
                </>
            )}

            {/* Modal upload */}
            <Modal
                open={uploadActif}
                onClose={() => setUploadActif(false)}
                title="Uploader des documents"
                subtitle={estAsc ? "Choisissez le client cible, puis glissez vos fichiers" : "Glissez-déposez ou cliquez pour sélectionner vos fichiers"}
                icon={CloudArrowUpIcon}
                accent="blue"
                size="md"
                footer={(
                    <Button variant="secondary" onClick={() => setUploadActif(false)}>Fermer</Button>
                )}
            >
                {estAsc && (
                    <div className="mb-4">
                        <Select
                            label="Client cible *"
                            value={uploadClientId}
                            onChange={e => setUploadClientId(e.target.value)}
                            icon={BuildingOffice2Icon}
                            helper={uploadClientId
                                ? `Les fichiers déposés seront rattachés à ${clientsDisponibles.find(c => String(c.id) === String(uploadClientId))?.raison_sociale || 'ce client'}.`
                                : "Sélectionnez l'entreprise pour laquelle vous voulez déposer ces fichiers."}
                        >
                            <option value="">— Sélectionner un client —</option>
                            {clientsDisponibles.map(c => (
                                <option key={c.id} value={c.id}>{c.raison_sociale}</option>
                            ))}
                        </Select>
                    </div>
                )}

                <div
                    onDragOver={e => { if (estAsc && !uploadClientId) return; e.preventDefault(); setDragOver(true); }}
                    onDragLeave={() => setDragOver(false)}
                    onDrop={e => { if (estAsc && !uploadClientId) { e.preventDefault(); alertError("Sélectionnez d'abord le client cible."); return; } handleDrop(e); }}
                    className={`relative border-2 border-dashed rounded-2xl p-10 text-center transition-all ${estAsc && !uploadClientId ? 'border-gray-200 bg-gray-50/50 cursor-not-allowed opacity-60' : `cursor-pointer ${dragOver ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-blue-400 hover:bg-blue-50/30'}`}`}
                >
                    <input
                        type="file"
                        multiple
                        accept=".pdf,.docx,.doc,.png,.jpg,.jpeg,.webp,.tiff,.bmp,.gif"
                        onChange={e => handleFiles(Array.from(e.target.files))}
                        disabled={estAsc && !uploadClientId}
                        className={`absolute inset-0 opacity-0 ${estAsc && !uploadClientId ? 'cursor-not-allowed' : 'cursor-pointer'}`}
                    />
                    <div className="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-100 to-indigo-100 mx-auto mb-3 flex items-center justify-center">
                        <CloudArrowUpIcon className="w-7 h-7 text-blue-600" />
                    </div>
                    <p className="font-semibold text-gray-800">
                        {estAsc && !uploadClientId ? 'Choisissez d\'abord le client ci-dessus' : 'Glissez-déposez ou cliquez'}
                    </p>
                    <p className="text-xs text-gray-500 mt-1">PDF, DOCX, DOC, images (PNG, JPG, WEBP, TIFF) — plusieurs fichiers OK (max 20 Mo chacun)</p>
                </div>

                {uploadEnCours.length > 0 && (
                    <div className="mt-4 space-y-2">
                        {uploadEnCours.map(u => (
                            <div key={u.id} className="flex items-center gap-3 p-2.5 bg-blue-50/60 ring-1 ring-blue-100 rounded-lg text-sm">
                                <div className="w-4 h-4 rounded-full border-2 border-blue-500 border-t-transparent animate-spin" />
                                <span className="flex-1 truncate font-medium text-gray-700">{u.name}</span>
                                <span className="text-xs text-blue-700 font-semibold">Upload...</span>
                            </div>
                        ))}
                    </div>
                )}
            </Modal>

            <Drawer
                open={!!documentActif}
                onClose={() => setDocumentActif(null)}
                title={documentActif?.titre || ''}
                subtitle={documentActif?.nom_fichier_original || ''}
                icon={DocumentTextIcon}
                accent="blue"
                size="xl"
            >
                {documentActif && (
                    <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p className="text-xs text-gray-500">Statut</p>
                                    <Badge variant={statutVariant[documentActif.statut]}>{statutLabel[documentActif.statut]}</Badge>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500">Taille</p>
                                    <p className="text-gray-800 font-medium">{(documentActif.taille_octets / 1024 / 1024).toFixed(2)} Mo</p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500">Fragments indexés</p>
                                    <p className="text-gray-800 font-medium">{documentActif.chunks_count || 0}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500">Uploadé le</p>
                                    <p className="text-gray-800 font-medium">{new Date(documentActif.created_at).toLocaleString('fr-FR')}</p>
                                </div>
                            </div>

                            {/* Bloc questionnaire (si detecte) */}
                            {documentActif.is_questionnaire && documentActif.questions_data?.length > 0 && (
                                <div className="border-t border-gray-100 pt-4">
                                    <div className="flex items-center gap-2 mb-3">
                                        <Badge variant="purple">Questionnaire</Badge>
                                        <span className="text-sm text-gray-600">
                                            <span className="font-bold text-emerald-700">{documentActif.nb_questions_repondues}</span>
                                            {' / '}
                                            <span className="font-bold">{documentActif.nb_questions}</span>
                                            {' questions répondues'}
                                        </span>
                                    </div>
                                    <div className="space-y-2 max-h-[500px] overflow-y-auto">
                                        {documentActif.questions_data.map((q, idx) => (
                                            <div key={idx} className={`p-3 rounded-lg border ${q.repondu ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200'}`}>
                                                <div className="flex items-start gap-2 mb-1">
                                                    <span className={`text-xs font-bold px-2 py-0.5 rounded ${q.repondu ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white'}`}>
                                                        Q{q.numero}
                                                    </span>
                                                    {q.repondu ? (
                                                        <span className="text-xs text-emerald-700 font-medium">Répondue</span>
                                                    ) : (
                                                        <span className="text-xs text-red-700 font-medium">Non répondue</span>
                                                    )}
                                                </div>
                                                <p className="text-sm text-gray-900 font-medium mb-1">{decoderTexte(q.question)}</p>
                                                {q.repondu ? (
                                                    <p className="text-sm text-gray-700 whitespace-pre-wrap">{decoderTexte(q.reponse)}</p>
                                                ) : (
                                                    <p className="text-sm text-red-600 italic">(Cette question n'a pas de réponse)</p>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Apercu texte brut (documents non-questionnaire OU complement) */}
                            {!documentActif.is_questionnaire && (
                                <div className="border-t border-gray-100 pt-4">
                                    <h4 className="font-semibold text-gray-900 text-sm mb-2">Aperçu du contenu extrait</h4>
                                    {documentActif.contenu_apercu ? (
                                        <div className="bg-gray-50 border border-gray-200 rounded-lg p-4 text-sm text-gray-700 whitespace-pre-wrap max-h-[500px] overflow-y-auto font-mono">
                                            {documentActif.contenu_apercu}
                                            {documentActif.contenu_apercu.length >= 5000 && (
                                                <p className="text-xs text-gray-500 italic mt-2">(Aperçu tronqué à 5000 caractères)</p>
                                            )}
                                        </div>
                                    ) : (
                                        <p className="text-sm text-gray-500 italic">Contenu non encore extrait ou document vide.</p>
                                    )}
                                </div>
                            )}
                    </div>
                )}
            </Drawer>
        </div>
    );
}
