/**
 * Composant Modal premium reutilisable.
 *
 * Usage minimal :
 *   <Modal open={open} onClose={() => setOpen(false)} title="Nouveau client">
 *     ...contenu...
 *   </Modal>
 *
 * Avec footer :
 *   <Modal
 *     open={...} onClose={...}
 *     title="..." subtitle="..."
 *     icon={PlusIcon}
 *     accent="blue"
 *     size="lg"
 *     footer={<>...boutons...</>}
 *   >
 *     ...
 *   </Modal>
 */

import { useEffect } from 'react';
import { XMarkIcon } from '@heroicons/react/24/outline';

const SIZES = {
    sm: 'max-w-md',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
    '2xl': 'max-w-5xl',
    full: 'max-w-6xl',
};

const ACCENTS = {
    blue: 'from-blue-600 via-blue-700 to-indigo-700',
    indigo: 'from-indigo-600 to-purple-700',
    rose: 'from-rose-600 to-red-700',
    emerald: 'from-emerald-600 to-teal-700',
    amber: 'from-amber-500 to-orange-600',
    slate: 'from-slate-700 to-slate-900',
    cyan: 'from-cyan-600 to-blue-700',
};

export default function Modal({
    open,
    onClose,
    title,
    subtitle,
    icon: Icon,
    accent = 'blue',
    size = 'lg',
    footer,
    children,
    closeOnBackdrop = true,
    closeOnEsc = true,
    className = '',
    bodyClassName = '',
    showHeader = true,
}) {
    // Fermeture par ESC
    useEffect(() => {
        if (!open || !closeOnEsc) return;
        const onKey = (e) => { if (e.key === 'Escape') onClose?.(); };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open, closeOnEsc, onClose]);

    // Lock scroll body
    useEffect(() => {
        if (!open) return;
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => { document.body.style.overflow = prev; };
    }, [open]);

    if (!open) return null;

    const accentCls = ACCENTS[accent] || ACCENTS.blue;
    const sizeCls = SIZES[size] || SIZES.lg;

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center p-4 animate-fadeIn"
            aria-modal="true"
            role="dialog"
        >
            {/* Backdrop */}
            <div
                className="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"
                onClick={closeOnBackdrop ? onClose : undefined}
            />

            {/* Panel */}
            <div
                className={`relative w-full ${sizeCls} max-h-[92vh] flex flex-col bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 overflow-hidden ${className}`}
                onClick={(e) => e.stopPropagation()}
            >
                {showHeader && (title || Icon) && (
                    <div className={`relative px-6 py-5 bg-gradient-to-r ${accentCls} text-white flex items-start justify-between gap-4`}>
                        {/* Decor subtil */}
                        <div className="absolute -top-12 -right-12 w-40 h-40 rounded-full bg-white/10 blur-2xl pointer-events-none" />
                        <div className="absolute -bottom-12 -left-12 w-40 h-40 rounded-full bg-white/10 blur-2xl pointer-events-none" />

                        <div className="relative flex items-start gap-3 min-w-0">
                            {Icon && (
                                <div className="shrink-0 w-10 h-10 rounded-xl bg-white/15 backdrop-blur ring-1 ring-white/20 flex items-center justify-center">
                                    <Icon className="w-5 h-5 text-white" />
                                </div>
                            )}
                            <div className="min-w-0">
                                {title && <h3 className="text-lg font-bold tracking-tight">{title}</h3>}
                                {subtitle && <p className="text-xs text-white/80 mt-0.5">{subtitle}</p>}
                            </div>
                        </div>

                        <button
                            type="button"
                            onClick={onClose}
                            aria-label="Fermer"
                            className="relative shrink-0 p-1.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-colors"
                        >
                            <XMarkIcon className="w-5 h-5" />
                        </button>
                    </div>
                )}

                <div className={`flex-1 overflow-y-auto px-6 py-6 ${bodyClassName}`}>
                    {children}
                </div>

                {footer && (
                    <div className="px-6 py-4 border-t border-gray-100 bg-gray-50/60 flex items-center justify-end gap-2 flex-wrap">
                        {footer}
                    </div>
                )}
            </div>
        </div>
    );
}
