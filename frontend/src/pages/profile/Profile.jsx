/**
 * Page Profil — Informations et modification du profil utilisateur.
 */

import { useState } from 'react';
import { useAuth } from '@/contexts/AuthContext';
import api, { getCsrfCookie } from '@/api/client';
import { alertSuccess, alertError } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card, { CardHeader, CardBody } from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import Badge from '@/components/ui/Badge';
import {
    UserCircleIcon,
    EnvelopeIcon,
    PhoneIcon,
    BriefcaseIcon,
    ShieldCheckIcon,
    CalendarDaysIcon,
    PencilSquareIcon,
    CheckIcon,
    XMarkIcon,
    KeyIcon,
} from '@heroicons/react/24/outline';

export default function Profile() {
    const { user, fetchUser } = useAuth();
    const [editing, setEditing] = useState(false);
    const [changingPassword, setChangingPassword] = useState(false);
    const [saving, setSaving] = useState(false);

    const [form, setForm] = useState({
        nom: user?.nom || '',
        prenom: user?.prenom || '',
        telephone: user?.telephone || '',
        poste: user?.poste || '',
    });

    const [passwordForm, setPasswordForm] = useState({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const handleSaveProfile = async () => {
        setSaving(true);
        try {
            await getCsrfCookie();
            await api.put('/user/profile', form);
            await fetchUser();
            alertSuccess('Profil mis à jour avec succès');
            setEditing(false);
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur lors de la mise à jour');
        } finally {
            setSaving(false);
        }
    };

    const handleChangePassword = async () => {
        if (passwordForm.password !== passwordForm.password_confirmation) {
            alertError('Les mots de passe ne correspondent pas');
            return;
        }
        setSaving(true);
        try {
            await getCsrfCookie();
            await api.put('/user/password', passwordForm);
            alertSuccess('Mot de passe modifié avec succès');
            setChangingPassword(false);
            setPasswordForm({ current_password: '', password: '', password_confirmation: '' });
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur lors du changement de mot de passe');
        } finally {
            setSaving(false);
        }
    };

    const cancelEdit = () => {
        setForm({ nom: user?.nom || '', prenom: user?.prenom || '', telephone: user?.telephone || '', poste: user?.poste || '' });
        setEditing(false);
    };

    return (
        <div className="p-6 lg:p-8 max-w-3xl mx-auto">
            <PageHeader
                title="Mon profil"
                subtitle="Gérez vos informations personnelles et votre mot de passe"
                eyebrow="Compte utilisateur"
                icon={UserCircleIcon}
                accent="blue"
            />

            {/* Carte profil */}
            <Card className="mb-6">
                <CardBody className="!p-0">
                    {/* Banniere */}
                    <div className="h-28 bg-gradient-to-r from-blue-600 to-indigo-700 rounded-t-xl relative">
                        <div className="absolute -bottom-10 left-6">
                            <div className="w-20 h-20 rounded-2xl bg-white shadow-lg flex items-center justify-center border-4 border-white">
                                <span className="text-2xl font-bold text-blue-700">
                                    {user?.prenom?.charAt(0)}{user?.nom?.charAt(0)}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="pt-14 px-6 pb-6">
                        <div className="flex items-start justify-between mb-6">
                            <div>
                                <h2 className="text-xl font-bold text-gray-900">{user?.nom_complet}</h2>
                                <p className="text-sm text-gray-500">{user?.email}</p>
                                <div className="flex items-center gap-2 mt-2">
                                    <Badge variant="info">{user?.roles?.[0]}</Badge>
                                    {user?.poste && <Badge variant="gray">{user?.poste}</Badge>}
                                </div>
                            </div>
                            {!editing && (
                                <Button variant="secondary" size="sm" onClick={() => setEditing(true)}>
                                    <PencilSquareIcon className="w-4 h-4" /> Modifier
                                </Button>
                            )}
                        </div>

                        {editing ? (
                            <div className="space-y-4 animate-fadeIn">
                                <div className="grid grid-cols-2 gap-4">
                                    <Input label="Prénom" value={form.prenom} onChange={e => setForm({...form, prenom: e.target.value})} />
                                    <Input label="Nom" value={form.nom} onChange={e => setForm({...form, nom: e.target.value})} />
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <Input label="Téléphone" value={form.telephone} onChange={e => setForm({...form, telephone: e.target.value})} placeholder="+225 XX XX XX XX" />
                                    <Input label="Poste" value={form.poste} onChange={e => setForm({...form, poste: e.target.value})} placeholder="Ex: Consultant senior" />
                                </div>
                                <div className="flex items-center gap-3 pt-2">
                                    <Button size="sm" onClick={handleSaveProfile} disabled={saving}>
                                        <CheckIcon className="w-4 h-4" /> {saving ? 'Sauvegarde...' : 'Sauvegarder'}
                                    </Button>
                                    <Button variant="secondary" size="sm" onClick={cancelEdit}>
                                        <XMarkIcon className="w-4 h-4" /> Annuler
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <InfoItem icon={UserCircleIcon} label="Nom complet" value={user?.nom_complet} />
                                <InfoItem icon={EnvelopeIcon} label="Email" value={user?.email} />
                                <InfoItem icon={PhoneIcon} label="Téléphone" value={user?.telephone || 'Non renseigné'} />
                                <InfoItem icon={BriefcaseIcon} label="Poste" value={user?.poste || 'Non renseigné'} />
                                <InfoItem icon={ShieldCheckIcon} label="Rôle" value={user?.roles?.[0]} />
                                <InfoItem icon={CalendarDaysIcon} label="Membre depuis" value={user?.created_at ? new Date(user.created_at).toLocaleDateString('fr-FR') : '-'} />
                            </div>
                        )}
                    </div>
                </CardBody>
            </Card>

            {/* Changement de mot de passe */}
            <Card>
                <CardHeader className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <KeyIcon className="w-5 h-5 text-gray-400" />
                        <h3 className="font-semibold text-gray-900">Sécurité</h3>
                    </div>
                    {!changingPassword && (
                        <Button variant="secondary" size="sm" onClick={() => setChangingPassword(true)}>
                            Changer le mot de passe
                        </Button>
                    )}
                </CardHeader>
                {changingPassword ? (
                    <CardBody className="space-y-4 animate-fadeIn">
                        <Input label="Mot de passe actuel" type="password" value={passwordForm.current_password}
                            onChange={e => setPasswordForm({...passwordForm, current_password: e.target.value})} />
                        <div className="grid grid-cols-2 gap-4">
                            <Input label="Nouveau mot de passe" type="password" value={passwordForm.password}
                                onChange={e => setPasswordForm({...passwordForm, password: e.target.value})} />
                            <Input label="Confirmer" type="password" value={passwordForm.password_confirmation}
                                onChange={e => setPasswordForm({...passwordForm, password_confirmation: e.target.value})} />
                        </div>
                        <div className="flex items-center gap-3 pt-2">
                            <Button size="sm" onClick={handleChangePassword} disabled={saving}>
                                {saving ? 'Modification...' : 'Modifier le mot de passe'}
                            </Button>
                            <Button variant="secondary" size="sm" onClick={() => { setChangingPassword(false); setPasswordForm({ current_password: '', password: '', password_confirmation: '' }); }}>
                                Annuler
                            </Button>
                        </div>
                    </CardBody>
                ) : (
                    <CardBody>
                        <p className="text-sm text-gray-500">Votre mot de passe doit contenir au moins 8 caractères.</p>
                    </CardBody>
                )}
            </Card>
        </div>
    );
}

function InfoItem({ icon: Icon, label, value }) {
    return (
        <div className="flex items-start gap-3 p-3 rounded-lg bg-gray-50/50">
            <Icon className="w-5 h-5 text-gray-400 mt-0.5 shrink-0" />
            <div>
                <p className="text-xs text-gray-500">{label}</p>
                <p className="text-sm font-medium text-gray-900">{value || '-'}</p>
            </div>
        </div>
    );
}
