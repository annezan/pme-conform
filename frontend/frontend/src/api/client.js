/**
 * Client API Axios — Configuration centralisee.
 *
 * En dev : les requetes /api sont proxyfiees par Vite vers Laravel (port 8000).
 * En prod : API_URL pointe vers le backend deploye.
 */

import axios from 'axios';

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
    withCredentials: true,
});

/**
 * Recupere le cookie CSRF avant la premiere requete POST.
 * Necessaire pour Sanctum en mode SPA.
 */
export async function getCsrfCookie() {
    const baseUrl = import.meta.env.VITE_BACKEND_URL || '';
    await axios.get(`${baseUrl}/sanctum/csrf-cookie`, { withCredentials: true });
}

// Intercepteur : redirige vers /login si 401
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            if (window.location.pathname !== '/login') {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);

export default api;
