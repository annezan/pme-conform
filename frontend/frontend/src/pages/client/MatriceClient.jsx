/**
 * MatriceClient — Espace client : edition de la matrice de collecte initiale.
 *
 * UX alignee sur MatriceCollecte (vue ASC) :
 *   - Upload des pieces justificatives PAR ITEM (un bouton "Joindre" + "Uploader"
 *     pour chaque preuve attendue, le libelle est auto-rempli avec le nom de l'item).
 *   - Bouton "Nouveau pole" pour ajouter un pole personnalise a la matrice.
 *   - Notes libres complementaires, deriver organigramme, remettre a AS Consulting.
 *
 * Differences avec la vue ASC :
 *   - Pas de bouton "Initier" / "Valider" (reserves a ASC).
 *   - Le retour mene vers /mes-matrices et non /missions/:id.
 */

import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
    getMatrice, updateMatrice, remettreMatrice, deriverOrganigramme,
    uploaderPiece, supprimerPiece, ajouterPole,
} from '@/api/matrices';
import { alertSuccess, alertError, confirmAction } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import Modal from '@/components/ui/Modal';
import { Input, Textarea } from '@/components/ui/Input';
import {
    ArrowLeftIcon, ArrowRightIcon, TrashIcon, CheckIcon,
    SparklesIcon, RectangleGroupIcon, PlusIcon,
    DocumentArrowUpIcon, PaperClipIcon,
} from '@heroicons/react/24/outline';

const STATUT = {
    a_remplir: { label: 'À remplir', variant: 'gray' },
    en_cours: { label: 'En cours', variant: 'info' },
    remise: { label: 'Remise', variant: 'warning' },
    validee: { label: 'Validée', variant: 'success' },
};

