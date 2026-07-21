/**
 * NotificationsBell — Cloche dans le header avec compteur de non-lues
 * et dropdown listant les notifications recentes.
 *
 * Polling toutes les 30s pour le compteur (pas le contenu complet).
 * Clic sur une notification : marque comme lue + navigue vers l'URL associee.
 */

import { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { BellIcon, CheckIcon, TrashIcon, XMarkIcon } from '@heroicons/react/24/outline';
import {
    listNotifications,
    unreadNotificationsCount,
    marquerNotificationLue,
    marquerToutesNotificationsLues,
    supprimerNotification,
} from '@/api/notifications';

const TYPE_ICON = {
    preuves_soumises_pour_validation: { emoji: '📥', label: 'Plan soumis' },
};

export default function NotificationsBell() {
    const navigate = useNavigate();
    const [unread, setUnread] = useState(0);
    const [open, setOpen] = useState(false);
    const [notifications, setNotifications] = useState([]);
    const [loading, setLoading] = useState(false);
    const panelRef = useRef(null);

    const rafraichirCompteur = async () => {
        try {
            const n = await unreadNotificationsCount();
            setUnread(n);
        } catch {
            // 401/403 silencieux (user pas connecte sur ecrans publics)
        }
    };

    // Compteur initial + polling toutes les 30s
    useEffect(() => {
        rafraichirCompteur();
        const i = setInterval(rafraichirCompteur, 30000);
        return () => clearInterval(i);
    }, []);

    // Ferme au clic exterieur
    useEffect(() => {
        const handler = (e) => {
            if (panelRef.current && !panelRef.current.contains(e.target)) setOpen(false);
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const ouvrir = async () => {
        const v = !open;
        setOpen(v);
        if (v) {
            setLoading(true);
            try {
                const page = await listNotifications(20);
                setNotifications(page.data || []);
            } catch {
                setNotifications([]);
            } finally { setLoading(false); }
        }
    };

    const cliquerNotification = async (n) => {
        if (!n.read_at) {
            try { await marquerNotificationLue(n.id); } catch {}
            setNotifications(prev => prev.map(x => x.id === n.id ? { ...x, read_at: new Date().toISOString() } : x));
            setUnread(u => Math.max(0, u - 1));
        }
        const url = n.data?.url;
        if (url) {
            setOpen(false);
            navigate(url);
        }
    };

    const supprimer = async (e, n) => {
        e.stopPropagation();
        try {
            await supprimerNotification(n.id);
            setNotifications(prev => prev.filter(x => x.id !== n.id));
            if (!n.read_at) setUnread(u => Math.max(0, u - 1));
        } catch {}
    };

    const toutesLues = async () => {
        try {
            await marquerToutesNotificationsLues();
            setNotifications(prev => prev.map(x => ({ ...x, read_at: x.read_at || new Date().toISOString() })));
            setUnread(0);
        } catch {}
    };

    return (
        <div className="relative" ref={panelRef}>
            <button
                onClick={ouvrir}
                className="w-9 h-9 rounded-lg text-blue-200 hover:text-white hover:bg-white/10 flex items-center justify-center transition-colors relative"
                title="Notifications"
            >
                <BellIcon className="w-5 h-5" />
                {unread > 0 && (
                    <span className="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-500 text-white text-[10px] font-bold flex items-center justify-center ring-2 ring-blue-900">
                        {unread > 99 ? '99+' : unread}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 top-full mt-2 w-96 bg-white rounded-xl shadow-xl border border-gray-200/60 py-1 animate-fadeIn z-50 max-h-[70vh] overflow-hidden flex flex-col">
                    <div className="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <p className="text-sm font-bold text-gray-900">Notifications</p>
                            <p className="text-[11px] text-gray-500">{unread > 0 ? `${unread} non lue${unread > 1 ? 's' : ''}` : 'Tout est à jour'}</p>
                        </div>
                        {unread > 0 && (
                            <button onClick={toutesLues} className="text-xs text-blue-600 hover:text-blue-800 font-medium inline-flex items-center gap-1">
                                <CheckIcon className="w-3.5 h-3.5" /> Tout marquer lu
                            </button>
                        )}
                    </div>

                    <div className="overflow-y-auto flex-1">
                        {loading ? (
                            <div className="px-4 py-6 text-center text-xs text-gray-400">Chargement…</div>
                        ) : notifications.length === 0 ? (
                            <div className="px-4 py-8 text-center">
                                <BellIcon className="w-8 h-8 text-gray-300 mx-auto mb-2" />
                                <p className="text-sm text-gray-500">Aucune notification</p>
                            </div>
                        ) : notifications.map(n => {
                            const type = TYPE_ICON[n.data?.type] || { emoji: '🔔', label: 'Notification' };
                            return (
                                <button
                                    key={n.id}
                                    onClick={() => cliquerNotification(n)}
                                    className={`w-full text-left px-4 py-3 border-b border-gray-50 hover:bg-blue-50/40 transition-colors flex items-start gap-3 group ${!n.read_at ? 'bg-blue-50/30' : ''}`}
                                >
                                    <div className="text-xl shrink-0 mt-0.5">{type.emoji}</div>
                                    <div className="flex-1 min-w-0">
                                        <p className={`text-sm leading-snug ${!n.read_at ? 'font-semibold text-gray-900' : 'text-gray-700'}`}>
                                            {n.data?.titre || type.label}
                                        </p>
                                        {n.data?.message && (
                                            <p className="text-xs text-gray-600 mt-0.5 line-clamp-2">{n.data.message}</p>
                                        )}
                                        <p className="text-[11px] text-gray-400 mt-1">
                                            {new Date(n.created_at).toLocaleString('fr-FR')}
                                        </p>
                                    </div>
                                    <span
                                        onClick={(e) => supprimer(e, n)}
                                        className="opacity-0 group-hover:opacity-100 transition-opacity p-1 text-gray-400 hover:text-red-600 cursor-pointer"
                                        title="Supprimer"
                                    >
                                        <XMarkIcon className="w-3.5 h-3.5" />
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}
