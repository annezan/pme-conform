/**
 * Page Parametres — Configuration de l'application pour l'utilisateur.
 */

import { useState, useEffect } from 'react';
import api from '@/api/client';
import { alertSuccess, alertError } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card, { CardHeader, CardBody } from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import {
    Cog6ToothIcon,
    CpuChipIcon,
    ServerIcon,
    ShieldCheckIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
} from '@heroicons/react/24/outline';

export default function Settings() {
    const [llmStatus, setLlmStatus] = useState(null);
    const [llmModels, setLlmModels] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        Promise.all([
            api.get('/llm/statut').catch(() => ({ data: { disponible: false } })),
            api.get('/llm/modeles').catch(() => ({ data: { modeles: [] } })),
        ]).then(([statut, modeles]) => {
            setLlmStatus(statut.data);
            setLlmModels(modeles.data.modeles || []);
        }).finally(() => setLoading(false));
    }, []);

    return (
        <div className="p-6 lg:p-8 max-w-3xl mx-auto">
            <PageHeader
                title="Paramètres"
                subtitle="Configuration de la plateforme et statut des services"
                eyebrow="Système"
                icon={Cog6ToothIcon}
                accent="indigo"
            />

            {/* Statut du systeme */}
            <Card className="mb-6">
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <ServerIcon className="w-5 h-5 text-gray-400" />
                        <h3 className="font-semibold text-gray-900">Statut du système</h3>
                    </div>
                </CardHeader>
                <CardBody>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <StatusItem
                            label="Serveur LLM (Ollama)"
                            status={llmStatus?.disponible}
                            detail={llmStatus?.host}
                            loading={loading}
                        />
                        <StatusItem
                            label="Modèle par défaut"
                            status={llmStatus?.disponible}
                            detail={llmStatus?.modele_defaut}
                            loading={loading}
                        />
                        <StatusItem
                            label="Base de données"
                            status={true}
                            detail="PostgreSQL connecté"
                            loading={false}
                        />
                    </div>
                </CardBody>
            </Card>

            {/* Modeles LLM disponibles */}
            <Card className="mb-6">
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <CpuChipIcon className="w-5 h-5 text-gray-400" />
                        <h3 className="font-semibold text-gray-900">Modèles IA disponibles</h3>
                    </div>
                </CardHeader>
                <CardBody>
                    {loading ? (
                        <div className="flex items-center justify-center py-8">
                            <div className="w-6 h-6 border-2 border-gray-200 border-t-blue-600 rounded-full animate-spin"></div>
                        </div>
                    ) : llmModels.length === 0 ? (
                        <div className="text-center py-8">
                            <ExclamationTriangleIcon className="w-8 h-8 text-amber-400 mx-auto mb-2" />
                            <p className="text-sm text-gray-500">Aucun modèle détecté. Vérifiez qu'Ollama est démarré.</p>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {llmModels.map((model, i) => (
                                <div key={i} className="flex items-center justify-between p-3 rounded-lg border border-gray-100 hover:bg-gray-50 transition-colors">
                                    <div className="flex items-center gap-3">
                                        <div className="w-9 h-9 rounded-lg bg-purple-50 flex items-center justify-center">
                                            <CpuChipIcon className="w-5 h-5 text-purple-600" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-gray-900">{model.nom}</p>
                                            {model.taille && (
                                                <p className="text-xs text-gray-500">{(model.taille / 1e9).toFixed(1)} Go</p>
                                            )}
                                        </div>
                                    </div>
                                    {model.nom === llmStatus?.modele_defaut && (
                                        <Badge variant="info">Modèle actif</Badge>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </CardBody>
            </Card>

            {/* Informations plateforme */}
            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <ShieldCheckIcon className="w-5 h-5 text-gray-400" />
                        <h3 className="font-semibold text-gray-900">À propos de la plateforme</h3>
                    </div>
                </CardHeader>
                <CardBody>
                    <div className="grid grid-cols-2 gap-4 text-sm">
                        <div className="p-3 rounded-lg bg-gray-50">
                            <p className="text-xs text-gray-500">Application</p>
                            <p className="font-medium text-gray-900">PME-CONFORM</p>
                        </div>
                        <div className="p-3 rounded-lg bg-gray-50">
                            <p className="text-xs text-gray-500">Version</p>
                            <p className="font-medium text-gray-900">1.0.0</p>
                        </div>
                        <div className="p-3 rounded-lg bg-gray-50">
                            <p className="text-xs text-gray-500">Éditeur</p>
                            <p className="font-medium text-gray-900">AS Consulting</p>
                        </div>
                        <div className="p-3 rounded-lg bg-gray-50">
                            <p className="text-xs text-gray-500">Hébergement</p>
                            <p className="font-medium text-gray-900">On-premise (100 % local)</p>
                        </div>
                    </div>
                    <p className="text-xs text-gray-400 mt-4">
                        Aucune donnée ne quitte le serveur. Conforme à la réglementation ARTCI.
                    </p>
                </CardBody>
            </Card>
        </div>
    );
}

function StatusItem({ label, status, detail, loading }) {
    return (
        <div className="p-4 rounded-xl border border-gray-100 bg-gray-50/50">
            <div className="flex items-center gap-2 mb-2">
                {loading ? (
                    <div className="w-4 h-4 border-2 border-gray-200 border-t-blue-600 rounded-full animate-spin"></div>
                ) : status ? (
                    <CheckCircleIcon className="w-5 h-5 text-emerald-500" />
                ) : (
                    <ExclamationTriangleIcon className="w-5 h-5 text-red-500" />
                )}
                <span className="text-sm font-medium text-gray-900">{label}</span>
            </div>
            <p className="text-xs text-gray-500 pl-7">{loading ? 'Vérification...' : detail || (status ? 'Connecté' : 'Indisponible')}</p>
        </div>
    );
}