export default function MatriceClient() {
    const { missionId } = useParams();
    const navigate = useNavigate();
    const [matrice, setMatrice] = useState(null);
    const [poles, setPoles] = useState([]);
    const [reponsesLibres, setReponsesLibres] = useState('');
    const [saving, setSaving] = useState(false);
    const [derivation, setDerivation] = useState(false);

    // Etat upload par item : { [poleCode_itemCode]: { fichier, uploading } }
    const [uploadItem, setUploadItem] = useState({});

    // Modal "Nouveau pole"
    const [showNewPole, setShowNewPole] = useState(false);
    const [newPole, setNewPole] = useState({ pole: '', cibles: '', description: '', items: [{ libelle: '', attendu: '' }] });
    const [creatingPole, setCreatingPole] = useState(false);

    const charger = async () => {
        try {
            const r = await getMatrice(missionId);
            setMatrice(r.matrice);
            setPoles(r.matrice.reponses || []);
            setReponsesLibres(r.matrice.reponses_libres || '');
        } catch (err) {
            alertError(err.response?.data?.message || 'Impossible de charger la matrice');
        }
    };
    useEffect(() => { charger(); }, [missionId]);

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

    const enregistrer = async () => {
        setSaving(true);
        try {
            await updateMatrice(missionId, { reponses: poles, reponses_libres: reponsesLibres });
            alertSuccess('Réponses enregistrées');
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur enregistrement');
        } finally {
            setSaving(false);
        }
    };

    const remettre = async () => {
        if (!(await confirmAction('Remettre la matrice à AS Consulting ? Vous pourrez encore la consulter.', 'Remise'))) return;
        try {
            await updateMatrice(missionId, { reponses: poles, reponses_libres: reponsesLibres });
            await remettreMatrice(missionId);
            alertSuccess('Matrice remise');
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur');
        }
    };

    const deriver = async () => {
        if (!(await confirmAction("Générer l'organigramme à partir des réponses (services / postes par pôle) ?", "Générer l'organigramme"))) return;
        setDerivation(true);
        try {
            await updateMatrice(missionId, { reponses: poles, reponses_libres: reponsesLibres });
            const r = await deriverOrganigramme(missionId);
            alertSuccess(r.message);
            navigate(r.redirect_to || `/missions/${missionId}/organigramme`);
        } catch (err) {
            alertError(err.response?.data?.message || 'Dérivation échouée');
        } finally {
            setDerivation(false);
        }
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
            const fd = new FormData();
            // Le libelle reprend le nom de l'item (= preuve attendue).
            fd.append('libelle', itemLibelle || 'Document justificatif');
            fd.append('fichier', state.fichier);
            fd.append('pole_code', poleCode);
            fd.append('item_code', itemCode);
            await uploaderPiece(missionId, fd);
            alertSuccess('Document uploadé');
            setUploadItem(s => ({ ...s, [key]: { fichier: null, uploading: false } }));
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur upload');
            setUploadItem(s => ({ ...s, [key]: { ...state, uploading: false } }));
        }
    };

    const supprimer = async (p) => {
        if (!(await confirmAction(`Supprimer "${p.libelle}" ?`, 'Suppression'))) return;
        try {
            await supprimerPiece(p.id);
            alertSuccess('Document supprimé');
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur suppression');
        }
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
            await ajouterPole(missionId, {
                pole: newPole.pole.trim(),
                cibles: newPole.cibles.trim() || null,
                description: newPole.description.trim() || null,
                items: itemsClean,
            });
            alertSuccess('Nouveau pôle ajouté');
            setShowNewPole(false);
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur de création');
        } finally {
            setCreatingPole(false);
        }
    };

    if (!matrice) return <div className="p-8">Chargement...</div>;
    const cfg = STATUT[matrice.statut] || STATUT.a_remplir;
    const lectureSeule = matrice.statut === 'validee';

    return (
        <div className="p-6 lg:p-8 max-w-5xl mx-auto">
            <Button variant="ghost" onClick={() => navigate('/mes-matrices')} className="mb-3">
                <ArrowLeftIcon className="w-4 h-4" /> Retour aux matrices
            </Button>
            <PageHeader
                title="Matrice de collecte initiale"
                subtitle="Renseignez par pôle les éléments demandés. Vos réponses (services / postes clés) servent à déduire l'organigramme du client puis à générer les questionnaires d'audit."
                eyebrow="Espace client"
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

            <Card className="p-4 mb-4 bg-blue-50 border-blue-200">
                <p className="text-sm text-gray-700">
                    <strong>Directive Absolue : zéro création, uniquement de l'extraction.</strong> Joignez à chaque ligne le
                    document existant (bouton « Joindre un document justificatif ») puis cliquez sur « Uploader ». Indiquez
                    « Inexistant » dans la réponse textuelle si un document n'existe pas chez vous — ce point fera l'objet
                    d'une remédiation lors des ateliers.
                </p>
            </Card>

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
                                                            <button onClick={() => supprimer(p)} className="text-red-600 hover:bg-red-50 p-1.5 rounded shrink-0">
                                                                <TrashIcon className="w-4 h-4" />
                                                            </button>
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}

                                        {/* Upload d'un nouveau document pour cet item.
                                            Le libelle est automatiquement renseigne avec le nom de l'item. */}
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
            </Card>

            <div className="sticky bottom-4 mt-6 bg-white border border-gray-200 rounded-xl shadow-md p-4 flex flex-wrap items-center justify-between gap-3 z-10">
                <p className="text-sm text-gray-600">
                    <strong>{poles.length}</strong> pôle(s) — Statut : {cfg.label}
                </p>
                <div className="flex flex-wrap gap-2">
                    <Button variant="secondary" onClick={enregistrer} disabled={saving || lectureSeule}>
                        {saving ? 'Enregistrement...' : 'Enregistrer'}
                    </Button>
                    <Button onClick={deriver} disabled={derivation || lectureSeule}>
                        <SparklesIcon className="w-4 h-4" /> {derivation ? 'Génération...' : "Générer l'organigramme"}
                    </Button>
                    {!lectureSeule && matrice.statut !== 'remise' && (
                        <Button variant="success" onClick={remettre}>
                            <CheckIcon className="w-4 h-4" /> Remettre à AS Consulting
                        </Button>
                    )}
                </div>
            </div>

            {/* Modal Nouveau pole */}
            <Modal
                open={showNewPole}
                onClose={() => setShowNewPole(false)}
                title="Nouveau pôle personnalisé"
                icon={PlusIcon}
                size="lg"
            >
                <form onSubmit={creerPole} className="space-y-3">
                    <Input
                        label="Nom du pôle"
                        required
                        value={newPole.pole}
                        onChange={e => setNewPole(p => ({ ...p, pole: e.target.value }))}
                        placeholder="Ex: Pôle Communication"
                    />
                    <Input
                        label="Cibles"
                        value={newPole.cibles}
                        onChange={e => setNewPole(p => ({ ...p, cibles: e.target.value }))}
                        placeholder="Ex: Direction Communication"
                    />
                    <Textarea
                        label="Description"
                        rows={2}
                        value={newPole.description}
                        onChange={e => setNewPole(p => ({ ...p, description: e.target.value }))}
                        placeholder="A quoi sert ce pôle dans la matrice ?"
                    />

                    <div>
                        <div className="flex items-center justify-between mb-2">
                            <label className="block text-sm font-semibold text-gray-700">Preuves attendues</label>
                            <Button type="button" size="xs" variant="secondary" onClick={ajouterItemNouveauPole}>
                                <PlusIcon className="w-3.5 h-3.5" /> Ajouter une ligne
                            </Button>
                        </div>
                        <div className="space-y-2">
                            {newPole.items.map((it, idx) => (
                                <div key={idx} className="grid grid-cols-12 gap-2">
                                    <Input
                                        className="col-span-5"
                                        placeholder="Libellé (ex: Charte de communication)"
                                        value={it.libelle}
                                        onChange={e => setNewPole(p => ({
                                            ...p,
                                            items: p.items.map((x, i) => i === idx ? { ...x, libelle: e.target.value } : x),
                                        }))}
                                    />
                                    <Input
                                        className="col-span-6"
                                        placeholder="Attendu (ex: Modèle vierge officiel)"
                                        value={it.attendu}
                                        onChange={e => setNewPole(p => ({
                                            ...p,
                                            items: p.items.map((x, i) => i === idx ? { ...x, attendu: e.target.value } : x),
                                        }))}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => supprimerItemNouveauPole(idx)}
                                        disabled={newPole.items.length === 1}
                                        className="col-span-1 text-red-600 hover:bg-red-50 rounded disabled:opacity-30"
                                    >
                                        <TrashIcon className="w-4 h-4 mx-auto" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <Button type="button" variant="ghost" onClick={() => setShowNewPole(false)} disabled={creatingPole}>Annuler</Button>
                        <Button type="submit" loading={creatingPole}>
                            <PlusIcon className="w-4 h-4" /> Créer le pôle
                        </Button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
