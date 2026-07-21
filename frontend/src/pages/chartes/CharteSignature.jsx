/**
 * Page CharteSignature — Lecture + signature d'une charte.
 *
 * Le hash_contenu est envoye au backend pour garantir que ce que l'utilisateur
 * a lu correspond bien a ce qui est en base (anti-tampering).
 */

import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { getCharte, signerCharte } from '@/api/chartes';
import { alertSuccess, alertError } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import {
    ArrowLeftIcon, ShieldCheckIcon, CheckCircleIcon, ExclamationTriangleIcon,
} from '@heroicons/react/24/outline';

export default function CharteSignature() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [accepte, setAccepte] = useState(false);
    const [signing, setSigning] = useState(false);

    useEffect(() => {
        getCharte(id)
            .then(r => {
                setData(r);
                setAccepte(false); // reset a chaque chargement
            })
            .catch(() => alertError('Impossible de charger la charte'))
            .finally(() => setLoading(false));
    }, [id]);

    const signer = async () => {
        if (!accepte || !data?.charte) return;
        setSigning(true);
        try {
            await signerCharte(data.charte.id, data.charte.hash_contenu);
            alertSuccess('Charte signée avec succès.');
            navigate('/chartes');
        } catch (err) {
            const status = err.response?.status;
            if (status === 409) {
                alertError('Le contenu a changé entre-temps. La page va être rechargée.');
                window.location.reload();
            } else {
                alertError(err.response?.data?.message || 'Erreur lors de la signature');
            }
        } finally {
            setSigning(false);
        }
    };

    if (loading) {
        return <div className="p-8 flex justify-center"><div className="w-10 h-10 rounded-full border-4 border-blue-600 border-t-transparent animate-spin" /></div>;
    }

    if (!data) return <div className="p-8 text-center text-gray-500">Charte introuvable.</div>;

    const { charte, signature_existante } = data;
    const dejaSigneeValide = signature_existante?.signature_valide;
    const dejaSigneeObsolete = signature_existante && !signature_existante.signature_valide;

    return (
        <div className="p-6 lg:p-8 max-w-4xl mx-auto">
            <button onClick={() => navigate('/chartes')} className="inline-flex items-center gap-1.5 text-sm font-medium text-blue-700 hover:text-blue-900 mb-4 transition-colors">
                <ArrowLeftIcon className="w-4 h-4" /> Retour aux chartes
            </button>

            <PageHeader
                title={charte.titre}
                subtitle={`${charte.type.replace(/_/g, ' ')} · Version ${charte.version}`}
                eyebrow="Engagement opposable"
                icon={ShieldCheckIcon}
                accent="emerald"
            />

            {dejaSigneeValide && (
                <Card className="p-4 mb-6 bg-emerald-50 border-emerald-200 flex items-start gap-3">
                    <ShieldCheckIcon className="w-6 h-6 text-emerald-600 shrink-0" />
                    <div>
                        <p className="font-semibold text-emerald-900">Vous avez déjà signé cette charte</p>
                        <p className="text-sm text-emerald-700 mt-1">
                            Signée le {new Date(signature_existante.signee_le).toLocaleString('fr-FR')} depuis l'IP <span className="font-mono">{signature_existante.ip_signature}</span>.
                            Le contenu n'a pas été modifié depuis.
                        </p>
                    </div>
                </Card>
            )}

            {dejaSigneeObsolete && (
                <Card className="p-4 mb-6 bg-amber-50 border-amber-200 flex items-start gap-3">
                    <ExclamationTriangleIcon className="w-6 h-6 text-amber-600 shrink-0" />
                    <div>
                        <p className="font-semibold text-amber-900">Le contenu a été modifié depuis votre dernière signature</p>
                        <p className="text-sm text-amber-700 mt-1">Relisez la nouvelle version et re-signez pour rester engagé.</p>
                    </div>
                </Card>
            )}

            {/* Contenu de la charte */}
            <Card className="p-8 mb-6">
                <div
                    className="prose prose-sm max-w-none text-gray-800"
                    dangerouslySetInnerHTML={{ __html: charte.contenu_html }}
                />
            </Card>

            {/* Metadata hash */}
            <Card className="p-4 mb-6 bg-gray-50">
                <div className="text-xs text-gray-500 space-y-1">
                    <div>
                        <span className="font-semibold">Empreinte SHA-256 (version signée) :</span>{' '}
                        <span className="font-mono break-all">{charte.hash_contenu}</span>
                    </div>
                    <div>Cette empreinte garantit que le contenu affiché sera bien celui attaché à votre signature.</div>
                </div>
            </Card>

            {/* Zone de signature */}
            <Card className="p-6">
                <h3 className="font-semibold text-gray-900 mb-4">Signature</h3>
                <label className="flex items-start gap-3 cursor-pointer mb-4">
                    <input
                        type="checkbox"
                        checked={accepte}
                        onChange={e => setAccepte(e.target.checked)}
                        className="w-5 h-5 rounded mt-0.5"
                    />
                    <span className="text-sm text-gray-800">
                        J'ai lu et je comprends l'intégralité des clauses de la présente charte. Je m'engage au nom de mon entreprise à respecter ses dispositions. Je reconnais que la signature électronique est équivalente à une signature manuscrite, et qu'elle est tracée (date, heure, IP) à des fins de preuve.
                    </span>
                </label>

                <div className="flex justify-end gap-2 pt-4 border-t border-gray-100">
                    <Button variant="secondary" onClick={() => navigate('/chartes')} disabled={signing}>Annuler</Button>
                    <Button onClick={signer} disabled={!accepte || signing}>
                        {signing ? (
                            <>
                                <div className="w-4 h-4 rounded-full border-2 border-white border-t-transparent animate-spin" />
                                Signature...
                            </>
                        ) : (
                            <><CheckCircleIcon className="w-4 h-4" /> {dejaSigneeObsolete ? 'Re-signer' : 'Signer la charte'}</>
                        )}
                    </Button>
                </div>
            </Card>
        </div>
    );
}
