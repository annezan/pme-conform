/**
 * Composant TableActions premium — Boutons d'actions pour les lignes de DataTable.
 *
 * Icones rondes avec tooltip natif et micro-interactions au survol.
 */

import { EyeIcon, PencilSquareIcon, TrashIcon, ArrowPathIcon } from '@heroicons/react/24/outline';

const variantStyles = {
    view: 'text-blue-600 bg-blue-50/70 hover:bg-blue-100 ring-blue-200/80 hover:ring-blue-300',
    edit: 'text-amber-600 bg-amber-50/70 hover:bg-amber-100 ring-amber-200/80 hover:ring-amber-300',
    delete: 'text-red-600 bg-red-50/70 hover:bg-red-100 ring-red-200/80 hover:ring-red-300',
    toggle: 'text-emerald-600 bg-emerald-50/70 hover:bg-emerald-100 ring-emerald-200/80 hover:ring-emerald-300',
    default: 'text-gray-600 bg-gray-50/70 hover:bg-gray-100 ring-gray-200/80 hover:ring-gray-300',
};

function ActionButton({ icon: Icon, label, variant = 'default', onClick, disabled }) {
    return (
        <button
            type="button"
            onClick={onClick}
            title={label}
            disabled={disabled}
            aria-label={label}
            className={`w-8 h-8 rounded-lg ring-1 flex items-center justify-center transition-all duration-150 hover:shadow-sm hover:-translate-y-0.5 active:translate-y-0 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0 ${variantStyles[variant]}`}
        >
            <Icon className="w-4 h-4" />
        </button>
    );
}

export function ViewAction({ onClick, label = 'Voir', disabled }) {
    return <ActionButton icon={EyeIcon} label={label} variant="view" onClick={onClick} disabled={disabled} />;
}

export function EditAction({ onClick, label = 'Modifier', disabled }) {
    return <ActionButton icon={PencilSquareIcon} label={label} variant="edit" onClick={onClick} disabled={disabled} />;
}

export function DeleteAction({ onClick, label = 'Supprimer', disabled }) {
    return <ActionButton icon={TrashIcon} label={label} variant="delete" onClick={onClick} disabled={disabled} />;
}

export function ToggleAction({ onClick, label = 'Basculer', active, disabled }) {
    return (
        <button
            type="button"
            onClick={onClick}
            title={label}
            disabled={disabled}
            aria-label={label}
            className={`w-8 h-8 rounded-lg ring-1 flex items-center justify-center transition-all duration-150 hover:shadow-sm hover:-translate-y-0.5 active:translate-y-0 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0 ${
                active
                    ? 'text-amber-600 bg-amber-50/70 hover:bg-amber-100 ring-amber-200/80 hover:ring-amber-300'
                    : 'text-emerald-600 bg-emerald-50/70 hover:bg-emerald-100 ring-emerald-200/80 hover:ring-emerald-300'
            }`}
        >
            <ArrowPathIcon className="w-4 h-4" />
        </button>
    );
}

export default function TableActions({ children, className = '' }) {
    return (
        <div className={`flex items-center justify-end gap-1.5 ${className}`}>
            {children}
        </div>
    );
}
