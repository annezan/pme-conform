/**
 * AdminComptesEnAttente — Vue ASC des comptes inscrits via /inscription
 * et en attente de validation. Permet de les approuver (email automatique
 * envoyé à l'utilisateur) ou de les rejeter (soft-delete).
 */

import { useEffect, useState } from 'react';
import { listComptesEnAttente, validerCompte, rejeterCompte } from '@/api/comptesEnAttente';
import { alertSuccess, alertError, confirmAction } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import EmptyState from '@/components/ui/EmptyState';
import Modal from '@/components/ui/Modal';
import { Input, Textarea } from '@/components/ui/Input';
import {
    UserPlusIcon, CheckCircleIcon, XCircleIcon, BuildingOffice2Icon,
    EnvelopeIcon, MagnifyingGlassIcon, ClockIcon, ExclamationTriangleIcon,
} from '@heroicons/react/24/outline';

export default function AdminComptesEnAttente() {
    const [comptes, setComptes] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [actionInProgress, setActionInProgress] = useState(null);

    // Modale de rejet : le motif est obligatoire (transmis au demandeur par email)
    const [userARejeter, setUserARejeter] = useState(null);
    const [motifRejet, setMotifRejet] = useState('');
    const [rejetEnCours, setRejetEnCours] = useState(false);

    const charger = async () => {
        setLoading(true);
        try {
            const r = await listComptesEnAttente({ q: search || undefined });
            setComptes(r.data || []);
        } catch (err) {
            alertError(err.response?.data?.message || 'Impossible de charger les comptes en attente');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { charger(); }, []);

    useEffect(() => {
        const t = setTimeout(charger, 350);
        return () => clearTimeout(t);
    }, [search]);

    const handleValider = async (user) => {
        const confirmed = await confirmAction(
            `Valider le compte de ${user.prenom} ${user.nom} (${user.email}) ? Un e-mail de confirmation sera envoyé.`,
            'Valider le compte'
        );
        if (!confirmed) return;
        setActionInProgress(user.id);
        try {
            await validerCompte(user.id);
            alertSuccess('Compte validé. L\'utilisateur peut désormais se connecter.');
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Validation refusée');
        } finally {
            setActionInProgress(null);
        }
    };

    // Ouvre la modale de rejet (le motif sera saisi dedans, obligatoire)
    const handleOuvrirModaleRejet = (user) => {
        setUserARejeter(user);
        setMotifRejet('');
    };

    const handleFermerModaleRejet = () => {
        setUserARejeter(null);
        setMotifRejet('');
    };

    // Confirme le rejet avec le motif saisi
    const handleConfirmerRejet = async () => {
        if (!userARejeter) return;
        const motif = motifRejet.trim();
        if (motif.length < 10) {
            alertError('Le motif doit contenir au moins 10 caractères.');
            return;
        }
        setRejetEnCours(true);
        try {
            const r = await rejeterCompte(userARejeter.id, motif);
            alertSuccess(r?.message || 'Compte rejeté. Le demandeur a été informé par e-mail.');
            handleFermerModaleRejet();
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Rejet impossible');
        } finally {
            setRejetEnCours(false);
        }
    };

    return (
        <div className="p-6 lg:p-8 max-w-6xl mx-auto">
            <PageHeader
                title="Comptes en attente de validation"
                subtitle="Comptes créés via le formulaire d'inscription publique. Validez ou rejetez pour autoriser la connexion."
                eyebrow="Administration"
                icon={UserPlusIcon}
                accent="amber"
            >
                <Badge variant="warning" solid>{comptes.length} en attente</Badge>
            </PageHeader>

            <Card className="mb-4 p-4">
                <Input
                    icon={MagnifyingGlassIcon}
                    value={search}
                    onChange={e => setSearch(e.target.value)}
                    placeholder="Rechercher par nom, prénom ou e-mail..."
                />
            </Card>

            {loading && <p className="text-sm text-gray-500 px-2">Chargement...</p>}

            {!loading && comptes.length === 0 && (
                <EmptyState
                    icon={CheckCircleIcon}
                    title="Aucun compte en attente"
                    description="Toutes les inscriptions ont été traitées. Les nouveaux comptes apparaîtront ici dès qu'un visiteur s'inscrira via /inscription."
                    accent="emerald"
                />
            )}

            <div className="space-y-3">
                {comptes.map(user => {
                    const client = user.clients?.[0];
                    return (
                        <Card key={user.id} className="p-4">
                            <div className="flex flex-col lg:flex-row lg:items-center gap-4">
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-3 mb-2 flex-wrap">
                                        <div className="w-10 h-10 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center font-bold">
                                            {(user.prenom?.[0] || '?').toUpperCase()}{(user.nom?.[0] || '').toUpperCase()}
                                        </div>
                                        <div>
                                            <p className="font-semibold text-gray-900">
                                                {user.prenom} {user.nom}
                                            </p>
                                            <p className="text-xs text-gray-500 flex items-center gap-1">
                                                <EnvelopeIcon className="w-3.5 h-3.5" /> {user.email}
                                            </p>
                                        </div>
                                        <Badge variant="warning" dot>En attente</Badge>
                                        <Badge variant="gray">{user.role?.name || 'client_admin'}</Badge>
                                    </div>

                                    {client && (
                                        <div className="text-xs text-gray-600 flex items-center gap-2 ml-13 pl-1">
                                            <BuildingOffice2Icon className="w-3.5 h-3.5" />
                                            <span className="font-medium">Entreprise :</span>
                                            {client.raison_sociale}
                                            {client.sigle && <span className="text-gray-400 font-mono">({client.sigle})</span>}
                                            <Badge variant="gray" size="sm">{client.statut}</Badge>
                                        </div>
                                    )}

                                    <div className="text-xs text-gray-400 flex items-center gap-1 mt-2">
                                        <ClockIcon className="w-3.5 h-3.5" />
                                        Inscrit le {new Date(user.created_at).toLocaleString('fr-FR')}
                                    </div>
                                </div>

                                <div className="flex items-center gap-2 shrink-0">
                                    <Button
                                        variant="success"
                                        size="sm"
                                        onClick={() => handleValider(user)}
                                        disabled={actionInProgress === user.id}
                                    >
                                        <CheckCircleIcon className="w-4 h-4" />
                                        {actionInProgress === user.id ? 'Validation...' : 'Valider'}
                                    </Button>
                                    <Button
                                        variant="danger"
                                        size="sm"
                                        onClick={() => handleOuvrirModaleRejet(user)}
                                        disabled={actionInProgress === user.id}
                                    >
                                        <XCircleIcon className="w-4 h-4" />
                                        Rejeter
                                    </Button>
                                </div>
                            </div>
                        </Card>
                    );
                })}
            </div>

            {/* Modale de rejet avec motif obligatoire */}
            {userARejeter && (
                <Modal
                    open={true}
                    onClose={handleFermerModaleRejet}
                    title="Rejeter la demande d'inscription"
                    subtitle={`${userARejeter.prenom} ${userARejeter.nom} — ${userARejeter.email}`}
                    icon={XCircleIcon}
                    accent="rose"
                    size="md"
                    footer={(
                        <>
                            <Button variant="secondary" onClick={handleFermerModaleRejet} disabled={rejetEnCours}>
                                Annuler
                            </Button>
                            <Button
                                variant="danger"
                                onClick={handleConfirmerRejet}
                                loading={rejetEnCours}
                                disabled={motifRejet.trim().length < 10 || rejetEnCours}
                            >
                                {rejetEnCours ? 'Rejet en cours…' : 'Rejeter et envoyer l\'e-mail'}
                            </Button>
                        </>
                    )}
                >
                    <div className="space-y-4">
                        <div className="flex items-start gap-3 p-3 rounded-lg bg-rose-50 border border-rose-200">
                            <ExclamationTriangleIcon className="w-5 h-5 text-rose-600 shrink-0 mt-0.5" />
                            <div className="text-sm text-rose-900">
                                Le motif ci-dessous sera <strong>transmis au demandeur par e-mail</strong> pour lui
                                permettre de comprendre le refus et éventuellement de corriger sa demande.
                                Soyez clair, courtois et précis.
                            </div>
                        </div>

                        <Textarea
                            label="Motif du refus (obligatoire)"
                            required
                            value={motifRejet}
                            onChange={e => setMotifRejet(e.target.value)}
                            rows={5}
                            placeholder="Ex : Les informations de l'entreprise fournies (RCCM, adresse) n'ont pas pu être vérifiées auprès du greffe. Merci de compléter votre dossier avec un justificatif de moins de 3 mois."
                            maxLength={2000}
                        />
                        <p className={`text-xs ${motifRejet.trim().length < 10 ? 'text-rose-600' : 'text-gray-500'}`}>
                            {motifRejet.trim().length}/2000 caractères (minimum 10)
                        </p>
                    </div>
                </Modal>
            )}
        </div>
    );
}
