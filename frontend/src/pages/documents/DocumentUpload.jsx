/**
 * Page DocumentUpload premium.
 * L'upload doit etre rattache a une mission pour etre analyse ensuite.
 */

import { useState, useEffect } from 'react';
import api from '@/api/client';
import { uploadDocument } from '@/api/documents';
import { alertSuccess, alertError } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card, { CardHeader, CardBody } from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import { Input, Select, Textarea } from '@/components/ui/Input';
import { CloudArrowUpIcon, DocumentIcon, CheckCircleIcon } from '@heroicons/react/24/outline';

export default function DocumentUpload() {
    const [missions, setMissions] = useState([]);
    const [form, setForm] = useState({ titre: '', description: '', mission_id: '' });
    const [file, setFile] = useState(null);
    const [loading, setLoading] = useState(false);
    const [result, setResult] = useState(null);
    const [dragOver, setDragOver] = useState(false);

    useEffect(() => {
        api.get('/missions?per_page=100').then(r => setMissions(r.data.data || []));
    }, []);

    const handleFile = (f) => {
        if (f && (f.type === 'application/pdf' || f.name.endsWith('.docx') || f.name.endsWith('.doc'))) {
            setFile(f);
        } else {
            alertError('Format non supporté. Utilisez PDF ou DOCX.');
        }
    };

    const handleDrop = (e) => {
        e.preventDefault();
        setDragOver(false);
        handleFile(e.dataTransfer.files[0]);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!file) return;
        if (!form.mission_id) {
            alertError('Veuillez sélectionner une mission.');
            return;
        }

        setLoading(true);
        setResult(null);

        const formData = new FormData();
        formData.append('fichier', file);
        formData.append('titre', form.titre || file.name);
        if (form.description) formData.append('description', form.description);
        formData.append('mission_id', form.mission_id);
        formData.append('type', 'document_client');

        try {
            const data = await uploadDocument(formData);
            setResult(data);
            alertSuccess('Document uploadé avec succès');
            setFile(null);
            setForm({ ...form, titre: '', description: '' });
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur lors de l\'upload');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="p-6 lg:p-8 max-w-2xl mx-auto">
            <PageHeader
                title="Upload de documents"
                subtitle="Alimentez la base de connaissances des agents IA"
                eyebrow="Indexation RAG"
                icon={CloudArrowUpIcon}
                accent="blue"
            />

            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <CloudArrowUpIcon className="w-5 h-5 text-gray-400" />
                        <h2 className="font-semibold text-gray-900">Nouveau document</h2>
                    </div>
                </CardHeader>
                <CardBody>
                    <form onSubmit={handleSubmit} className="space-y-5">
                        <Select
                            label="Mission *"
                            value={form.mission_id}
                            onChange={e => setForm({...form, mission_id: e.target.value})}
                            required
                        >
                            <option value="">-- Sélectionner une mission --</option>
                            {missions.map(m => (
                                <option key={m.id} value={m.id}>
                                    {m.reference} - {m.titre} ({m.client?.raison_sociale})
                                </option>
                            ))}
                        </Select>

                        {/* Zone de depot */}
                        <div
                            onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
                            onDragLeave={() => setDragOver(false)}
                            onDrop={handleDrop}
                            className={`relative border-2 border-dashed rounded-xl p-8 text-center transition-all cursor-pointer ${
                                dragOver ? 'border-blue-400 bg-blue-50' : file ? 'border-emerald-300 bg-emerald-50/50' : 'border-gray-300 hover:border-blue-400 hover:bg-gray-50'
                            }`}
                        >
                            <input
                                type="file"
                                accept=".pdf,.docx,.doc"
                                onChange={(e) => handleFile(e.target.files[0])}
                                className="absolute inset-0 opacity-0 cursor-pointer"
                            />
                            {file ? (
                                <div className="flex flex-col items-center">
                                    <CheckCircleIcon className="w-10 h-10 text-emerald-500 mb-2" />
                                    <p className="font-medium text-gray-900">{file.name}</p>
                                    <p className="text-xs text-gray-500 mt-1">{(file.size / 1024 / 1024).toFixed(2)} Mo</p>
                                    <button type="button" onClick={(e) => { e.stopPropagation(); setFile(null); }} className="text-xs text-red-500 hover:text-red-700 mt-2">Retirer</button>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center">
                                    <DocumentIcon className="w-10 h-10 text-gray-400 mb-2" />
                                    <p className="text-sm font-medium text-gray-700">Glissez-déposez un fichier ici</p>
                                    <p className="text-xs text-gray-400 mt-1">ou cliquez pour sélectionner — PDF, DOCX (max 20 Mo)</p>
                                </div>
                            )}
                        </div>

                        <Input label="Titre du document" value={form.titre} onChange={e => setForm({...form, titre: e.target.value})}
                            placeholder="Titre personnalisé (nom du fichier par défaut)" />

                        <Textarea label="Description (optionnel)" value={form.description} onChange={e => setForm({...form, description: e.target.value})}
                            rows={2} placeholder="Brève description du contenu du document" />

                        {result && (
                            <div className="flex items-center gap-2 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-700">
                                <CheckCircleIcon className="w-5 h-5 shrink-0" />
                                {result.message} — Statut: {result.document?.statut}
                            </div>
                        )}

                        <Button type="submit" disabled={loading || !file || !form.mission_id} size="lg" className="w-full">
                            {loading ? (
                                <><svg className="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none"/><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg> Upload en cours...</>
                            ) : (
                                <><CloudArrowUpIcon className="w-5 h-5" /> Uploader le document</>
                            )}
                        </Button>
                    </form>
                </CardBody>
            </Card>
        </div>
    );
}
