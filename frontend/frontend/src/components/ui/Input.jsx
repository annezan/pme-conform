/**
 * Composants de formulaire premium — Input, Select, Textarea.
 *
 * Supporte :
 * - label + helper + error
 * - icon (composant) en prefixe
 * - suffix (slot a droite, e.g. unite ou bouton)
 */

const baseField = `w-full px-3.5 py-2.5 rounded-lg border text-sm bg-white/80 backdrop-blur-sm transition-all duration-150 outline-none placeholder:text-gray-400 placeholder:font-normal font-medium text-gray-900`;
const focusClean = 'border-gray-200 hover:border-gray-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:bg-white';
const focusError = 'border-red-300 focus:border-red-500 focus:ring-4 focus:ring-red-500/10';

function Label({ label, required }) {
    if (!label) return null;
    return (
        <label className="block text-sm font-semibold text-gray-700 mb-1.5">
            {label}{required && <span className="text-red-500 ml-0.5">*</span>}
        </label>
    );
}

function Helper({ error, helper }) {
    if (error) return <p className="mt-1.5 text-xs text-red-600 font-medium">{error}</p>;
    if (helper) return <p className="mt-1.5 text-xs text-gray-500">{helper}</p>;
    return null;
}

export function Input({ label, error, helper, icon: Icon, suffix, required, className = '', ...props }) {
    return (
        <div className={className}>
            <Label label={label} required={required} />
            <div className="relative">
                {Icon && (
                    <Icon className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                )}
                <input
                    className={`${baseField} ${error ? focusError : focusClean} ${Icon ? 'pl-9' : ''} ${suffix ? 'pr-10' : ''}`}
                    {...props}
                />
                {suffix && (
                    <div className="absolute right-2 top-1/2 -translate-y-1/2 flex items-center">{suffix}</div>
                )}
            </div>
            <Helper error={error} helper={helper} />
        </div>
    );
}

export function Select({ label, children, error, helper, required, className = '', ...props }) {
    return (
        <div className={className}>
            <Label label={label} required={required} />
            <div className="relative">
                <select
                    className={`${baseField} appearance-none pr-9 ${error ? focusError : focusClean}`}
                    {...props}
                >
                    {children}
                </select>
                <svg className="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.084l3.71-3.853a.75.75 0 111.08 1.04l-4.24 4.4a.75.75 0 01-1.08 0l-4.24-4.4a.75.75 0 01.02-1.06z" clipRule="evenodd" />
                </svg>
            </div>
            <Helper error={error} helper={helper} />
        </div>
    );
}

export function Textarea({ label, error, helper, required, className = '', rows = 4, ...props }) {
    return (
        <div className={className}>
            <Label label={label} required={required} />
            <textarea
                rows={rows}
                className={`${baseField} resize-y ${error ? focusError : focusClean}`}
                {...props}
            />
            <Helper error={error} helper={helper} />
        </div>
    );
}
