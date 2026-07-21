/**
 * Utilitaire SweetAlert2 — Alertes stylisees pour toute l'application.
 */

import Swal from 'sweetalert2';

const baseConfig = {
    customClass: {
        popup: 'rounded-2xl shadow-2xl !p-6',
        title: '!text-lg !font-semibold !text-gray-900',
        htmlContainer: '!text-sm !text-gray-600 !mt-2',
        actions: '!mt-6 !gap-4',
        confirmButton: 'px-6 py-2.5 rounded-xl font-semibold text-sm transition-all duration-200 shadow-sm',
        cancelButton: 'px-6 py-2.5 rounded-xl font-semibold text-sm transition-all duration-200',
    },
    buttonsStyling: false,
};

export const toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    customClass: {
        popup: 'rounded-xl shadow-lg text-sm !py-3 !px-4',
    },
});

export function alertSuccess(message) {
    return toast.fire({
        icon: 'success',
        title: message,
    });
}

export function alertError(message, title = 'Erreur') {
    return Swal.fire({
        ...baseConfig,
        icon: 'error',
        title,
        text: message,
        confirmButtonText: 'Compris',
        customClass: {
            ...baseConfig.customClass,
            confirmButton: baseConfig.customClass.confirmButton + ' bg-red-600 text-white hover:bg-red-700 shadow-red-500/25',
        },
    });
}

export function alertWarning(message, title = 'Attention') {
    return Swal.fire({
        ...baseConfig,
        icon: 'warning',
        title,
        text: message,
        confirmButtonText: 'OK',
        customClass: {
            ...baseConfig.customClass,
            confirmButton: baseConfig.customClass.confirmButton + ' bg-amber-500 text-white hover:bg-amber-600 shadow-amber-500/25',
        },
    });
}

export function alertInfo(message) {
    return toast.fire({
        icon: 'info',
        title: message,
    });
}

export async function confirmDelete(itemName = 'cet élément') {
    const result = await Swal.fire({
        ...baseConfig,
        icon: 'warning',
        title: 'Confirmer la suppression',
        html: `Êtes-vous sûr de vouloir supprimer <strong class="text-gray-900">${itemName}</strong> ?<br><span class="text-red-500 text-xs">Cette action est irréversible.</span>`,
        showCancelButton: true,
        confirmButtonText: 'Oui, supprimer',
        cancelButtonText: 'Annuler',
        customClass: {
            ...baseConfig.customClass,
            confirmButton: baseConfig.customClass.confirmButton + ' bg-red-600 text-white hover:bg-red-700 shadow-red-500/25',
            cancelButton: baseConfig.customClass.cancelButton + ' bg-gray-100 text-gray-700 hover:bg-gray-200 ring-1 ring-gray-300',
        },
        reverseButtons: true,
    });

    return result.isConfirmed;
}

export async function confirmAction(message, title = 'Confirmer') {
    const result = await Swal.fire({
        ...baseConfig,
        icon: 'question',
        title,
        text: message,
        showCancelButton: true,
        confirmButtonText: 'Confirmer',
        cancelButtonText: 'Annuler',
        customClass: {
            ...baseConfig.customClass,
            confirmButton: baseConfig.customClass.confirmButton + ' bg-blue-600 text-white hover:bg-blue-700 shadow-blue-500/25',
            cancelButton: baseConfig.customClass.cancelButton + ' bg-gray-100 text-gray-700 hover:bg-gray-200 ring-1 ring-gray-300',
        },
        reverseButtons: true,
    });

    return result.isConfirmed;
}
