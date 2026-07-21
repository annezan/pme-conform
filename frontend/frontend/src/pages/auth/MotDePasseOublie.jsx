/**
 * MotDePasseOublie — Page publique demandant l'envoi d'un lien de
 * réinitialisation par e-mail.
 *
 * Sécurité : le backend renvoie la même réponse que l'e-mail existe ou non
 * en base (anti-énumération des comptes). Le frontend affiche un message
 * neutre invitant à vérifier la boîte de réception.
 */

import { useState } from 'react';
import { Link } from 'react-router-dom';
import { forgotPassword } from '@/api/passwordReset';
import { alertError } from '@/utils/alerts';
import {
    EnvelopeIcon, ArrowLeftIcon, CheckCircleIcon, KeyIcon,
} from '@heroicons/react/24/outline';

export default function MotDePasseOublie() {
    const [email, setEmail] = useState('');
    const [sending, setSending] = useState(false);
    const [submitted, setSubmitted] = useState(false);

    const submit = async (e) => {
        e.preventDefault();
        if (!email) return;
        setSending(true);
        try {
            await forgotPassword(email);
            setSubmitted(true);
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur lors de la demande.');
        } finally {
            setSending(false);
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-950 via-blue-950 to-indigo-950 px-4">
            <div className="max-w-md w-full bg-white rounded-2xl shadow-2xl p-8">
                <Link to="/login" className="inline-flex items-center gap-1 text-sm text-blue-700 hover:text-blue-900 mb-4">
                    <ArrowLeftIcon className="w-4 h-4" /> Retour à la connexion
                </Link>

                {submitted ? (
                    <div className="text-center">
                        <div className="w-16 h-16 rounded-full bg-emerald-100 mx-auto mb-5 flex items-center justify-center">
                            <CheckCircleIcon className="w-8 h-8 text-emerald-600" />
                        </div>
                        <h1 className="text-xl font-bold text-gray-900 mb-2">Vérifiez vos e-mails</h1>
                        <p className="text-sm text-gray-600 leading-relaxed">
                            Si l'adresse <strong>{email}</strong> correspond à un compte PME-CONFORM,
                            vous allez recevoir un lien de réinitialisation dans quelques instants.
                        </p>
                        <p className="text-xs text-gray-500 mt-4">
                            Pensez à vérifier votre dossier "Courrier indésirable" si vous ne voyez pas l'e-mail.
                        </p>
                    </div>
                ) : (
                    <>
                        <div className="w-16 h-16 rounded-full bg-blue-100 mx-auto mb-5 flex items-center justify-center">
                            <KeyIcon className="w-8 h-8 text-blue-700" />
                        </div>
                        <h1 className="text-2xl font-bold text-gray-900 text-center mb-2">
                            Mot de passe oublié
                        </h1>
                        <p className="text-sm text-gray-600 text-center mb-6">
                            Saisissez l'adresse e-mail associée à votre compte. Nous vous enverrons un lien
                            pour définir un nouveau mot de passe.
                        </p>

                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <label className="block text-sm font-semibold text-gray-700 mb-1.5">
                                    Adresse e-mail
                                </label>
                                <div className="relative">
                                    <input
                                        type="email"
                                        value={email}
                                        onChange={e => setEmail(e.target.value)}
                                        required
                                        autoFocus
                                        className="w-full px-3 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="vous@entreprise.ci"
                                    />
                                    <EnvelopeIcon className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                </div>
                            </div>

                            <button
                                type="submit"
                                disabled={sending || !email}
                                className="w-full bg-blue-700 text-white py-2.5 rounded-lg font-semibold hover:bg-blue-800 transition-colors disabled:opacity-60"
                            >
                                {sending ? 'Envoi en cours...' : 'Envoyer le lien de réinitialisation'}
                            </button>
                        </form>
                    </>
                )}
            </div>
        </div>
    );
}
