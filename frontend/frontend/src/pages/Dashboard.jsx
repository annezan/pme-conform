/**
 * Page Dashboard premium — Tableau de bord principal.
 *
 * Aiguille selon les permissions :
 *  - Utilisateur avec view-portefeuille (interne ASC) : DashboardInterne
 *  - Sinon (cote client)                              : DashboardClient
 */

import { useAuth } from '@/contexts/AuthContext';
import DashboardInterne from '@/pages/dashboards/DashboardInterne';
import DashboardClient from '@/pages/dashboards/DashboardClient';
import Loader from '@/components/ui/Loader';

export default function Dashboard() {
    const { user, loading, hasPermission } = useAuth();

    if (loading) return <Loader />;
    if (!user) return null;

    return hasPermission('view-portefeuille')
        ? <DashboardInterne />
        : <DashboardClient />;
}
