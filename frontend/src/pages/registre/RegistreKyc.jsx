/**
 * Page RegistreKyc — Generation a la demande et historique des registres
 * des traitements (Article 30 — Loi 2013-450).
 */

import { useState, useEffect } from 'react';
import { useAuth } from '@/contexts/AuthContext';
import {
    listRegistres, genererRegistre, telechargerRegistre, supprimerRegistre,
} from '@/api/registreKyc';
import api from '@/api/client';
import { alertSuccess, alertError, confirmAction, confirmDelete } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import { Select } from '@/components/ui/Input';
import {
    DocumentTextIcon, ArrowDownTrayIcon, TrashIcon, ClockIcon, CheckCircleIcon,
    ExclamationTriangleIcon, DocumentDuplicateIcon,
} from '@heroicons/react/24/outline';

const statutVariant = { en_cours: 'info', termine: 'success', erreur: 'danger' };
const statutLabel = { en_cours: 'Génération...', termine: 'Disponible', erreur: 'Erreur' };

export default function RegistreKyc() {
    const { hasPermission } = useAuth();
    // "Interne" = utilisateur cote ASC (admin/manager/consultant), detecte par view-portefeuille.
    const estInterne = hasPermission('view-portefeuille');
    const peutSupprimer = hasPermission('delete-registres-kyc');

    const [registres, setRegistres] = useState([]);
    const [loading, setLoading] = useState(true);
    const [generating, setGenerating] = useState(false);

    // Pour les roles internes : choix du client
    const [clients, setClients] = useState([]);
    const [clientId, setClientId] = useState('');

    const charger = async () => {
        setLoading(true);
        try {
            const r = await listRegistres({ per_page: 50 });
            setRegistres(r.data || []);
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur de chargement');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        charger();
        if (estInterne) {
            api.get('/clients?per_page=200').then(r => setClients(r.data.data || [])).catch(() => {});
        }
    }, []);

    const handleGenerer = async () => {
        if (estInterne && !clientId) {
            alertError('Sélectionnez une entreprise avant de générer.');
            return;
        }
        if (!(await confirmAction('Générer un nouveau registre à partir des traitements validés actuels ?', 'Génération'))) return;

        setGenerating(true);
        try {
            await genererRegistre(estInterne ? parseInt(clientId) : null);
            alertSuccess('Registre généré');
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur lors de la génération');
        } finally {
            setGenerating(false);
        }
    };

    const handleTelecharger = async (r) => {
        try {
            await telechargerRegistre(r);
        } catch {
            alertError('Impossible de télécharger');
        }
    };

    const handleDelete = async (r) => {
        if (!(await confirmDelete(r.reference))) return;
        try {
            await supprimerRegistre(r.id);
            alertSuccess('Registre supprimé');
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur');
        }
    };

    return (
        <div className="p-6 lg:p-8 max-w-5xl mx-auto">
            <PageHeader
                title="Registre des traitements (KYC)"
                subtitle="Génération dynamique à partir des traitements validés (Article 30 — Loi 2013-450)"
                eyebrow="Conformité ARTCI"
                icon={DocumentDuplicateIcon}
                accent="cyan"
            >
                <div className="flex items-center gap-2">
                    {estInterne && (
                        <Select value={clientId} onChange={e => setClientId(e.target.value)} className="w-64">
                            <option value="">-- Choisir l'entreprise --</option>
                            {clients.map(c => (
                                <option key={c.id} value={c.id}>{c.raison_sociale}</option>
                            ))}
                        </Select>
                    )}
                    <Button onClick={handleGenerer} disabled={generating}>
                        {generating ? (
                            <>
                                <div className="w-4 h-4 rounded-full border-2 border-white border-t-transparent animate-spin" />
                                Génération...
                            </>
                        ) : (
                            <><DocumentDuplicateIcon className="w-4 h-4" /> Générer un nouveau registre</>
                        )}
                    </Button>
                </div>
            </PageHeader>

            {/* Info card */}
            <Card className="p-5 mb-6 bg-blue-50 border-blue-200">
                <div className="flex items-start gap-3">
                    <DocumentTextIcon className="w-6 h-6 text-blue-600 shrink-0 mt-0.5" />
                    <div className="text-sm text-blue-900">
                        <p className="font-semibold mb-1">À propos du registre</p>
                        <p>Le registre est généré à partir des traitements au statut <strong>Validé</strong>. Chaque génération fige les versions des fiches incluses et calcule une empreinte SHA-256 pour prouver l'intégrité du document. Vous pouvez régénérer à tout moment après avoir validé de nouvelles fiches.</p>
                    </div>
                </div>
            </Card>

            {/* Liste des registres */}
            {loading ? (
                <div className="p-8 flex justify-center">
                    <div className="w-10 h-10 rounded-full border-4 border-blue-600 border-t-transparent animate-spin" />
                </div>
            ) : registres.length === 0 ? (
                <Card className="p-12 text-center text-gray-500">
                    <DocumentTextIcon className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                    <p className="font-medium">Aucun registre généré pour le moment</p>
                    <p className="text-sm text-gray-400 mt-1">Cliquez sur « Générer un nouveau registre » pour commencer.</p>
                </Card>
            ) : (
                <div className="space-y-3">
                    {registres.map(r => (
                        <Card key={r.id} className="p-5">
                            <div className="flex items-start gap-4">
                                <div className={`w-12 h-12 rounded-xl flex items-center justify-center shrink-0 ${r.statut_generation === 'termine' ? 'bg-emerald-50 text-emerald-600' : r.statut_generation === 'erreur' ? 'bg-red-50 text-red-600' : 'bg-blue-50 text-blue-600'}`}>
                                    {r.statut_generation === 'termine' ? <CheckCircleIcon className="w-6 h-6" /> : r.statut_generation === 'erreur' ? <ExclamationTriangleIcon className="w-6 h-6" /> : <ClockIcon className="w-6 h-6" />}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 flex-wrap mb-1">
                                        <span className="font-mono text-sm font-semibold text-blue-700">{r.reference}</span>
                                        <Badge variant={statutVariant[r.statut_generation]}>{statutLabel[r.statut_generation]}</Badge>
                                        <Badge variant="gray">{r.format.toUpperCase()}</Badge>
                                    </div>
                                    <p className="text-sm font-medium text-gray-900">
                                        {r.nb_traitements} traitement(s) inclus
                                        {estInterne && r.client && (
                                            <span className="text-gray-500 font-normal"> · {r.client.raison_sociale}</span>
                                        )}
                                    </p>
                                    <p className="text-xs text-gray-500 mt-1">
                                        Généré le {new Date(r.created_at).toLocaleString('fr-FR')}
                                        {r.genereur && <> par {r.genereur.prenom} {r.genereur.nom}</>}
                                    </p>
                                    {r.hash_fichier && (
                                        <p className="text-[10px] text-gray-400 font-mono mt-1 break-all">
                                            SHA-256 : {r.hash_fichier}
                                        </p>
                                    )}
                                    {r.erreur_message && (
                                        <p className="text-xs text-red-600 mt-1">{r.erreur_message}</p>
                                    )}
                                </div>
                                <div className="flex flex-col gap-2 shrink-0">
                                    {r.statut_generation === 'termine' && (
                                        <Button size="sm" onClick={() => handleTelecharger(r)}>
                                            <ArrowDownTrayIcon className="w-4 h-4" /> Télécharger
                                        </Button>
                                    )}
                                    {peutSupprimer && (
                                        <Button size="sm" variant="secondary" onClick={() => handleDelete(r)}>
                                            <TrashIcon className="w-4 h-4" />
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>
            )}
        </div>
    );
}
