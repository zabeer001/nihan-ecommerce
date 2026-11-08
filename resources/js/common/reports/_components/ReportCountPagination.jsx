import React from 'react';

function ReportCountPagination({ pagination, onPageChange }) {
  const currentPage = Number(pagination?.current_page || 1);
  const lastPage = Number(pagination?.last_page || 1);

  if (lastPage <= 1) {
    return null;
  }

  return (
    <div className="flex flex-col gap-3 border-t border-base-300 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
      <p className="text-sm text-base-content/60">
        Page {currentPage} of {lastPage}
      </p>

      <div className="join">
        <button
          type="button"
          className="btn btn-sm join-item"
          disabled={currentPage <= 1}
          onClick={() => onPageChange(currentPage - 1)}
        >
          Previous
        </button>
        <button type="button" className="btn btn-sm join-item pointer-events-none">
          {currentPage}
        </button>
        <button
          type="button"
          className="btn btn-sm join-item"
          disabled={currentPage >= lastPage}
          onClick={() => onPageChange(currentPage + 1)}
        >
          Next
        </button>
      </div>
    </div>
  );
}

export default ReportCountPagination;
