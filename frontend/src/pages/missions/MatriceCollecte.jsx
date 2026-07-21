/**
 * MatriceCollecte — Methode 2 etape 1.
 *
 * Pour chaque item d'un pole, le client peut :
 *   - saisir une reponse textuelle ;
 *   - et/ou uploader un ou plusieurs documents justificatifs.
 *
 * Un bouton "Nouveau pole" permet d'ajouter dynamiquement un pole
 * personnalise a la matrice. Le bouton "Generer l'organigramme" enregistre
 * et redirige automatiquement vers /missions/:id/organigramme.
 */

import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import api, { getCsrfCookie } from '@/api/client';
import { deriverOrganigramme } from '@/api/matrices';
import { useAuth } from '@/contexts/AuthContext';
import { alertSuccess, alertError, confirmAction } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import Modal from '@/components/ui/Modal';
import { Input, Textarea } from '@/components/ui/Input';
import {
    ArrowLeftIcon,
    ArrowRightIcon,
    PaperAirplaneIcon,
    CloudArrowUpIcon,
    CheckIcon,
    TrashIcon,
    SparklesIcon,
    RectangleGroupIcon,
    PlusIcon,
    DocumentArrowUpIcon,
    PaperClipIcon,
} from '@heroicons/react/24/outline';

const STATUT_LABEL = {
    a_remplir: { label: 'À remplir', variant: 'gray' },
    en_cours: { label: 'En cours', variant: 'info' },
    remise: { label: 'Remise', variant: 'warning' },
    validee: { label: 'Validée', variant: 'success' },
};

