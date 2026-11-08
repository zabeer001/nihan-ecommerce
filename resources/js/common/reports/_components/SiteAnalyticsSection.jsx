import React from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Activity, Clock3, Eye, Globe2, MousePointerClick, UsersRound } from 'lucide-react';
import { formatNumber } from './reportFormatters';

function formatDuration(seconds) {
    const value = Number(seconds || 0);

    if (value < 60) {
        return `${value}s`;
    }

    const minutes = Math.floor(value / 60);
    const remainingSeconds = value % 60;

    return remainingSeconds ? `${minutes}m ${remainingSeconds}s` : `${minutes}m`;
}

function MetricCard({ label, value, icon: Icon, helper }) {
    return (
        <article className="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-base-content/55">{label}</p>
                    <h3 className="mt-2 text-2xl font-black leading-none text-base-content">{value}</h3>
                    {helper ? <p className="mt-2 text-xs text-base-content/55">{helper}</p> : null}
                </div>
                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-secondary/10 text-secondary">
                    <Icon size={18} strokeWidth={2.2} />
                </span>
            </div>
        </article>
    );
}

function SmallTable({ title, rows, valueKey = 'sessions', emptyText = 'No analytics data yet.' }) {
    return (
        <div className="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
            <h3 className="text-base font-black">{title}</h3>
            <div className="mt-3 overflow-x-auto">
                <table className="table table-sm">
                    <tbody>
                        {rows.length === 0 ? (
                            <tr>
                                <td className="text-base-content/55">{emptyText}</td>
                            </tr>
                        ) : (
                            rows.map((row) => (
                                <tr key={`${title}-${row.label || row.path}`}>
                                    <td className="max-w-[18rem] truncate font-medium">{row.label || row.path}</td>
                                    <td className="text-right font-bold">{formatNumber(row[valueKey])}</td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function SiteAnalyticsSection({ analytics, isLoading }) {
    const summary = analytics?.summary || {};
    const daily = Array.isArray(analytics?.daily) ? analytics.daily : [];
    const topPages = Array.isArray(analytics?.top_pages) ? analytics.top_pages : [];
    const sources = Array.isArray(analytics?.sources) ? analytics.sources : [];
    const devices = Array.isArray(analytics?.devices) ? analytics.devices : [];
    const locations = Array.isArray(analytics?.locations) ? analytics.locations : [];
    const browsers = Array.isArray(analytics?.browsers) ? analytics.browsers : [];
    const recentVisitors = Array.isArray(analytics?.recent_visitors) ? analytics.recent_visitors : [];

    return (
        <section className="space-y-4 sm:space-y-6">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p className="text-xs font-bold uppercase tracking-[0.16em] text-secondary">Site analytics</p>
                    <h2 className="text-2xl font-black sm:text-3xl">All Pages Reporting</h2>
                </div>
                <span className="badge badge-outline w-fit">Last {analytics?.range?.days || 30} days</span>
            </div>

            {isLoading ? (
                <div className="rounded-2xl border border-base-300 bg-base-100 p-5 text-sm text-base-content/60">
                    Loading site analytics...
                </div>
            ) : null}

            {!isLoading ? (
                <>
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <MetricCard label="Visitors" value={formatNumber(summary.visitors)} icon={UsersRound} />
                        <MetricCard label="Sessions" value={formatNumber(summary.sessions)} icon={Activity} />
                        <MetricCard label="Page Views" value={formatNumber(summary.page_views)} icon={Eye} />
                        <MetricCard label="Avg Time" value={formatDuration(summary.avg_time_seconds)} icon={Clock3} />
                        <MetricCard label="Bounce Rate" value={`${Number(summary.bounce_rate || 0)}%`} icon={MousePointerClick} />
                    </div>

                    <div className="grid gap-4 xl:grid-cols-[minmax(0,1.5fr)_minmax(22rem,0.8fr)]">
                        <div className="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
                            <div className="mb-4 flex items-center justify-between gap-3">
                                <h3 className="text-base font-black">Daily Visitors and Views</h3>
                                <Globe2 size={18} className="text-base-content/45" />
                            </div>
                            <div className="h-[300px] w-full">
                                <ResponsiveContainer width="100%" height="100%">
                                    <LineChart data={daily}>
                                        <CartesianGrid strokeDasharray="3 3" opacity={0.16} />
                                        <XAxis dataKey="label" tick={{ fontSize: 11 }} minTickGap={18} />
                                        <YAxis tick={{ fontSize: 11 }} />
                                        <Tooltip />
                                        <Line type="monotone" dataKey="visitors" name="Visitors" strokeWidth={3} dot={false} />
                                        <Line type="monotone" dataKey="page_views" name="Page Views" strokeWidth={3} dot={false} />
                                    </LineChart>
                                </ResponsiveContainer>
                            </div>
                        </div>

                        <div className="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
                            <h3 className="text-base font-black">Traffic Sources</h3>
                            <div className="mt-4 h-[300px] w-full">
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart data={sources} layout="vertical" margin={{ left: 18 }}>
                                        <CartesianGrid strokeDasharray="3 3" opacity={0.16} />
                                        <XAxis type="number" tick={{ fontSize: 11 }} />
                                        <YAxis dataKey="label" type="category" tick={{ fontSize: 11 }} width={82} />
                                        <Tooltip />
                                        <Bar dataKey="sessions" name="Sessions" radius={[0, 8, 8, 0]} />
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-4 xl:grid-cols-2">
                        <div className="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
                            <h3 className="text-base font-black">Top Pages</h3>
                            <div className="mt-3 overflow-x-auto">
                                <table className="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Page</th>
                                            <th className="text-right">Views</th>
                                            <th className="text-right">Visitors</th>
                                            <th className="text-right">Avg Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {topPages.length === 0 ? (
                                            <tr>
                                                <td colSpan={4} className="text-base-content/55">No page views recorded yet.</td>
                                            </tr>
                                        ) : (
                                            topPages.map((page) => (
                                                <tr key={page.path}>
                                                    <td className="max-w-[18rem] truncate font-medium">{page.path}</td>
                                                    <td className="text-right">{formatNumber(page.views)}</td>
                                                    <td className="text-right">{formatNumber(page.visitors)}</td>
                                                    <td className="text-right">{formatDuration(page.avg_time_seconds)}</td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <SmallTable title="Devices" rows={devices} />
                            <SmallTable title="Locations" rows={locations} />
                            <SmallTable title="Browsers" rows={browsers} />
                            <SmallTable title="Source Visitors" rows={sources} valueKey="visitors" />
                        </div>
                    </div>

                    <div className="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
                        <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h3 className="text-base font-black">Recent Visitor IPs</h3>
                                <p className="text-sm text-base-content/55">Latest sessions with IP address, country, source, and first page.</p>
                            </div>
                            <span className="badge badge-outline w-fit">{recentVisitors.length} sessions</span>
                        </div>
                        <div className="mt-3 overflow-x-auto">
                            <table className="table table-sm">
                                <thead>
                                    <tr>
                                        <th>IP Address</th>
                                        <th>User ID</th>
                                        <th>Country</th>
                                      
                                        <th>Landing Page</th>
                                        <th className="text-right">Views</th>
                                        <th className="text-right">Time</th>
                                        <th>Last Seen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recentVisitors.length === 0 ? (
                                        <tr>
                                            <td colSpan={7} className="text-base-content/55">No visitor sessions recorded yet.</td>
                                        </tr>
                                    ) : (
                                        recentVisitors.map((visitor, index) => (
                                            <tr key={`${visitor.ip_address}-${visitor.last_seen_at}-${index}`}>
                                                <td className="font-mono text-xs font-semibold">{visitor.ip_address}</td>
                                                <td className="font-mono text-xs font-semibold">
                                                    {visitor.user_id ? visitor.user_id : 'Not registered'}
                                                </td>
                                                <td>{visitor.country}</td>
                                     
                                             
                                                <td className="max-w-[18rem] truncate font-medium">{visitor.landing_page}</td>
                                                <td className="text-right">{formatNumber(visitor.page_views)}</td>
                                                <td className="text-right">{formatDuration(visitor.time_seconds)}</td>
                                                <td className="whitespace-nowrap text-xs text-base-content/65">{visitor.last_seen_at || 'Unknown'}</td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </>
            ) : null}
        </section>
    );
}

export default SiteAnalyticsSection;
