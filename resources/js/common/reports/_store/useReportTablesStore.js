import { create } from 'zustand';

export const DEFAULT_REPORT_TABLE = 'distributors';

const createEmptyTableState = () => ({
  rows: [],
  loaded: false,
  page: 1,
  lastLoadedPage: null,
  loading: false,
  error: '',
  totalGroups: 0,
  totalTapes: 0,
  pagination: null,
});

const initialTableState = {
  titles: createEmptyTableState(),
  distributors: createEmptyTableState(),
  years: createEmptyTableState(),
};

const useReportTablesStore = create((set) => ({
  activeTable: DEFAULT_REPORT_TABLE,
  tableState: initialTableState,

  setActiveTable: (activeTable) => set({ activeTable }),

  setTablePage: (tableKey, page) =>
    set((state) => ({
      tableState: {
        ...state.tableState,
        [tableKey]: {
          ...state.tableState[tableKey],
          page: Math.max(1, Number(page || 1)),
        },
      },
    })),

  setTableLoading: (tableKey) =>
    set((state) => ({
      tableState: {
        ...state.tableState,
        [tableKey]: {
          ...state.tableState[tableKey],
          loading: true,
          error: '',
        },
      },
    })),

  setTableLoaded: (tableKey, payload) =>
    set((state) => ({
      tableState: {
        ...state.tableState,
        [tableKey]: {
          rows: payload.rows,
          loaded: true,
          lastLoadedPage: payload.page,
          loading: false,
          error: '',
          page: payload.page,
          totalGroups: payload.totalGroups,
          totalTapes: payload.totalTapes,
          pagination: payload.pagination,
        },
      },
    })),

  setTableError: (tableKey, error) =>
    set((state) => ({
      tableState: {
        ...state.tableState,
        [tableKey]: {
          ...state.tableState[tableKey],
          loading: false,
          error: error?.message || 'Failed to load report table.',
        },
      },
    })),
}));

export default useReportTablesStore;
