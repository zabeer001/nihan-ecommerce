import React, { useEffect } from 'react';
import {
  fetchDistributorCounts,
  fetchTitleCounts,
  fetchYearCounts,
} from '../api/reportApi';
import useReportTablesStore from '../_store/useReportTablesStore';
import DistributorCountsTable from './DistributorCountsTable';
import TitleCountsTable from './TitleCountsTable';
import YearCountsTable from './YearCountsTable';

const TABLE_CONFIG = {
  titles: {
    label: 'Titles',
    fetcher: fetchTitleCounts,
    render: (state, onPageChange) => (
      <TitleCountsTable
        rows={state.rows}
        totalGroups={state.totalGroups}
        totalTapes={state.totalTapes}
        pagination={state.pagination}
        onPageChange={onPageChange}
      />
    ),
  },
  distributors: {
    label: 'Distributors',
    fetcher: fetchDistributorCounts,
    render: (state, onPageChange) => (
      <DistributorCountsTable
        rows={state.rows}
        pagination={state.pagination}
        onPageChange={onPageChange}
      />
    ),
  },
  years: {
    label: 'Years',
    fetcher: fetchYearCounts,
    render: (state, onPageChange) => (
      <YearCountsTable
        rows={state.rows}
        totalGroups={state.totalGroups}
        totalTapes={state.totalTapes}
        pagination={state.pagination}
        onPageChange={onPageChange}
      />
    ),
  },
};

const REPORT_TABLE_PAGE_SIZE = 25;

function ReportsCountTablesSection() {
  const activeTable = useReportTablesStore((state) => state.activeTable);
  const tableState = useReportTablesStore((state) => state.tableState);
  const setActiveTable = useReportTablesStore((state) => state.setActiveTable);
  const setTablePage = useReportTablesStore((state) => state.setTablePage);
  const setTableLoading = useReportTablesStore((state) => state.setTableLoading);
  const setTableLoaded = useReportTablesStore((state) => state.setTableLoaded);
  const setTableError = useReportTablesStore((state) => state.setTableError);
  const activeState = tableState[activeTable];

  useEffect(() => {
    const currentState = activeState;

    if (currentState.loading || currentState.lastLoadedPage === currentState.page) {
      return;
    }

    const loadActiveTable = async () => {
      setTableLoading(activeTable);

      try {
        const payload = await TABLE_CONFIG[activeTable].fetcher({
          page: currentState.page,
          per_page: REPORT_TABLE_PAGE_SIZE,
        });
        const rows = Array.isArray(payload?.data?.rows) ? payload.data.rows : [];
        const pagination = payload?.data?.pagination || null;
        const totalGroups = Number(payload?.data?.total_groups || 0);
        const totalTapes = Number(payload?.data?.total_tapes || 0);

        setTableLoaded(activeTable, {
          rows,
          page: Number(pagination?.current_page || currentState.page),
          totalGroups,
          totalTapes,
          pagination,
        });
      } catch (error) {
        setTableError(activeTable, error);
      }
    };

    loadActiveTable();
  }, [activeState, activeTable, setTableError, setTableLoaded, setTableLoading]);

  const handlePageChange = (nextPage) => {
    setTablePage(activeTable, nextPage);
  };

  return (
    <section className="space-y-4">
      <div className="rounded-[2rem] border border-base-300 bg-base-100 p-4 shadow-xl sm:p-5">
        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h2 className="text-xl font-black sm:text-2xl">Database Reporting</h2>
            <p className="text-sm text-base-content/60">
              Switch between title, distributor, and year counts without loading every heavy dataset at once.
            </p>
          </div>

          <div className="flex flex-wrap gap-2">
            {Object.entries(TABLE_CONFIG).map(([key, config]) => (
              <button
                key={key}
                type="button"
                className={`btn btn-sm rounded-full ${
                  activeTable === key ? 'btn-primary' : 'btn-ghost border border-base-300'
                }`}
                onClick={() => setActiveTable(key)}
              >
                {config.label}
              </button>
            ))}
          </div>
        </div>
      </div>

      {activeState.error ? (
        <div className="alert alert-error rounded-2xl shadow-md">
          <span>{activeState.error}</span>
        </div>
      ) : null}

      {activeState.loading ? (
        <div className="rounded-[2rem] border border-base-300 bg-base-100 p-8 text-center shadow-xl">
          <p className="text-sm text-base-content/65">Loading {TABLE_CONFIG[activeTable].label.toLowerCase()} report...</p>
        </div>
      ) : null}

      {!activeState.loading && !activeState.error
        ? TABLE_CONFIG[activeTable].render(activeState, handlePageChange)
        : null}
    </section>
  );
}

export default ReportsCountTablesSection;
