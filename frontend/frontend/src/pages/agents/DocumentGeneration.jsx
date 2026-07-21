/**
 * Page DocumentGeneration premium.
 */

import { useState } from 'react';
import { generateDocument, downloadGenerated } from '@/api/documents';
import { useAgents } from '@/hooks/useAgents';
import { alertSuccess, alertError } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card, { CardHeader, CardBody } from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import { Select, Textarea } from '@/components/ui/Input';
import Loader from '@/components/ui/Loader';
import { DocumentTextIcon, ArrowDownTrayIcon, SparklesIcon } from '@heroicons/react/24/outline';

const typesDocument = [
    { value: 'rapport_audit', label: 'Rapport d\'audit de conformité' },
    { value: 'politique', label: 'Politique de confidentialité' },
    { value: 'registre', label: 'Registre des activités de traitement' },
    { value: 'aipd', label: 'Analyse d\'impact (AIPD)' },
    { value: 'courrier_artci', label: 'Courrier à l\'ARTCI' },
    { value: 'charte', label: 'Charte informatique' },
];

export default function DocumentGeneration() {
    const { agents } = useAgents();
    const generateurs = agents.filter((a) => a.type === 'generateur');

    const [form, setForm] = useState({ agent_id: '', type_document: '', contexte: '' });
    const [result, setResult] = useState(null);
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setResult(null);

        try {
            const data = await generateDocument(parseInt(form.agent_id), form.type_document, form.contexte);
            setResult(data);
            alertSuccess('Document généré avec succès');
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur lors de la génération');
        } finally {
            setLoading(false);
        }
    };

    const handleDownload = async () => {
        if (result?.message_id) {
            await downloadGenerated(result.message_id);
            alertSuccess('Téléchargement lancé');
        }
    };

    return (
        <div className="p-6 lg:p-8 max-w-4xl mx-auto">
            <PageHeader
                title="Génération de documents"
                subtitle="Utilisez un agent IA pour générer des documents professionnels"
                eyebrow="Propulsé par l'IA"
                icon={DocumentTextIcon}
                accent="purple"
            >
                <div className="flex items-center gap-2 text-sm text-gray-500">
                    <SparklesIcon className="w-5 h-5 text-purple-500" /> Propulsé par l'IA
                </div>
            </PageHeader>

            <Card className="mb-6">
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <DocumentTextIcon className="w-5 h-5 text-gray-400" />
                        <h2 className="font-semibold text-gray-900">Paramètres de génération</h2>
                    </div>
                </CardHeader>
                <CardBody>
                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <Select label="Agent générateur *" value={form.agent_id} onChange={e => setForm({...form, agent_id: e.target.value})} required>
                                <option value="">Sélectionner un agent</option>
                                {generateurs.map(a => <option key={a.id} value={a.id}>{a.nom}</option>)}
                            </Select>
                            <Select label="Type de document *" value={form.type_document} onChange={e => setForm({...form, type_document: e.target.value})} required>
                                <option value="">Sélectionner un type</option>
                                {typesDocument.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                            </Select>
                        </div>
                        <Textarea label="Contexte et instructions *" value={form.contexte} onChange={e => setForm({...form, contexte: e.target.value})}
                            rows={6} required placeholder="Décrivez le contexte : entreprise cliente, secteur, spécificités, exigences particulières..." />
                        <div className="flex items-center gap-3">
                            <Button type="submit" disabled={loading} size="lg">
                                {loading ? (
                                    <><svg className="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none"/><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg> Génération en cours...</>
                                ) : (
                                    <><SparklesIcon className="w-4 h-4" /> Générer le document</>
                                )}
                            </Button>
                            {result && (
                                <Button variant="success" size="lg" onClick={handleDownload} type="button">
                                    <ArrowDownTrayIcon className="w-4 h-4" /> Télécharger (.md)
                                </Button>
                            )}
                        </div>
                    </form>
                </CardBody>
            </Card>

            {/* Aperçu */}
            {result && (
                <Card className="animate-fadeIn">
                    <CardHeader className="flex items-center justify-between">
                        <h3 className="font-semibold text-gray-900">Aperçu du document généré</h3>
                        <button onClick={() => window.print()} className="text-sm text-blue-600 hover:text-blue-700 font-medium">Imprimer</button>
                    </CardHeader>
                    <CardBody>
                        <div className="prose prose-sm max-w-none whitespace-pre-wrap text-gray-700 leading-relaxed">
                            {result.contenu}
                        </div>
                    </CardBody>
                </Card>
            )}
        </div>
    );
}
