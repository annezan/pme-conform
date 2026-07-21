/**
 * Composant EmptyState premium — Affichage "pas de donnees" elegant.
 *
 * Props :
 * - icon : composant icone
 * - title, description : textes
 * - children : actions (boutons)
 * - accent : 'blue' (default) | 'indigo' | 'rose' | 'emerald' | 'amber' | 'gray'
 * - compact : boolean (padding reduit)
 */

const ACCENTS = {
    blue: { ring: 'ring-blue-100', iconBg: 'from-blue-500/15 to-indigo-500/15', iconColor: 'text-blue-600' },
    indigo: { ring: 'ring-indigo-100', iconBg: 'from-indigo-500/15 to-purple-500/15', iconColor: 'text-indigo-600' },
    rose: { ring: 'ring-rose-100', iconBg: 'from-rose-500/15 to-red-500/15', iconColor: 'text-rose-600' },
    emerald: { ring: 'ring-emerald-100', iconBg: 'from-emerald-500/15 to-teal-500/15', iconColor: 'text-emerald-600' },
    amber: { ring: 'ring-amber-100', iconBg: 'from-amber-500/15 to-orange-500/15', iconColor: 'text-amber-600' },
    gray: { ring: 'ring-gray-100', iconBg: 'from-gray-300/30 to-gray-400/30', iconColor: 'text-gray-500' },
};

export default function EmptyState({
    icon: Icon,
    title,
    description,
    children,
    accent = 'blue',
    compact = false,
    className = '',
}) {
    const a = ACCENTS[accent] || ACCENTS.blue;
    return (
        <div className={`flex flex-col items-center justify-center text-center ${compact ? 'py-8 px-4' : 'py-14 px-6'} ${className}`}>
            {Icon && (
                <div className={`mb-4 inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br ${a.iconBg} ring-4 ${a.ring}`}>
                    <Icon className={`w-8 h-8 ${a.iconColor}`} />
                </div>
            )}
            {title && <h3 className="text-base font-semibold text-gray-900 mb-1">{title}</h3>}
            {description && <p className="text-sm text-gray-500 max-w-md">{description}</p>}
            {children && <div className="mt-5 flex items-center gap-2 flex-wrap justify-center">{children}</div>}
        </div>
    );
}