export default function MatriceCollecte() {
    const { id } = useParams();
    const navigate = useNavigate();
    const { hasPermission } = useAuth();
    // "ASC" = utilisateur cote AS Consulting avec capacite a editer la matrice.
    // update-matrice est dans admin/manager/consultant, pas chez les clients.
    const estASC = hasPermission('update-matrice');
    const [matrice, setMatrice] = useState(null);
    const [poles, setPoles] = useState([]);
    const [reponsesLibres, setReponsesLibres] = useState('');
    const [saving, setSaving] = useState(false);
    const [derivation, setDerivation] = useState(false);

    // Etat upload par item : { [poleCode_itemCode]: { fichier, libelle, uploading } }
    const [uploadItem, setUploadItem] = useState({});

    // Modal "Nouveau pole"
    const [showNewPole, setShowNewPole] = useState(false);
    const [newPole, setNewPole] = useState({ pole: '', cibles: '', description: '', items: [{ libelle: '', attendu: '' }] });
    const [creatingPole, setCreatingPole] = useState(false);

    const charger = async () => {
        try {
            const r = await api.get(`/missions/${id}/matrice`);
            setMatrice(r.data.matrice);
            setPoles(r.data.matrice.reponses || []);
            setReponsesLibres(r.data.matrice.reponses_libres || '');
        } catch {
            alertError('Impossible de charger la matrice');
        }
    };

    const modifierItem = (poleIdx, itemIdx, valeur) => {
        setPoles(p => p.map((pole, i) => i !== poleIdx ? pole : ({
            ...pole,
            items: (pole.items || []).map((it, j) => j !== itemIdx ? it : { ...it, reponse: valeur }),
        })));
    };
    const modifierOrganigramme = (poleIdx, champIdx, valeur) => {
        setPoles(p => p.map((pole, i) => i !== poleIdx ? pole : ({
            ...pole,
            organigramme: (pole.organigramme || []).map((c, j) => j !== champIdx ? c : { ...c, reponse: valeur }),
        })));
    };

    const deriver = async () => {
        if (!(await confirmAction('Générer l\'organigramme à partir des réponses (services / postes par pôle) ? Vous allez être redirigé vers la page Organigramme.', 'Générer l\'organigramme'))) return;
        setDerivation(true);
        try {
            await getCsrfCookie();
            await api.put(`/missions/${id}/matrice`, { reponses: poles, reponses_libres: reponsesLibres });
            const r = await deriverOrganigramme(id);
            alertSuccess(r.message);
            navigate(r.redirect_to || `/missions/${id}/organigramme`);
        } catch (e) {
            alertError(e.response?.data?.message || 'Génération échouée');
        } finally {
            setDerivation(false);
        }
    };
    useEffect(() => { charger(); }, [id]);

    const initier = async () => {
        if (!(await confirmAction('Envoyer la matrice par email au client ?', 'Envoi'))) return;
        try {
            await getCsrfCookie();
            const r = await api.post(`/missions/${id}/matrice/initier`);
            alertSuccess(r.data.message);
            charger();
        } catch (e) { alertError(e.response?.data?.message || 'Erreur'); }
    };

    const enregistrer = async () => {
        setSaving(true);
        try {
            await getCsrfCookie();
            await api.put(`/missions/${id}/matrice`, { reponses: poles, reponses_libres: reponsesLibres });
            alertSuccess('Réponses enregistrées');
            charger();
        } catch (e) { alertError(e.response?.data?.message || 'Erreur'); } finally { setSaving(false); }
    };

    const remettre = async () => {
        if (!(await confirmAction('Remettre la matrice à AS Consulting ? Vous ne pourrez plus la modifier sans demande.', 'Remise'))) return;
        try {
            await getCsrfCookie();
            await api.put(`/missions/${id}/matrice`, { reponses: poles, reponses_libres: reponsesLibres });
            await api.post(`/missions/${id}/matrice/remettre`);
            alertSuccess('Matrice remise');
            charger();
        } catch (e) { alertError(e.response?.data?.message || 'Erreur'); }
    };

    const valider = async () => {
        if (!(await confirmAction('Valider la matrice ? La phase suivante est l\'organigramme.', 'Validation'))) return;
        try {
            await getCsrfCookie();
            await api.post(`/missions/${id}/matrice/valider`);
            alertSuccess('Matrice validée');
            navigate(`/missions/${id}/organigramme`);
        } catch (e) { alertError(e.response?.data?.message || 'Erreur'); }
    };

    // -------- Documents justificatifs par item --------

    const piecesParItem = useMemo(() => {
        const map = new Map();
        for (const p of (matrice?.pieces || [])) {
            const key = `${p.pole_code || ''}__${p.item_code || ''}`;
            const liste = map.get(key) || [];
            liste.push(p);
            map.set(key, liste);
        }
        return map;
    }, [matrice]);

    const piecesPourItem = (poleCode, itemCode) =>
        piecesParItem.get(`${poleCode || ''}__${itemCode || ''}`) || [];

    const onChangeFichierItem = (poleCode, itemCode, fichier) => {
        const key = `${poleCode}__${itemCode}`;
        setUploadItem(s => ({ ...s, [key]: { ...(s[key] || {}), fichier } }));
    };

    const uploaderItem = async (poleCode, itemCode, itemLibelle) => {
        const key = `${poleCode}__${itemCode}`;
        const state = uploadItem[key] || {};
        if (!state.fichier) { alertError('Sélectionnez un fichier.'); return; }
        setUploadItem(s => ({ ...s, [key]: { ...state, uploading: true } }));
        try {
            await getCsrfCookie();
            const fd = new FormData();
            // Le libelle reprend automatiquement le nom de l'item (= nom de la preuve attendue).
            fd.append('libelle', itemLibelle || 'Document justificatif');
            fd.append('fichier', state.fichier);
            fd.append('pole_code', poleCode);
            fd.append('item_code', itemCode);
            await api.post(`/missions/${id}/matrice/pieces`, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
            alertSuccess('Document uploadé');
            setUploadItem(s => ({ ...s, [key]: { fichier: null, uploading: false } }));
            charger();
        } catch (e) {
            alertError(e.response?.data?.message || 'Erreur upload');
            setUploadItem(s => ({ ...s, [key]: { ...state, uploading: false } }));
        }
    };

    const supprimerPiece = async (piece) => {
        if (!(await confirmAction(`Supprimer "${piece.libelle}" ?`, 'Suppression'))) return;
        try {
            await getCsrfCookie();
            await api.delete(`/matrice-pieces/${piece.id}`);
            alertSuccess('Document supprimé');
            charger();
        } catch (e) { alertError(e.response?.data?.message || 'Erreur'); }
    };

    // -------- Nouveau pole --------

    const ouvrirNouveauPole = () => {
        setNewPole({ pole: '', cibles: '', description: '', items: [{ libelle: '', attendu: '' }] });
        setShowNewPole(true);
    };
    const ajouterItemNouveauPole = () => {
        setNewPole(p => ({ ...p, items: [...p.items, { libelle: '', attendu: '' }] }));
    };
    const supprimerItemNouveauPole = (idx) => {
        setNewPole(p => ({ ...p, items: p.items.filter((_, i) => i !== idx) }));
    };
    const creerPole = async (e) => {
        e?.preventDefault?.();
        if (!newPole.pole.trim()) { alertError('Le nom du pôle est requis.'); return; }
        const itemsClean = newPole.items.filter(it => it.libelle.trim());
        setCreatingPole(true);
        try {
            await getCsrfCookie();
            await api.post(`/missions/${id}/matrice/poles`, {
                pole: newPole.pole.trim(),
                cibles: newPole.cibles.trim() || null,
                description: newPole.description.trim() || null,
                items: itemsClean,
            });
            alertSuccess('Nouveau pôle ajouté');
            setShowNewPole(false);
            charger();
        } catch (e) {
            alertError(e.response?.data?.message || 'Erreur de création');
        } finally {
            setCreatingPole(false);
        }
    };

    if (!matrice) return <div className="p-8">Chargement...</div>;
    const cfg = STATUT_LABEL[matrice.statut] || STATUT_LABEL.a_remplir;
    const lectureSeule = matrice.statut === 'validee';

    return (
        <div className="p-6 lg:p-8 max-w-5xl mx-auto">
            <Button variant="ghost" onClick={() => navigate(`/missions/${id}`)} className="mb-3">
                <ArrowLeftIcon className="w-4 h-4" /> Retour mission
            </Button>
            <PageHeader
                title="Matrice de collecte initiale"
                subtitle="Méthode 2 - Étape 1 : cartographier les processus et obtenir l'organigramme"
                eyebrow="IA dynamique · Étape 1/3"
                icon={RectangleGroupIcon}
                accent="indigo"
            >
                <div className="flex items-center gap-2">
                    <Badge variant={cfg.variant} solid size="md" dot>{cfg.label}</Badge>
                    {!lectureSeule && (
                        <Button variant="secondary" onClick={ouvrirNouveauPole}>
                            <PlusIcon className="w-4 h-4" /> Nouveau pôle
                        </Button>
                    )}
                </div>
            </PageHeader>

            {estASC && matrice.statut === 'a_remplir' && (
                <Card className="p-5 mb-4 border-l-4 border-l-blue-500">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <p className="font-semibold text-gray-900">Initialiser et envoyer au client</p>
                            <p className="text-sm text-gray-600 mt-1">Envoie un email au contact principal du client avec le lien et la PJ matrice.</p>
                        </div>
                        <Button onClick={initier}>
                            <PaperAirplaneIcon className="w-4 h-4" /> Envoyer
                        </Button>
                    </div>
                </Card>
            )}

            {/* Reponses structurees par pole */}
            <div className="space-y-4 mb-4">
                {poles.map((pole, pi) => (
                    <Card key={pi} className="p-5">
                        <div className="flex items-center gap-2 mb-2 flex-wrap">
                            <Badge variant="info">{pole.code?.toUpperCase()}</Badge>
                            <h3 className="font-bold text-gray-900">{pole.pole}</h3>
                        </div>
                        {pole.cibles && <p className="text-xs text-gray-500 italic mb-1">Cibles : {pole.cibles}</p>}
                        {pole.description && <p className="text-sm text-gray-600 mb-4">{pole.description}</p>}

                        <div className="space-y-4 mb-4">
                            {(pole.items || []).map((it, ii) => {
                                const piecesDeLitem = piecesPourItem(pole.code, it.code);
                                const key = `${pole.code}__${it.code}`;
                                const upState = uploadItem[key] || {};
                                return (
                                    <div key={ii} className="border border-gray-200 rounded-lg p-3 bg-gray-50">
                                        <p className="font-medium text-gray-900 text-sm mb-1">{it.libelle}</p>
                                        {it.attendu && <p className="text-xs text-gray-600 mb-2">{it.attendu}</p>}

                                        {/* Reponse textuelle */}
                                        <textarea
                                            value={it.reponse || ''}
                                            onChange={e => modifierItem(pi, ii, e.target.value)}
                                            rows={2}
                                            disabled={lectureSeule}
                                            placeholder="Réponse textuelle, lien interne ou 'Inexistant'..."
                                            className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white"
                                        />

                                        {/* Documents justificatifs deja attaches */}
                                        {piecesDeLitem.length > 0 && (
                                            <ul className="mt-3 space-y-1.5">
                                                {piecesDeLitem.map(p => (
                                                    <li key={p.id} className="flex items-center justify-between bg-white border border-gray-200 rounded px-3 py-2 text-sm">
                                                        <div className="flex items-center gap-2 min-w-0">
                                                            <PaperClipIcon className="w-4 h-4 text-gray-400 shrink-0" />
                                                            <div className="min-w-0">
                                                                <p className="font-medium text-gray-900 truncate">{p.libelle}</p>
                                                                <p className="text-xs text-gray-500 truncate">
                                                                    {((p.taille_octets || 0) / 1024).toFixed(1)} ko
                                                                    {p.uploadeur ? ` - par ${p.uploadeur.prenom} ${p.uploadeur.nom}` : ''}
                                                                </p>
                                                            </div>
                                                        </div>
                                                        {!lectureSeule && (
                                                            <button onClick={() => supprimerPiece(p)} className="text-red-600 hover:bg-red-50 p-1.5 rounded shrink-0">
                                                                <TrashIcon className="w-4 h-4" />
                                                            </button>
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}

                                        {/* Upload d'un nouveau document pour cet item.
                                            Le libelle est automatiquement renseigne avec le nom de l'item :
                                            inutile de demander a l'utilisateur d'en saisir un. */}
                                        {!lectureSeule && (
                                            <div className="mt-3 flex flex-wrap items-center gap-2">
                                                <label className="cursor-pointer inline-flex items-center gap-1.5 px-3 py-2 border border-dashed border-gray-300 rounded-lg text-sm bg-white hover:border-blue-400 flex-1 min-w-0">
                                                    <DocumentArrowUpIcon className="w-4 h-4 text-gray-400 shrink-0" />
                                                    <span className="text-gray-600 truncate">
                                                        {upState.fichier ? upState.fichier.name : 'Joindre un document justificatif'}
                                                    </span>
                                                    <input type="file" className="hidden" onChange={e => onChangeFichierItem(pole.code, it.code, e.target.files?.[0] || null)} />
                                                </label>
                                                <Button
                                                    type="button"
                                                    onClick={() => uploaderItem(pole.code, it.code, it.libelle)}
                                                    disabled={!upState.fichier || upState.uploading}
                                                    variant="primary"
                                                    size="sm"
                                                >
                                                    {upState.uploading ? 'Upload...' : 'Uploader'}
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>

                        {(pole.organigramme || []).length > 0 && (
                            <div className="border-t pt-3 mt-2">
                                <p className="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-2">Pour déduire l'organigramme</p>
                                <div className="grid md:grid-cols-2 gap-3">
                                    {pole.organigramme.map((c, ci) => (
                                        <div key={ci}>
                                            <label className="block text-xs font-medium text-gray-700 mb-1">{c.libelle}</label>
                                            <input
                                                type="text"
                                                value={c.reponse || ''}
                                                onChange={e => modifierOrganigramme(pi, ci, e.target.value)}
                                                disabled={lectureSeule}
                                                placeholder="Ex: Service A, Service B"
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                            />
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </Card>
                ))}
            </div>

            <Card className="p-5 mb-4">
                <h3 className="font-semibold text-gray-900 mb-3">Notes libres complémentaires</h3>
                <Textarea
                    value={reponsesLibres}
                    onChange={(e) => setReponsesLibres(e.target.value)}
                    rows={6}
                    placeholder="Notes complémentaires : sous-traitants principaux, infrastructures particulières, etc."
                />
                <div className="flex flex-wrap justify-end gap-2 mt-3">
                    <Button variant="secondary" onClick={enregistrer} disabled={saving || lectureSeule}>
                        {saving ? 'Enregistrement...' : 'Enregistrer'}
                    </Button>
                    <Button onClick={deriver} disabled={derivation || lectureSeule}>
                        <SparklesIcon className="w-4 h-4" /> {derivation ? 'Génération...' : 'Générer l\'organigramme'}
                    </Button>
                    <Button variant="primary" onClick={() => navigate(`/missions/${id}/organigramme`)}>
                        Suivant <ArrowRightIcon className="w-4 h-4" />
                    </Button>
                    {matrice.statut === 'en_cours' && !estASC && (
                        <Button onClick={remettre}><CheckIcon className="w-4 h-4" /> Remettre à AS Consulting</Button>
                    )}
                    {estASC && matrice.statut === 'remise' && (
                        <Button variant="success" onClick={valider}><CheckIcon className="w-4 h-4" /> Valider et passer à l'organigramme</Button>
                    )}
                </div>
            </Card>

            {/* Modal "Nouveau pole" */}
            <Modal
                open={showNewPole}
                onClose={() => setShowNewPole(false)}
                title="Ajouter un pôle personnalisé"
                subtitle="Le pôle sera ajouté en fin de matrice. Vous pourrez ensuite y associer des réponses et des documents."
                icon={PlusIcon}
                accent="indigo"
                size="md"
                footer={(
                    <>
                        <Button variant="secondary" type="button" onClick={() => setShowNewPole(false)}>Annuler</Button>
                        <Button variant="primary" type="submit" form="new-pole-form" loading={creatingPole}>
                            {creatingPole ? 'Ajout...' : 'Ajouter le pôle'}
                        </Button>
                    </>
                )}
            >
                <form id="new-pole-form" onSubmit={creerPole} className="space-y-3">
                    <Input
                        label="Nom du pôle"
                        required
                        value={newPole.pole}
                        onChange={e => setNewPole(p => ({ ...p, pole: e.target.value }))}
                        placeholder="Ex: Pôle Innovation"
                    />
                    <Input
                        label="Cibles"
                        value={newPole.cibles}
                        onChange={e => setNewPole(p => ({ ...p, cibles: e.target.value }))}
                        placeholder="Ex: Direction Innovation / R&D"
                    />
                    <Textarea
                        label="Description"
                        rows={2}
                        value={newPole.description}
                        onChange={e => setNewPole(p => ({ ...p, description: e.target.value }))}
                        placeholder="Court résumé des preuves attendues..."
                    />

                    <div>
                        <div className="flex items-center justify-between mb-1.5">
                            <label className="text-sm font-semibold text-gray-700">Items / preuves attendues</label>
                            <button type="button" onClick={ajouterItemNouveauPole} className="text-xs text-blue-600 hover:underline">
                                + Ajouter un item
                            </button>
                        </div>
                        <div className="space-y-2">
                            {newPole.items.map((it, idx) => (
                                <div key={idx} className="grid grid-cols-[1fr_1fr_auto] gap-2">
                                    <input
                                        type="text"
                                        value={it.libelle}
                                        onChange={e => setNewPole(p => ({ ...p, items: p.items.map((x, i) => i === idx ? { ...x, libelle: e.target.value } : x) }))}
                                        placeholder="Libellé de l'item"
                                        className="px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                    />
                                    <input
                                        type="text"
                                        value={it.attendu}
                                        onChange={e => setNewPole(p => ({ ...p, items: p.items.map((x, i) => i === idx ? { ...x, attendu: e.target.value } : x) }))}
                                        placeholder="Preuve attendue (optionnel)"
                                        className="px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => supprimerItemNouveauPole(idx)}
                                        disabled={newPole.items.length === 1}
                                        className="text-red-600 hover:bg-red-50 p-2 rounded disabled:opacity-30"
                                    >
                                        <TrashIcon className="w-4 h-4" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
