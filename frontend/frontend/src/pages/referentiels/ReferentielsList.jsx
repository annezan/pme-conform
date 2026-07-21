/**
 * Page ReferentielsList — Gestion des referentiels (lois, normes).
 * Admin/manager : upload, modification. Tous : consultation.
 */

import { useState, useEffect, useMemo, useRef } from 'react';
import CreatableSelect from 'react-select/creatable';
import { listReferentiels, getReferentiel, createReferentiel, updateReferentiel, deleteReferentiel, reindexerReferentiel, uploaderFichierReferentiel, saisirContenuReferentiel } from '@/api/referentiels';
import api from '@/api/client';
import { alertSuccess, alertError, confirmDelete, confirmAction } from '@/utils/alerts';
import { useAuth } from '@/contexts/AuthContext';
import DataTableWrapper from '@/components/ui/DataTableWrapper';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import PageHeader from '@/components/ui/PageHeader';
import { Input, Select, Textarea } from '@/components/ui/Input';
import TableActions, { DeleteAction, EditAction, ToggleAction } from '@/components/ui/TableActions';
import Modal from '@/components/ui/Modal';
import { PlusIcon, XMarkIcon, BookOpenIcon, CloudArrowUpIcon, ArrowPathIcon, DocumentCheckIcon, ExclamationTriangleIcon, ChevronDownIcon, ChevronRightIcon, Squares2X2Icon, TableCellsIcon, DocumentTextIcon, PencilSquareIcon } from '@heroicons/react/24/outline';

const typeVariant = {
    loi: 'danger',
    decret: 'warning',
    arrete: 'info',
    directive: 'cyan',
    norme: 'purple',
    guide: 'gray',
    autre: 'gray',
};

const statutVariant = { actif: 'success', obsolete: 'gray', brouillon: 'warning' };

