/**
 * ChangerMotDePasse — Page de changement OBLIGATOIRE du mot de passe temporaire.
 *
 * Affichee automatiquement par AuthContext quand user.must_change_password === true.
 * Tant que le user n'a pas defini un nouveau mot de passe, il ne peut acceder
 * a aucune autre page de la plateforme (middleware backend + redirection frontend).
 */

import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '@/contexts/AuthContext';
import { changeTemporaryPassword } from '@/api/clientUsers';
import { alertSuccess, alertError } from '@/utils/alerts';
import { KeyIcon, ShieldCheckIcon, EyeIcon, EyeSlashIcon } from '@heroicons/react/24/outline';

export default function ChangerMotDePasse() {
    const { user, fetchUser } = useAuth();
    const navigate = useNavigate();
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [showPwd, setShowPwd] = useState(false);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState({});

    const submit = async (e) => {
        e.preventDefault();

        const localErrors = {};
        if (password.length < 8) localErrors.password = 'Au moins 8 caractères.';
        if (password !== confirmation) localErrors.confirmation = 'Les mots de passe ne correspondent pas.';
        if (Object.keys(localErrors).length > 0) {
            setErrors(localErrors);
            return;
        }

        setSaving(true);
        setErrors({});
        try {
            await changeTemporaryPassword(password, confirmation);
            await fetchUser();
            alertSuccess('Mot de passe modifié. Bienvenue !');
            navigate('/');
        } catch (err) {
            const respErrors = err.response?.data?.errors;
            if (respErrors) {
                const flat = {};
                Object.entries(respErrors).forEach(([k, msgs]) => { flat[k] = Array.isArray(msgs) ? msgs[0] : msgs; });
                setErrors(flat);
            }
            alertError(err.response?.data?.message || 'Impossible de modifier le mot de passe.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-950 via-blue-950 to-indigo-950 px-4">
            <div className="max-w-md w-full bg-white rounded-2xl shadow-2xl p-8">
                <div className="w-16 h-16 rounded-full bg-amber-100 mx-auto mb-5 flex items-center justify-center">
                    <KeyIcon className="w-8 h-8 text-amber-600" />
                </div>

                <h1 className="text-2xl font-bold text-gray-900 text-center mb-2">
                    Changement de mot de passe requis
                </h1>
                <p className="text-sm text-gray-600 text-center mb-6">
                    Bonjour <strong>{user?.prenom}</strong>, pour des raisons de sécurité vous devez
                    définir un nouveau mot de passe avant de pouvoir utiliser la plateforme.
                </p>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1.5">
                            Nouveau mot de passe <span className="text-red-500">*</span>
                        </label>
                        <div className="relative">
                            <input
                                type={showPwd ? 'text' : 'password'}
                                value={password}
                                onChange={e => setPassword(e.target.value)}
                                required
                                minLength={8}
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pr-10"
                                placeholder="Au moins 8 caractères"
                            />
                            <button
                                type="button"
                                onClick={() => setShowPwd(!showPwd)}
                                className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            >
                                {showPwd ? <EyeSlashIcon className="w-4 h-4" /> : <EyeIcon className="w-4 h-4" />}
                            </button>
                        </div>
                        {errors.password && <p className="text-xs text-red-600 mt-1">{errors.password}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1.5">
                            Confirmation <span className="text-red-500">*</span>
                        </label>
                        <input
                            type={showPwd ? 'text' : 'password'}
                            value={confirmation}
                            onChange={e => setConfirmation(e.target.value)}
                            required
                            minLength={8}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Re-saisissez le mot de passe"
                        />
                        {errors.confirmation && <p className="text-xs text-red-600 mt-1">{errors.confirmation}</p>}
                    </div>

                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-900 flex gap-2">
                        <ShieldCheckIcon className="w-4 h-4 shrink-0 mt-0.5" />
                        <div>
                            <p className="font-semibold mb-1">Conseils de sécurité :</p>
                            <ul className="list-disc list-inside space-y-0.5">
                                <li>Au moins 8 caractères (12 recommandés)</li>
                                <li>Mélangez majuscules, minuscules, chiffres et symboles</li>
                                <li>Ne réutilisez pas un mot de passe d'un autre site</li>
                            </ul>
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={saving}
                        className="w-full bg-blue-700 text-white py-2.5 rounded-lg font-semibold hover:bg-blue-800 transition-colors disabled:opacity-60"
                    >
                        {saving ? 'Enregistrement...' : 'Définir mon mot de passe'}
                    </button>
                </form>
            </div>
        </div>
    );
}
