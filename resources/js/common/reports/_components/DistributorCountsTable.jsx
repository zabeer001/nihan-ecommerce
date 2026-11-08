import React from 'react';
import ReportCountPagination from './ReportCountPagination';
import { formatNumber } from './reportFormatters';

function DistributorCountsTable({ rows, pagination, onPageChange }) {
  return (
    <section className="rounded-[2rem] border border-base-300 bg-base-100 shadow-xl">
      <div className="border-b border-base-300 px-4 py-4 sm:px-6 sm:py-5">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h2 className="text-xl font-black sm:text-2xl">Distributor Counts</h2>
            <p className="text-sm text-base-content/60">
              Unique distributors and how many tapes belong to each distributor.
            </p>
          </div>

        </div>
      </div>

      <div className="hidden overflow-x-auto lg:block">
        <table className="table table-zebra">
          <thead>
            <tr className="text-xs uppercase tracking-[0.16em] text-base-content/55">
              <th>Distributor</th>
              <th className="text-right">Tape Count</th>
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td colSpan={2} className="py-10 text-center text-base-content/70">
                  No distributor rows found.
                </td>
              </tr>
            ) : (
              rows.map((row) => (
                <tr key={row.distributor} className="hover">
                  <td className="font-semibold">{row.distributor}</td>
                  <td className="text-right text-base font-black">{formatNumber(row.count)}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <div className="grid gap-3 p-3 sm:gap-4 sm:p-4 lg:hidden">
        {rows.length === 0 ? (
          <div className="rounded-3xl bg-base-200 p-8 text-center text-base-content/70">
            No distributor rows found.
          </div>
        ) : (
          rows.map((row) => (
            <article key={row.distributor} className="rounded-3xl border border-base-300 bg-base-100 p-4 shadow-sm sm:p-5">
              <h3 className="font-bold">{row.distributor}</h3>
              <div className="mt-4 rounded-2xl bg-base-200 p-3 text-center">
                <p className="text-xs uppercase tracking-wider text-base-content/50">Tape Count</p>
                <p className="mt-1 text-lg font-black">{formatNumber(row.count)}</p>
              </div>
            </article>
          ))
        )}
      </div>

      <ReportCountPagination pagination={pagination} onPageChange={onPageChange} />
    </section>
  );
}

export default DistributorCountsTable;
