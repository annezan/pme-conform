/**
 * Wrapper DataTable premium — Configuration commune pour react-data-table-component.
 *
 * Apporte :
 * - Styles homogenes (header colore, ligne survol bleute, ombre douce, scrollbar discrete)
 * - Loader & no-data premium
 * - Pagination en francais avec selecteur soft
 */

import DataTableModule from 'react-data-table-component';
import EmptyState from './EmptyState';
import { InboxIcon } from '@heroicons/react/24/outline';

const DataTable = DataTableModule.default || DataTableModule;

const paginationComponentOptions = {
    rowsPerPageText: 'Lignes',
    rangeSeparatorText: 'sur',
    selectAllRowsItem: true,
    selectAllRowsItemText: 'Tout',
};

const customStyles = {
    table: {
        style: {
            backgroundColor: 'transparent',
        },
    },
    headRow: {
        style: {
            background: 'linear-gradient(to bottom, rgb(248 250 252), rgb(241 245 249))',
            borderBottom: '1px solid rgb(226 232 240)',
            minHeight: '46px',
            color: 'rgb(51 65 85)',
            fontWeight: '600',
            fontSize: '11px',
            letterSpacing: '0.05em',
            textTransform: 'uppercase',
        },
    },
    headCells: {
        style: {
            paddingLeft: '20px',
            paddingRight: '20px',
        },
    },
    rows: {
        style: {
            minHeight: '60px',
            fontSize: '13.5px',
            borderBottom: '1px solid rgb(241 245 249) !important',
            transition: 'background-color 150ms ease',
        },
        highlightOnHoverStyle: {
            backgroundColor: 'rgb(239 246 255 / 0.55)',
            borderRadius: '0',
            transitionDuration: '0.15s',
            outline: 'none',
        },
    },
    cells: {
        style: {
            paddingLeft: '20px',
            paddingRight: '20px',
        },
    },
    pagination: {
        style: {
            borderTop: '1px solid rgb(226 232 240)',
            minHeight: '52px',
            color: 'rgb(71 85 105)',
            fontSize: '12.5px',
        },
        pageButtonsStyle: {
            borderRadius: '8px',
            height: '32px',
            width: '32px',
            padding: '6px',
            margin: '0 2px',
            cursor: 'pointer',
            transition: 'background-color 150ms ease, color 150ms ease',
            color: 'rgb(71 85 105)',
            fill: 'rgb(71 85 105)',
            '&:disabled': { color: 'rgb(203 213 225)', fill: 'rgb(203 213 225)', cursor: 'not-allowed' },
            '&:hover:not(:disabled)': { backgroundColor: 'rgb(239 246 255)', color: 'rgb(29 78 216)', fill: 'rgb(29 78 216)' },
        },
    },
};

const loader = (
    <div className="py-16 flex items-center justify-center">
        <div className="relative">
            <div className="w-10 h-10 rounded-full border-[3px] border-gray-200" />
            <div className="absolute top-0 left-0 w-10 h-10 rounded-full border-[3px] border-blue-600 border-t-transparent animate-spin" />
        </div>
    </div>
);

const defaultNoData = (
    <EmptyState
        icon={InboxIcon}
        title="Aucune donnée"
        description="Aucun enregistrement ne correspond à vos critères."
        accent="gray"
    />
);

export default function DataTableWrapper({
    columns,
    data,
    loading,
    noDataComponent,
    paginationPerPage = 10,
    paginationRowsPerPageOptions = [10, 25, 50],
    ...props
}) {
    return (
        <div className="overflow-hidden rounded-2xl">
            <DataTable
                columns={columns}
                data={data}
                progressPending={loading}
                progressComponent={loader}
                noDataComponent={noDataComponent || defaultNoData}
                pagination
                paginationComponentOptions={paginationComponentOptions}
                paginationPerPage={paginationPerPage}
                paginationRowsPerPageOptions={paginationRowsPerPageOptions}
                highlightOnHover
                pointerOnHover
                responsive
                customStyles={customStyles}
                {...props}
            />
        </div>
    );
}
