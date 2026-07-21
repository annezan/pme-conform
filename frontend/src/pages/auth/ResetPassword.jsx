/**
 * ResetPassword — Page accédée via le lien envoyé par e-mail.
 *
 * URL : /reset-password/:token?email=xxx
 * L'utilisateur saisit son nouveau mot de passe. Le backend valide le token
 * (durée 60 min par défaut, hashé en table password_reset_tokens).
 */

import { useState } from 'react';
import { useParams, useSearchParams, Link, useNavigate } from 'react-router-dom';
import { resetPassword } from '@/api/passwordReset';
import { alertError, alertSuccess } from '@/utils/alerts';
import {
    KeyIcon, EyeIcon, EyeSlashIcon, ShieldCheckIcon, ArrowLeftIcon,
    CheckCircleIcon, ExclamationTriangleIcon,
} from '@heroicons/react/24/outline';

export default function ResetPassword() {
    const { token } = useParams();
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();

    const [email, setEmail] = useState(searchParams.get('email') || '');
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [showPwd, setShowPwd] = useState(false);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState({});
    const [success, setSuccess] = useState(false);

    const submit = async (e) => {
        e.preventDefault();

        const localErrors = {};
        if (!email) localErrors.email = 'Adresse e-mail requise.';
        if (password.length < 8) localErrors.password = 'Au moins 8 caractères.';
        if (password !== confirmation) localErrors.confirmation = 'Les mots de passe ne correspondent pas.';
        if (Object.keys(localErrors).length > 0) {
            setErrors(localErrors);
            return;
        }

        setSaving(true);
        setErrors({});
        try {
            await resetPassword({
                email,
                token,
                password,
                password_confirmation: confirmation,
            });
            setSuccess(true);
            alertSuccess('Mot de passe réinitialisé. Vous pouvez maintenant vous connecter.');
            setTimeout(() => navigate('/login'), 2500);
        } catch (err) {
            const respErrors = err.response?.data?.errors;
            if (respErrors) {
                const flat = {};
                Object.entries(respErrors).forEach(([k, msgs]) => { flat[k] = Array.isArray(msgs) ? msgs[0] : msgs; });
                setErrors(flat);
            }
            alertError(err.response?.data?.message || 'Impossible de réinitialiser le mot de passe.');
        } finally {
            setSaving(false);
        }
    };

    if (success) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-950 via-blue-950 to-indigo-950 px-4">
                <div className="max-w-md w-full bg-white rounded-2xl shadow-2xl p-8 text-center">
                    <div className="w-16 h-16 rounded-full bg-emerald-100 mx-auto mb-5 flex items-center justify-center">
                        <CheckCircleIcon className="w-8 h-8 text-emerald-600" />
                    </div>
                    <h1 className="text-2xl font-bold text-gray-900 mb-2">Mot de passe réinitialisé</h1>
                    <p className="text-sm text-gray-600">
                        Votre mot de passe a été modifié avec succès. Redirection vers la page de connexion...
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-950 via-blue-950 to-indigo-950 px-4">
            <div className="max-w-md w-full bg-white rounded-2xl shadow-2xl p-8">
                <Link to="/login" className="inline-flex items-center gap-1 text-sm text-blue-700 hover:text-blue-900 mb-4">
                    <ArrowLeftIcon className="w-4 h-4" /> Retour à la connexion
                </Link>

                <div className="w-16 h-16 rounded-full bg-blue-100 mx-auto mb-5 flex items-center justify-center">
                    <KeyIcon className="w-8 h-8 text-blue-700" />
                </div>

                <h1 className="text-2xl font-bold text-gray-900 text-center mb-2">
                    Nouveau mot de passe
                </h1>
                <p className="text-sm text-gray-600 text-center mb-6">
                    Définissez votre nouveau mot de passe pour le compte ci-dessous.
                </p>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1.5">
                            Adresse e-mail <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="email"
                            value={email}
                            onChange={e => setEmail(e.target.value)}
                            required
                            readOnly={!!searchParams.get('email')}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 read-only:bg-gray-50"
                        />
                        {errors.email && <p className="text-xs text-red-600 mt-1">{errors.email}</p>}
                    </div>

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

                    {errors.token && (
                        <div className="bg-red-50 border border-red-200 rounded-lg p-3 text-xs text-red-900 flex gap-2">
                            <ExclamationTriangleIcon className="w-4 h-4 shrink-0 mt-0.5" />
                            <div>{errors.token}</div>
                        </div>
                    )}

                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-900 flex gap-2">
                        <ShieldCheckIcon className="w-4 h-4 shrink-0 mt-0.5" />
                        <div>
                            <p className="font-semibold mb-1">Bonnes pratiques :</p>
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
                        {saving ? 'Enregistrement...' : 'Réinitialiser mon mot de passe'}
                    </button>
                </form>
            </div>
        </div>
    );
}
