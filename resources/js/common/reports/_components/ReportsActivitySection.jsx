import React from 'react';
import { Activity, Mail } from 'lucide-react';
import {
  ResponsiveContainer,
  CartesianGrid,
  XAxis,
  YAxis,
  Tooltip,
  BarChart,
  Bar,
  PieChart,
  Pie,
  Cell,
  Legend,
} from 'recharts';
import { formatNumber } from './reportFormatters';
import ReportTooltip from './ReportTooltip';

function ReportsActivitySection({ chartData, pieData, topContributors }) {
  return (
    <section className="grid gap-4 sm:gap-6 ">
      <div className="rounded-[2rem] border border-base-300 bg-base-100 p-4 shadow-xl sm:p-5">
        <div className="mb-5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 className="text-xl font-black sm:text-2xl">Editor Activity Graph</h2>
            <p className="text-sm text-base-content/60">
              Compare QA checked, pending QA, and total contribution per editor.
            </p>
          </div>
          <div className="badge badge-outline badge-sm w-fit sm:badge-md">Interactive</div>
        </div>

        <div className="h-[280px] w-full sm:h-[360px]">
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={chartData} barGap={8}>
              <CartesianGrid strokeDasharray="3 3" opacity={0.15} />
              <XAxis dataKey="name" tick={{ fontSize: 11 }} minTickGap={14} />
              <YAxis tick={{ fontSize: 11 }} />
              <Tooltip content={<ReportTooltip />} />
              <Legend iconSize={9} wrapperStyle={{ fontSize: '11px' }} />
              <Bar dataKey="qa_checked" name="QA Checked" radius={[10, 10, 0, 0]} />
              <Bar dataKey="not_qa" name="Pending QA" radius={[10, 10, 0, 0]} />
              <Bar dataKey="total" name="Total" radius={[10, 10, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* <div className="space-y-6">
        <div className="rounded-[2rem] border border-base-300 bg-base-100 p-4 shadow-xl sm:p-5">
          <div className="mb-4">
            <h2 className="text-xl font-black">QA Distribution</h2>
            <p className="text-sm text-base-content/60">
              Fast visual split between reviewed and pending entries.
            </p>
          </div>

          <div className="h-[240px] w-full sm:h-[280px]">
            <ResponsiveContainer width="100%" height="100%">
              <PieChart>
                <Pie
                  data={pieData}
                  cx="50%"
                  cy="50%"
                  outerRadius={90}
                  innerRadius={55}
                  paddingAngle={4}
                  dataKey="value"
                  nameKey="name"
                >
                  <Cell fill="#22c55e" />
                  <Cell fill="#f59e0b" />
                </Pie>
                <Tooltip content={<ReportTooltip />} />
                <Legend iconSize={9} wrapperStyle={{ fontSize: '11px' }} />
              </PieChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="rounded-[2rem] border border-base-300 bg-base-100 p-4 shadow-xl sm:p-5">
          <div className="mb-4 flex items-center gap-3">
            <div className="rounded-2xl bg-success/10 p-3 text-success">
              <Activity size={18} />
            </div>
            <div>
              <h2 className="text-xl font-black">Top Contributors</h2>
              <p className="text-sm text-base-content/60">Highest total work volume</p>
            </div>
          </div>

          <div className="space-y-3">
            {topContributors.length === 0 ? (
              <div className="rounded-2xl bg-base-200 p-4 text-sm text-base-content/65">
                No contributor data found.
              </div>
            ) : (
              topContributors.map((row, index) => (
                <div
                  key={row.id}
                  className="flex flex-col gap-3 rounded-2xl border border-base-300 bg-base-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                  <div className="flex min-w-0 items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-primary/10 font-bold text-primary">
                      {index + 1}
                    </div>
                    <div className="min-w-0">
                      <p className="truncate font-semibold">{row.name}</p>
                      <p className="flex items-center gap-1 truncate text-xs text-base-content/55">
                        <Mail size={12} />
                        {row.email}
                      </p>
                    </div>
                  </div>
                  <div className="shrink-0 text-left sm:text-right">
                    <p className="text-lg font-black">{formatNumber(row.total)}</p>
                    <p className="text-xs text-base-content/55">{row.role}</p>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
      </div> */}
    </section>
  );
}

export default ReportsActivitySection;
