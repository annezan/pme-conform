/**
 * Composant PageHeader premium.
 *
 * Props :
 * - title    : titre principal (string)
 * - subtitle : sous-titre / description
 * - eyebrow  : petit label en majuscules au-dessus du titre (e.g. "Methode 3")
 * - icon     : icon component a afficher dans un cercle a gauche du titre
 * - accent   : 'blue' (default) | 'indigo' | 'rose' | 'emerald' | 'amber'
 * - breadcrumb : tableau [{label, to?}]
 * - children : actions a droite (boutons, badges)
 */

import { Link } from 'react-router-dom';
import { ChevronRightIcon } from '@heroicons/react/24/outline';

const accents = {
    blue: 'from-blue-500 to-indigo-600 ring-blue-500/20',
    indigo: 'from-indigo-500 to-purple-600 ring-indigo-500/20',
    rose: 'from-rose-500 to-red-600 ring-rose-500/20',
    emerald: 'from-emerald-500 to-teal-600 ring-emerald-500/20',
    amber: 'from-amber-500 to-orange-600 ring-amber-500/20',
    cyan: 'from-cyan-500 to-blue-600 ring-cyan-500/20',
};

export default function PageHeader({
    title,
    subtitle,
    eyebrow,
    icon: Icon,
    accent = 'blue',
    breadcrumb,
    children,
    className = '',
}) {
    return (
        <div className={`mb-7 ${className}`}>
            {Array.isArray(breadcrumb) && breadcrumb.length > 0 && (
                <nav className="flex items-center text-xs text-gray-500 mb-3" aria-label="Breadcrumb">
                    {breadcrumb.map((item, idx) => {
                        const isLast = idx === breadcrumb.length - 1;
                        return (
                            <span key={`${item.label}-${idx}`} className="flex items-center">
                                {item.to && !isLast ? (
                                    <Link to={item.to} className="hover:text-blue-700 transition-colors font-medium">{item.label}</Link>
                                ) : (
                                    <span className={isLast ? 'text-gray-700 font-semibold' : 'font-medium'}>{item.label}</span>
                                )}
                                {!isLast && <ChevronRightIcon className="w-3.5 h-3.5 text-gray-400 mx-1.5" />}
                            </span>
                        );
                    })}
                </nav>
            )}

            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div className="flex items-start gap-4 min-w-0">
                    {Icon && (
                        <div className={`shrink-0 w-12 h-12 rounded-2xl bg-gradient-to-br ${accents[accent] || accents.blue} ring-4 flex items-center justify-center shadow-lg shadow-blue-500/10`}>
                            <Icon className="w-6 h-6 text-white" />
                        </div>
                    )}
                    <div className="min-w-0">
                        {eyebrow && (
                            <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-blue-700 mb-1">
                                {eyebrow}
                            </p>
                        )}
                        <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight leading-tight">
                            {title}
                        </h1>
                        {subtitle && (
                            <p className="mt-1.5 text-sm text-gray-500 max-w-2xl">{subtitle}</p>
                        )}
                    </div>
                </div>
                {children && (
                    <div className="flex items-center gap-2 flex-wrap shrink-0">{children}</div>
                )}
            </div>
        </div>
    );
}
