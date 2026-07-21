/**
 * MesMatrices — Espace client : liste des matrices de collecte initiale
 * (Methode 2). Le client peut ouvrir chaque matrice pour la renseigner,
 * uploader les pieces de conviction, deriver l'organigramme et finaliser.
 */

import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { listMesMatrices } from '@/api/matrices';
import { alertError } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import {
    ClipboardDocumentListIcon, PencilSquareIcon, SparklesIcon, RectangleGroupIcon,
} from '@heroicons/react/24/outline';

const STATUT = {
    a_remplir: { label: 'À remplir', variant: 'gray' },
    en_cours: { label: 'En cours', variant: 'info' },
    remise: { label: 'Remise', variant: 'warning' },
    validee: { label: 'Validée', variant: 'success' },
};

export default function MesMatrices() {
    const navigate = useNavigate();
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        listMesMatrices()
            .then(r => setItems(r.data || []))
            .catch(err => alertError(err.response?.data?.message || 'Chargement impossible'))
            .finally(() => setLoading(false));
    }, []);

    return (
        <div className="p-6 lg:p-8 max-w-5xl mx-auto">
            <PageHeader
                title="Cartographie initiale"
                subtitle="Renseignez la matrice de collecte de chaque mission. Vos réponses servent ensuite à générer automatiquement l'organigramme et les questionnaires d'audit."
                eyebrow="Espace client"
                icon={RectangleGroupIcon}
                accent="indigo"
            />

            {loading && <p className="text-sm text-gray-500">Chargement...</p>}

            {!loading && items.length === 0 && (
                <Card className="p-10 text-center">
                    <SparklesIcon className="w-12 h-12 text-purple-300 mx-auto mb-3" />
                    <p className="text-gray-700">
                        Aucune matrice pour le moment. Une matrice est créée automatiquement
                        lorsque AS Consulting démarre une mission de type "Méthode 2 - IA dynamique".
                    </p>
                </Card>
            )}

            <div className="space-y-3">
                {items.map(({ matrice, mission }) => {
                    const cfg = STATUT[matrice.statut] || STATUT.a_remplir;
                    const totalReponses = (matrice.reponses || []).reduce((acc, p) => {
                        return acc + (p.items || []).filter(it => it.reponse?.trim()).length;
                    }, 0);
                    const totalAttendues = (matrice.reponses || []).reduce((acc, p) => acc + (p.items || []).length, 0);
                    const pct = totalAttendues > 0 ? Math.round((totalReponses / totalAttendues) * 100) : 0;

                    return (
                        <Card key={matrice.id} className="p-4 hover:shadow transition-shadow">
                            <div className="flex items-start justify-between gap-4 flex-wrap">
                                <div className="flex-1 min-w-[220px]">
                                    <div className="flex items-center gap-2 mb-1 flex-wrap">
                                        <ClipboardDocumentListIcon className="w-4 h-4 text-blue-600" />
                                        <p className="font-semibold text-gray-900">{mission.reference} — {mission.titre}</p>
                                        <Badge variant={cfg.variant}>{cfg.label}</Badge>
                                    </div>
                                    {mission.client?.raison_sociale && (
                                        <p className="text-xs text-gray-500 mt-0.5">Client : {mission.client.raison_sociale}</p>
                                    )}
                                    <div className="flex items-center gap-3 text-xs text-gray-500 mt-2 flex-wrap">
                                        <span>{(matrice.reponses || []).length} pôle(s)</span>
                                        <span>·</span>
                                        <span>{totalReponses}/{totalAttendues} réponse(s)</span>
                                        <span>·</span>
                                        <span className={pct === 100 ? 'text-emerald-700 font-semibold' : 'text-blue-700 font-semibold'}>
                                            {pct} %
                                        </span>
                                        <span>·</span>
                                        <span>{(matrice.pieces || []).length} pièce(s)</span>
                                    </div>
                                </div>
                                <Button onClick={() => navigate(`/mes-matrices/${mission.id}`)}>
                                    <PencilSquareIcon className="w-4 h-4" /> Ouvrir
                                </Button>
                            </div>
                        </Card>
                    );
                })}
            </div>
        </div>
    );
}
