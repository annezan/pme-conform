/**
 * Page ChartesList — Liste des chartes disponibles et statut de signature.
 */

import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { listChartes, listMesSignatures, revoquerSignature } from '@/api/chartes';
import { alertSuccess, alertError, confirmAction } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import {
    ShieldCheckIcon, ClockIcon, ExclamationTriangleIcon,
    ArrowPathIcon, PencilSquareIcon, XCircleIcon,
} from '@heroicons/react/24/outline';

const typeLabel = {
    charte_ia: 'Charte IA',
    charte_sous_traitance: 'Sous-traitance (DPA)',
    cgu: 'CGU',
    accord_confidentialite: 'Accord de confidentialité',
    autre: 'Autre',
};

export default function ChartesList() {
    const navigate = useNavigate();
    const [chartes, setChartes] = useState([]);
    const [signatures, setSignatures] = useState([]);
    const [loading, setLoading] = useState(true);
    const [activeTab, setActiveTab] = useState('chartes'); // 'chartes' | 'signatures'

    const charger = async () => {
        setLoading(true);
        try {
            const [rC, rS] = await Promise.all([listChartes(), listMesSignatures()]);
            setChartes(rC.data || []);
            setSignatures(rS.data || []);
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur de chargement');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { charger(); }, []);

    const handleRevoquer = async (s) => {
        if (!(await confirmAction('Révoquer cette signature ? Vous pourrez re-signer à tout moment.', 'Révocation'))) return;
        try {
            await revoquerSignature(s.id);
            alertSuccess('Signature révoquée');
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur');
        }
    };

    return (
        <div className="p-6 lg:p-8 max-w-5xl mx-auto">
            <PageHeader
                title="Chartes d'engagement"
                subtitle="Signez les chartes obligatoires et consultez votre historique"
                eyebrow="Engagements opposables"
                icon={ShieldCheckIcon}
                accent="emerald"
            />

            {/* Tabs */}
            <div className="flex gap-1 mb-6 border-b border-gray-200">
                <button
                    onClick={() => setActiveTab('chartes')}
                    className={`px-4 py-2 text-sm font-medium border-b-2 transition-all ${activeTab === 'chartes' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
                >
                    Chartes disponibles ({chartes.length})
                </button>
                <button
                    onClick={() => setActiveTab('signatures')}
                    className={`px-4 py-2 text-sm font-medium border-b-2 transition-all ${activeTab === 'signatures' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
                >
                    Mes signatures ({signatures.length})
                </button>
            </div>

            {loading && (
                <div className="p-8 flex justify-center">
                    <div className="w-10 h-10 rounded-full border-4 border-blue-600 border-t-transparent animate-spin" />
                </div>
            )}

            {!loading && activeTab === 'chartes' && (
                <div className="space-y-3">
                    {chartes.length === 0 ? (
                        <Card className="p-10 text-center text-gray-500">Aucune charte active pour le moment.</Card>
                    ) : chartes.map(c => (
                        <Card key={c.id} hover className="p-5">
                            <div className="flex items-start gap-4">
                                <div className={`w-12 h-12 rounded-xl flex items-center justify-center shrink-0 ${c.signee && c.signature_valide ? 'bg-emerald-50 text-emerald-600' : c.obligatoire ? 'bg-amber-50 text-amber-600' : 'bg-gray-50 text-gray-500'}`}>
                                    <ShieldCheckIcon className="w-6 h-6" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 flex-wrap mb-1">
                                        <h3 className="font-semibold text-gray-900">{c.titre}</h3>
                                        <Badge variant="info">{typeLabel[c.type] || c.type}</Badge>
                                        <Badge variant="gray">v{c.version}</Badge>
                                        {c.obligatoire && <Badge variant="warning">Obligatoire</Badge>}
                                    </div>
                                    <p className="text-xs text-gray-500">
                                        Publiée le {new Date(c.publiee_le).toLocaleDateString('fr-FR')}
                                    </p>

                                    <div className="mt-3">
                                        {!c.client_rattache ? (
                                            <div className="flex items-center gap-2 text-sm text-amber-700">
                                                <ExclamationTriangleIcon className="w-4 h-4" />
                                                Rattachez-vous à une entreprise pour pouvoir signer.
                                            </div>
                                        ) : c.signee && c.signature_valide ? (
                                            <div className="flex items-center gap-2 text-sm text-emerald-700">
                                                <ShieldCheckIcon className="w-4 h-4" />
                                                Signée le {new Date(c.signee_le).toLocaleDateString('fr-FR')}
                                            </div>
                                        ) : c.signee && !c.signature_valide ? (
                                            <div className="flex items-center gap-2 text-sm text-amber-700">
                                                <ExclamationTriangleIcon className="w-4 h-4" />
                                                Contenu modifié depuis votre signature — veuillez re-signer.
                                            </div>
                                        ) : (
                                            <div className="flex items-center gap-2 text-sm text-gray-600">
                                                <ClockIcon className="w-4 h-4" />
                                                Non signée
                                            </div>
                                        )}
                                    </div>
                                </div>
                                <div className="flex flex-col gap-2 shrink-0">
                                    <Button onClick={() => navigate(`/chartes/${c.id}`)} size="sm">
                                        {c.signee && c.signature_valide ? (
                                            <>Consulter</>
                                        ) : c.signee ? (
                                            <><PencilSquareIcon className="w-4 h-4" /> Re-signer</>
                                        ) : (
                                            <><PencilSquareIcon className="w-4 h-4" /> Signer</>
                                        )}
                                    </Button>
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>
            )}

            {!loading && activeTab === 'signatures' && (
                <div className="space-y-2">
                    {signatures.length === 0 ? (
                        <Card className="p-10 text-center text-gray-500">Aucune signature enregistrée.</Card>
                    ) : signatures.map(s => (
                        <Card key={s.id} className="p-4">
                            <div className="flex items-start gap-3">
                                <div className={`w-10 h-10 rounded-lg flex items-center justify-center shrink-0 ${s.statut === 'signee' && s.signature_valide ? 'bg-emerald-50 text-emerald-600' : 'bg-gray-100 text-gray-500'}`}>
                                    {s.statut === 'signee' && s.signature_valide ? <ShieldCheckIcon className="w-5 h-5" /> : <XCircleIcon className="w-5 h-5" />}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <p className="font-medium text-gray-900 text-sm">{s.charte?.titre}</p>
                                        <Badge variant="info">v{s.charte?.version}</Badge>
                                        {s.statut === 'signee' && s.signature_valide
                                            ? <Badge variant="success">Signature valide</Badge>
                                            : s.statut === 'signee'
                                                ? <Badge variant="warning">Contenu modifié</Badge>
                                                : <Badge variant="gray">Révoquée</Badge>}
                                    </div>
                                    <div className="text-xs text-gray-500 mt-1 space-x-3">
                                        <span>Signée : {new Date(s.signee_le).toLocaleString('fr-FR')}</span>
                                        <span>IP : <span className="font-mono">{s.ip_signature}</span></span>
                                        {s.revoquee_le && <span>Révoquée : {new Date(s.revoquee_le).toLocaleString('fr-FR')}</span>}
                                    </div>
                                    {s.raison_revocation && (
                                        <p className="text-xs text-gray-600 italic mt-1">{s.raison_revocation}</p>
                                    )}
                                </div>
                                {s.statut === 'signee' && (
                                    <Button variant="secondary" size="sm" onClick={() => handleRevoquer(s)}>
                                        <ArrowPathIcon className="w-4 h-4" /> Révoquer
                                    </Button>
                                )}
                            </div>
                        </Card>
                    ))}
                </div>
            )}
        </div>
    );
}