export default function ReferentielsList() {
    const { hasPermission } = useAuth();
    const peutGerer = hasPermission('view-all-referentiels');

    const [referentiels, setReferentiels] = useState([]);
    const [groupes, setGroupes] = useState([]); // [{secteur, referentiels:[]}]
    const [secteursOptions, setSecteursOptions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [editingId, setEditingId] = useState(null); // null = creation, sinon ID
    const [saving, setSaving] = useState(false);
    const [filterText, setFilterText] = useState('');
    const [vueMode, setVueMode] = useState('groupes'); // 'groupes' | 'tableau'
    const [secteursOuverts, setSecteursOuverts] = useState({}); // { 'BANQUE': true }

    // AUDREY : referentiel.secteurs_activite_ids (array d'IDs) au lieu d'un array de strings.
    const [form, setForm] = useState({
        code: '',
        titre: '',
        description: '',
        autorite: 'ARTCI',
        version: '',
        date_publication: '',
        type: 'loi',
        secteurs_activite_ids: [], // [{value: id, label: nom}]
    });
    const [fichier, setFichier] = useState(null);

    // Upload fichier pour referentiel existant
    const [uploadCible, setUploadCible] = useState(null); // referentiel cible
    const [fichierUpload, setFichierUpload] = useState(null);
    const [uploading, setUploading] = useState(false);
    const [modeUpload, setModeUpload] = useState('fichier'); // 'fichier' | 'texte'
    const [contenuTexte, setContenuTexte] = useState('');
    const [extractionVide, setExtractionVide] = useState(false);

    const pollingRef = useRef(null);

    const charger = (showLoader = true) => {
        if (showLoader) setLoading(true);
        return Promise.all([
            listReferentiels({ per_page: 100 }).then(r => r.data || []),
            api.get('/ref/referentiels-par-secteur').then(r => r.data.data || []),
        ])
            .then(([refs, grp]) => {
                setReferentiels(refs);
                setGroupes(grp);
                setSecteursOuverts(prev => {
                    const next = { ...prev };
                    grp.forEach(g => { if (next[g.secteur] === undefined) next[g.secteur] = true; });
                    return next;
                });
            })
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        // AUDREY : /secteurs-activite-liste renvoie [{id, nom, code}] — on a besoin de l'id.
        api.get('/secteurs-activite-liste')
            .then(r => setSecteursOptions((r.data.data || []).map(s => ({ value: s.id, label: s.nom }))))
            .catch(() => setSecteursOptions([]));
    }, []);

    useEffect(() => {
        charger();
        // Polling leger : si au moins 1 referentiel a un fichier mais 0 chunks (= indexation en cours),
        // on rafraichit toutes les 3s jusqu'a ce que tous soient soit sans fichier, soit indexes.
        pollingRef.current = setInterval(() => {
            setReferentiels(current => {
                const enCours = current.some(r => (r.media?.length || 0) > 0 && (r.chunks_count || 0) === 0);
                if (enCours) charger(false);
                return current;
            });
        }, 3000);
        return () => clearInterval(pollingRef.current);
    }, []);

    const ouvrirCreation = () => {
        setEditingId(null);
        setForm({ code: '', titre: '', description: '', autorite: 'ARTCI', version: '', date_publication: '', type: 'loi', secteurs_activite_ids: [] });
        setFichier(null);
        setShowModal(true);
    };

    const ouvrirEdition = async (ref) => {
        setEditingId(ref.id);
        setShowModal(true);
        // Pre-remplit avec les valeurs courantes ; on completera ensuite avec la fiche
        // detaillee (qui peut contenir des champs absents de la liste).
        try {
            const r = await getReferentiel(ref.id);
            const full = r.referentiel || ref;
            const secteursRaw = full.secteursActivite || full.secteurs_activite || [];
            const secteursOpts = (Array.isArray(secteursRaw) ? secteursRaw : [])
                .filter(s => s && typeof s === 'object' && s.id)
                .map(s => ({ value: s.id, label: s.nom }));
            setForm({
                code: full.code || '',
                titre: full.titre || '',
                description: full.description || '',
                autorite: full.autorite || '',
                version: full.version || '',
                date_publication: full.date_publication ? String(full.date_publication).slice(0, 10) : '',
                type: full.type || 'loi',
                secteurs_activite_ids: secteursOpts,
            });
        } catch {
            alertError('Impossible de charger les détails du référentiel');
        }
    };

    const fermerModalForm = () => {
        setShowModal(false);
        setEditingId(null);
        setForm({ code: '', titre: '', description: '', autorite: 'ARTCI', version: '', date_publication: '', type: 'loi', secteurs_activite_ids: [] });
        setFichier(null);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!form.secteurs_activite_ids || form.secteurs_activite_ids.length === 0) {
            alertError('Veuillez sélectionner au moins un secteur d\'activité.');
            return;
        }
        setSaving(true);
        try {
            if (editingId) {
                // En edition, on envoie du JSON (pas de fichier — pour ca il y a la modal upload dediee).
                const payload = {
                    code: form.code,
                    titre: form.titre,
                    description: form.description || null,
                    autorite: form.autorite || null,
                    version: form.version || null,
                    date_publication: form.date_publication || null,
                    type: form.type,
                    secteurs_activite_ids: (form.secteurs_activite_ids || []).map(o => o.value),
                };
                await updateReferentiel(editingId, payload);
                alertSuccess('Référentiel mis à jour.');
            } else {
                const fd = new FormData();
                Object.entries(form).forEach(([k, v]) => {
                    if (k === 'secteurs_activite_ids') {
                        (v || []).forEach(opt => fd.append('secteurs_activite_ids[]', opt.value));
                        return;
                    }
                    if (v === '' || v === null) return;
                    fd.append(k, v);
                });
                if (fichier) fd.append('fichier', fichier);
                await createReferentiel(fd);
                alertSuccess('Référentiel créé. Indexation en cours.');
            }
            fermerModalForm();
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur lors de l\'enregistrement.');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (ref) => {
        if (await confirmDelete(ref.code + ' - ' + ref.titre)) {
            try {
                await deleteReferentiel(ref.id);
                alertSuccess('Référentiel supprimé');
                charger();
            } catch {
                alertError('Erreur lors de la suppression');
            }
        }
    };

    const handleReindex = async (ref) => {
        if (await confirmAction('Relancer l\'indexation ?', 'Ré-indexation')) {
            try {
                await reindexerReferentiel(ref.id);
                alertSuccess('Ré-indexation lancée');
                setTimeout(charger, 1500);
            } catch {
                alertError('Erreur de ré-indexation');
            }
        }
    };

    const fermerModalUpload = () => {
        setUploadCible(null);
        setFichierUpload(null);
        setContenuTexte('');
        setModeUpload('fichier');
        setExtractionVide(false);
    };

    const handleUploadFichier = async (e) => {
        e.preventDefault();
        if (!fichierUpload || !uploadCible) return;
        setUploading(true);
        setExtractionVide(false);
        try {
            const res = await uploaderFichierReferentiel(uploadCible.id, fichierUpload);
            if (res.extraction_vide) {
                alertError(res.message);
                setExtractionVide(true);
                setModeUpload('texte');
                setTimeout(charger, 1000);
            } else {
                alertSuccess(res.message || 'Fichier uploadé. Indexation lancée.');
                fermerModalUpload();
                setTimeout(charger, 1500);
            }
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur lors de l\'upload');
        } finally {
            setUploading(false);
        }
    };

    const handleSaisirTexte = async (e) => {
        e.preventDefault();
        if (!contenuTexte.trim() || !uploadCible) return;
        if (contenuTexte.length < 200) {
            alertError('Le contenu doit faire au moins 200 caractères.');
            return;
        }
        setUploading(true);
        try {
            const res = await saisirContenuReferentiel(uploadCible.id, contenuTexte);
            alertSuccess(res.message);
            fermerModalUpload();
            setTimeout(charger, 1500);
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur lors de l\'enregistrement');
        } finally {
            setUploading(false);
        }
    };

    const columns = useMemo(() => [
        {
            name: 'Code',
            selector: row => row.code,
            sortable: true,
            width: '200px',
            cell: row => <span className="font-mono text-xs font-semibold text-blue-700">{row.code}</span>,
        },
        {
            name: 'Titre',
            selector: row => row.titre,
            sortable: true,
            grow: 3,
            cell: row => (
                <div className="py-2">
                    <p className="font-medium text-gray-900 text-sm leading-snug">{row.titre}</p>
                    {row.autorite && <p className="text-xs text-gray-500 mt-0.5">{row.autorite} {row.version && `- ${row.version}`}</p>}
                </div>
            ),
        },
        {
            name: 'Type',
            selector: row => row.type,
            sortable: true,
            width: '120px',
            cell: row => <Badge variant={typeVariant[row.type]}>{row.type}</Badge>,
        },
        {
            name: 'État',
            selector: row => row.chunks_count || 0,
            sortable: true,
            width: '170px',
            cell: row => {
                const aFichier = (row.media?.length || 0) > 0;
                const chunks = row.chunks_count || 0;
                if (chunks > 0) {
                    return (
                        <div className="flex items-center gap-1.5">
                            <DocumentCheckIcon className="w-4 h-4 text-emerald-600" />
                            <span className="text-xs font-semibold text-emerald-700">{chunks} exigences</span>
                        </div>
                    );
                }
                if (aFichier) {
                    return (
                        <div className="flex items-center gap-1.5">
                            <div className="w-3 h-3 rounded-full border-2 border-blue-500 border-t-transparent animate-spin" />
                            <span className="text-xs text-blue-700">Indexation...</span>
                        </div>
                    );
                }
                return (
                    <div className="flex items-center gap-1.5">
                        <ExclamationTriangleIcon className="w-4 h-4 text-amber-500" />
                        <span className="text-xs text-amber-700 font-medium">Aucun fichier</span>
                    </div>
                );
            },
        },
        {
            name: 'Statut',
            selector: row => row.statut,
            sortable: true,
            width: '110px',
            cell: row => <Badge variant={statutVariant[row.statut]}>{row.statut}</Badge>,
        },
        peutGerer && {
            name: 'Actions',
            width: '220px',
            right: true,
            cell: row => (
                <TableActions>
                    <EditAction onClick={() => ouvrirEdition(row)} />
                    <button
                        onClick={() => setUploadCible(row)}
                        title={(row.media?.length || 0) > 0 ? 'Remplacer le fichier' : 'Uploader un fichier'}
                        className={`w-8 h-8 rounded-lg ring-1 flex items-center justify-center transition-all duration-200 hover:shadow-sm ${(row.media?.length || 0) > 0 ? 'text-blue-600 bg-blue-50 hover:bg-blue-100 ring-blue-200' : 'text-amber-600 bg-amber-50 hover:bg-amber-100 ring-amber-200'}`}
                    >
                        <CloudArrowUpIcon className="w-4 h-4" />
                    </button>
                    <ToggleAction onClick={() => handleReindex(row)} label="Re-indexer" active={true} />
                    <DeleteAction onClick={() => handleDelete(row)} />
                </TableActions>
            ),
        },
    ].filter(Boolean), [peutGerer]);

    const filtered = referentiels.filter(r =>
        r.titre?.toLowerCase().includes(filterText.toLowerCase()) ||
        r.code?.toLowerCase().includes(filterText.toLowerCase()) ||
        r.autorite?.toLowerCase().includes(filterText.toLowerCase())
    );

    return (
        <div className="p-6 lg:p-8 max-w-7xl mx-auto">
            <PageHeader
                title="Référentiels réglementaires"
                subtitle="Lois, décrets et normes utilisés pour l'analyse d'écarts"
                eyebrow="Corpus réglementaire"
                icon={BookOpenIcon}
                accent="indigo"
            >
                {peutGerer && (
                    <Button onClick={ouvrirCreation} variant="primary">
                        <PlusIcon className="w-4 h-4" /> Nouveau référentiel
                    </Button>
                )}
            </PageHeader>

            <Card>
                <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
                    <div className="flex items-center gap-2 text-sm text-gray-500">
                        <BookOpenIcon className="w-4 h-4" />
                        {filtered.length} référentiel(s)
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="flex items-center bg-gray-100 rounded-lg p-1">
                            <button
                                type="button"
                                onClick={() => setVueMode('groupes')}
                                className={`px-3 py-1.5 text-xs font-medium rounded-md flex items-center gap-1.5 transition-all ${vueMode === 'groupes' ? 'bg-white text-blue-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
                            >
                                <Squares2X2Icon className="w-4 h-4" />
                                Par secteur
                            </button>
                            <button
                                type="button"
                                onClick={() => setVueMode('tableau')}
                                className={`px-3 py-1.5 text-xs font-medium rounded-md flex items-center gap-1.5 transition-all ${vueMode === 'tableau' ? 'bg-white text-blue-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
                            >
                                <TableCellsIcon className="w-4 h-4" />
                                Tableau
                            </button>
                        </div>
                        <input
                            type="text"
                            value={filterText}
                            onChange={e => setFilterText(e.target.value)}
                            placeholder="Rechercher..."
                            className="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 outline-none w-64"
                        />
                    </div>
                </div>

                {vueMode === 'tableau' && (
                    <DataTableWrapper columns={columns} data={filtered} loading={loading} />
                )}

                {vueMode === 'groupes' && (
                    <div className="p-6 space-y-3">
                        {loading && <p className="text-sm text-gray-500">Chargement...</p>}
                        {!loading && groupes.length === 0 && (
                            <p className="text-sm text-gray-500 italic">Aucun référentiel disponible.</p>
                        )}
                        {groupes.map(g => {
                            const refsFiltres = (g.referentiels || []).filter(r =>
                                !filterText
                                || r.titre?.toLowerCase().includes(filterText.toLowerCase())
                                || r.code?.toLowerCase().includes(filterText.toLowerCase())
                                || r.autorite?.toLowerCase().includes(filterText.toLowerCase())
                            );
                            if (filterText && refsFiltres.length === 0) return null;
                            const ouvert = secteursOuverts[g.secteur];
                            return (
                                <div key={g.secteur} className="border border-gray-200 rounded-xl overflow-hidden bg-white">
                                    <button
                                        type="button"
                                        onClick={() => setSecteursOuverts(s => ({ ...s, [g.secteur]: !s[g.secteur] }))}
                                        className="w-full flex items-center justify-between px-5 py-3.5 bg-gradient-to-r from-blue-50 to-indigo-50 hover:from-blue-100 hover:to-indigo-100 transition-colors"
                                    >
                                        <div className="flex items-center gap-3">
                                            {ouvert
                                                ? <ChevronDownIcon className="w-5 h-5 text-blue-700" />
                                                : <ChevronRightIcon className="w-5 h-5 text-blue-700" />}
                                            <span className="font-semibold text-gray-900">{g.secteur}</span>
                                            <Badge variant="info">{refsFiltres.length}</Badge>
                                            {g.secteur === 'Transversal' && (
                                                <span className="text-xs text-gray-500 italic">applicable à tous secteurs</span>
                                            )}
                                        </div>
                                    </button>
                                    {ouvert && (
                                        <div className="divide-y divide-gray-100">
                                            {refsFiltres.map(r => (
                                                <div key={r.id} className="px-5 py-3 flex items-start justify-between gap-4 hover:bg-gray-50">
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-center gap-2 mb-1">
                                                            <Badge variant={typeVariant[r.type]}>{r.type}</Badge>
                                                            <span className="font-mono text-xs font-semibold text-blue-700">{r.code}</span>
                                                        </div>
                                                        <p className="font-medium text-gray-900 text-sm">{r.titre}</p>
                                                        {r.autorite && (
                                                            <p className="text-xs text-gray-500 mt-0.5">{r.autorite}</p>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                            {refsFiltres.length === 0 && (
                                                <div className="px-5 py-4 text-sm text-gray-500 italic">Aucun référentiel actif dans cette catégorie.</div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
            </Card>

            {/* Modal upload fichier OU saisie texte */}
            <Modal
                open={!!uploadCible}
                onClose={() => !uploading && fermerModalUpload()}
                title="Alimenter le référentiel"
                subtitle={uploadCible ? `${uploadCible.code} — ${uploadCible.titre}` : ''}
                icon={CloudArrowUpIcon}
                accent="cyan"
                size="xl"
                closeOnBackdrop={!uploading}
                closeOnEsc={!uploading}
                bodyClassName="!px-0 !py-0"
            >
                {uploadCible && (
                    <>
                        {/* Onglets */}
                        <div className="flex gap-1 px-6 pt-4 border-b border-gray-100">
                            <button
                                type="button"
                                onClick={() => setModeUpload('fichier')}
                                className={`px-4 py-2 text-sm font-medium rounded-t-lg transition-all ${modeUpload === 'fichier' ? 'bg-blue-50 text-blue-700 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'}`}
                            >
                                Uploader un fichier
                            </button>
                            <button
                                type="button"
                                onClick={() => setModeUpload('texte')}
                                className={`px-4 py-2 text-sm font-medium rounded-t-lg transition-all ${modeUpload === 'texte' ? 'bg-blue-50 text-blue-700 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'}`}
                            >
                                Saisir le texte
                            </button>
                        </div>

                        {extractionVide && (
                            <div className="mx-6 mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-900 flex items-start gap-2">
                                <ExclamationTriangleIcon className="w-5 h-5 shrink-0 mt-0.5" />
                                <div>
                                    <strong>PDF non textuel détecté.</strong> Le fichier est probablement une image scannée.
                                    Collez le texte intégral du référentiel ci-dessous pour l'indexer.
                                </div>
                            </div>
                        )}

                        {modeUpload === 'fichier' && (
                            <form onSubmit={handleUploadFichier} className="p-6 space-y-4">
                                {(uploadCible.media?.length || 0) > 0 && !extractionVide && (
                                    <div className="p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                                        Un fichier existe déjà. Il sera remplacé et le référentiel ré-indexé.
                                    </div>
                                )}

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Fichier (PDF ou DOCX)</label>
                                    <div className="relative border-2 border-dashed border-gray-300 rounded-lg p-6 hover:border-blue-400 transition-colors">
                                        <input
                                            type="file"
                                            accept=".pdf,.docx,.doc"
                                            onChange={e => setFichierUpload(e.target.files[0])}
                                            className="absolute inset-0 opacity-0 cursor-pointer"
                                        />
                                        <div className="flex flex-col items-center gap-2">
                                            <CloudArrowUpIcon className="w-8 h-8 text-gray-400" />
                                            {fichierUpload ? (
                                                <>
                                                    <p className="text-sm font-medium text-emerald-700">{fichierUpload.name}</p>
                                                    <p className="text-xs text-gray-500">{(fichierUpload.size / 1024 / 1024).toFixed(2)} Mo</p>
                                                </>
                                            ) : (
                                                <>
                                                    <p className="text-sm font-medium text-gray-700">Cliquez pour sélectionner</p>
                                                    <p className="text-xs text-gray-400">PDF, DOCX, DOC (max 50 Mo)</p>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                    <p className="text-xs text-gray-500 mt-2">
                                        Privilégiez un PDF avec couche texte (sélectionnable). Les PDF scannés ne seront pas indexables — utilisez l'onglet « Saisir le texte ».
                                    </p>
                                </div>

                                <div className="flex justify-end gap-3 pt-2">
                                    <Button variant="secondary" type="button" onClick={fermerModalUpload} disabled={uploading}>Annuler</Button>
                                    <Button type="submit" disabled={!fichierUpload || uploading}>
                                        {uploading ? 'Upload...' : 'Uploader & indexer'}
                                    </Button>
                                </div>
                            </form>
                        )}

                        {modeUpload === 'texte' && (
                            <form onSubmit={handleSaisirTexte} className="p-6 space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Contenu texte du référentiel</label>
                                    <Textarea
                                        value={contenuTexte}
                                        onChange={e => setContenuTexte(e.target.value)}
                                        rows={18}
                                        placeholder={"Collez ici le texte intégral du référentiel (Article 1, Article 2, ...).\n\nPour une meilleure détection des exigences, préservez la structure originale : numérotation des articles, titres, alinéas."}
                                        className="font-mono text-xs"
                                    />
                                    <div className="flex justify-between items-center mt-1 text-xs text-gray-500">
                                        <span>Minimum 200 caractères</span>
                                        <span className={contenuTexte.length >= 200 ? 'text-emerald-600 font-semibold' : 'text-gray-400'}>
                                            {contenuTexte.length} caractères
                                        </span>
                                    </div>
                                </div>
                                <div className="p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-800">
                                    <strong>Conseil :</strong> le moteur détecte automatiquement les articles (Article 1, Art. 2.3...) pour créer un chunk par exigence. Si le texte ne contient pas ces marqueurs, il sera découpé en blocs de 1200 caractères.
                                </div>

                                <div className="flex justify-end gap-3 pt-2">
                                    <Button variant="secondary" type="button" onClick={fermerModalUpload} disabled={uploading}>Annuler</Button>
                                    <Button variant="primary" type="submit" loading={uploading} disabled={contenuTexte.length < 200 || uploading}>
                                        {uploading ? 'Enregistrement...' : 'Enregistrer & indexer'}
                                    </Button>
                                </div>
                            </form>
                        )}
                    </>
                )}
            </Modal>

            <Modal
                open={showModal}
                onClose={fermerModalForm}
                title={editingId ? 'Modifier le référentiel' : 'Nouveau référentiel'}
                subtitle={editingId ? 'Mettre à jour les métadonnées (le fichier se modifie via le bouton « Uploader »)' : 'Ajouter une loi, un décret ou une norme au corpus'}
                icon={editingId ? PencilSquareIcon : DocumentTextIcon}
                accent="indigo"
                size="xl"
                footer={(
                    <>
                        <Button variant="secondary" type="button" onClick={fermerModalForm}>Annuler</Button>
                        <Button variant="primary" type="submit" form="ref-form" loading={saving}>
                            {saving
                                ? (editingId ? 'Enregistrement...' : 'Création...')
                                : (editingId ? 'Enregistrer' : 'Créer le référentiel')}
                        </Button>
                    </>
                )}
            >
                <form id="ref-form" onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <Input label="Code unique *" value={form.code} onChange={e => setForm({...form, code: e.target.value})} required placeholder="Ex: ARTCI-LOI-2013-450" />
                                <Select label="Type *" value={form.type} onChange={e => setForm({...form, type: e.target.value})}>
                                    <option value="loi">Loi</option>
                                    <option value="decret">Décret</option>
                                    <option value="arrete">Arrêté</option>
                                    <option value="directive">Directive</option>
                                    <option value="norme">Norme</option>
                                    <option value="guide">Guide</option>
                                    <option value="autre">Autre</option>
                                </Select>
                            </div>
                            <Input label="Titre *" value={form.titre} onChange={e => setForm({...form, titre: e.target.value})} required />
                            <Textarea label="Description" value={form.description} onChange={e => setForm({...form, description: e.target.value})} rows={2} />
                            <div className="grid grid-cols-3 gap-4">
                                <Input label="Autorité" value={form.autorite} onChange={e => setForm({...form, autorite: e.target.value})} placeholder="ARTCI" />
                                <Input label="Version" value={form.version} onChange={e => setForm({...form, version: e.target.value})} placeholder="2013" />
                                <Input label="Date de publication" type="date" value={form.date_publication} onChange={e => setForm({...form, date_publication: e.target.value})} />
                            </div>
                            <div>
                                <div className="flex items-center justify-between mb-1.5">
                                    <label className="block text-sm font-medium text-gray-700">
                                        Secteurs d'activité <span className="text-red-500">*</span>
                                    </label>
                                    <div className="flex items-center gap-2">
                                        <button
                                            type="button"
                                            onClick={() => setForm({...form, secteurs_activite_ids: secteursOptions})}
                                            disabled={secteursOptions.length === 0 || form.secteurs_activite_ids.length === secteursOptions.length}
                                            className="text-xs font-medium text-blue-600 hover:text-blue-800 disabled:text-gray-400 disabled:cursor-not-allowed"
                                        >
                                            Tous les secteurs
                                        </button>
                                        {form.secteurs_activite_ids.length > 0 && (
                                            <>
                                                <span className="text-gray-300">|</span>
                                                <button
                                                    type="button"
                                                    onClick={() => setForm({...form, secteurs_activite_ids: []})}
                                                    className="text-xs font-medium text-gray-500 hover:text-gray-700"
                                                >
                                                    Effacer
                                                </button>
                                            </>
                                        )}
                                    </div>
                                </div>
                                <CreatableSelect
                                    isMulti
                                    options={secteursOptions}
                                    value={form.secteurs_activite_ids}
                                    onChange={(val) => setForm({...form, secteurs_activite_ids: val || []})}
                                    placeholder={secteursOptions.length === 0 ? 'Chargement des secteurs...' : 'Sélectionnez les secteurs concernés...'}
                                    isValidNewOption={() => false}
                                    classNamePrefix="rs"
                                    isLoading={secteursOptions.length === 0}
                                    noOptionsMessage={() => 'Aucun secteur disponible (gérer dans Admin → Secteurs)'}
                                    required
                                />
                                <p className="text-xs text-gray-500 mt-1">
                                    Sélectionnez au moins un secteur, ou cliquez sur <span className="font-medium">Tous les secteurs</span> pour un référentiel transversal. Gérés dans <span className="font-medium">Admin → Secteurs d'activité</span>.
                                </p>
                            </div>

                            {!editingId && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Fichier du référentiel (PDF/DOCX)</label>
                                    <div className="relative border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-blue-400 transition-colors">
                                        <input type="file" accept=".pdf,.docx,.doc" onChange={e => setFichier(e.target.files[0])} className="absolute inset-0 opacity-0 cursor-pointer" />
                                        <div className="flex items-center justify-center gap-2 text-sm">
                                            <CloudArrowUpIcon className="w-5 h-5 text-gray-400" />
                                            {fichier ? (
                                                <span className="text-emerald-600 font-medium">{fichier.name}</span>
                                            ) : (
                                                <span className="text-gray-500">Cliquez pour sélectionner (indexation auto)</span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            )}

                </form>
            </Modal>
        </div>
    );
}
