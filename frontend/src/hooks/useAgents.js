/**
 * Hook useAgents — Charge et cache la liste des agents.
 */

import { useState, useEffect } from 'react';
import { getAgents } from '@/api/agents';

export function useAgents() {
    const [agents, setAgents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        getAgents()
            .then(setAgents)
            .catch((err) => setError(err.response?.data?.message || 'Erreur de chargement des agents.'))
            .finally(() => setLoading(false));
    }, []);

    return { agents, loading, error };
}
