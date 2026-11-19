import React, { useEffect, useState } from 'react';
import { ResponsiveContainer, LineChart, Line, CartesianGrid, XAxis, YAxis, Tooltip } from 'recharts';

const currency = value => new Intl.NumberFormat('en-BD', { style: 'currency', currency: 'BDT' }).format(value);
import { apiRequest } from '../../../shared/apiClient';
import { usePermissions } from '../../layouts/PermissionsContext';
export default function DashBoardPage() {
 const { can } = usePermissions(); const canView = can('dashboard.view'); const [summary,setSummary]=useState(null),[error,setError]=useState('');
 useEffect(()=>{if(canView)apiRequest('/api/admin/summary',{auth:true}).then(setSummary).catch(e=>setError(e.message));},[canView]);
 return <section className="space-y-6"><h1 className="text-3xl font-bold">Dashboard</h1><p>Welcome to your workspace.</p>{canView&&error&&<p className="alert alert-error">{error}</p>}{canView&&summary&&<><div className="grid gap-4 md:grid-cols-3">{[['Products',summary.products],['Low stock (5 or fewer)',summary.low_stock],['Delivered sales',new Intl.NumberFormat('en-BD',{style:'currency',currency:'BDT'}).format(summary.delivered_sales_cents/100)]].map(([label,value])=><article key={label} className="card bg-base-100 p-6 border border-base-300"><p>{label}</p><strong className="text-3xl mt-2">{value}</strong></article>)}</div><div className="grid gap-3 md:grid-cols-3">{['Pending','Confirmed','Processing','Shipped','Delivered','Cancelled'].map(status=><div className="card bg-base-100 p-4" key={status}>{status}: {summary.orders[status]||0}</div>)}</div><div className="grid gap-6 xl:grid-cols-2">
  <ActivityChart title="Sales · last six months" description="Delivered orders only, grouped by delivery month." data={summary.monthly_activity || []} sales />
  <ActivityChart title="Orders · last six months" description="All orders, grouped by the month they were placed." data={summary.monthly_activity || []} />
 </div></>}</section>;
}

function ActivityChart({ title, description, data, sales = false }) {
 const points = data.map(month => ({ ...month, sales: month.sales_cents / 100 }));
 const key = sales ? 'sales' : 'orders';
 return <article className="card bg-base-100 border border-base-300 p-4 sm:p-6 min-w-0">
  <h2 className="text-xl font-semibold">{title}</h2>
  <p className="text-sm opacity-70 mt-1 mb-5">{description}</p>
  <div className="h-72 w-full" role="img" aria-label={`${title}. ${points.map(point => `${point.month}: ${sales ? currency(point.sales) : point.orders + ' orders'}`).join('; ')}`}>
   <ResponsiveContainer width="100%" height="100%">
    <LineChart data={points} margin={{ top: 10, right: 16, bottom: 10, left: 8 }} accessibilityLayer>
     <CartesianGrid strokeDasharray="3 3" stroke="currentColor" opacity={0.12} />
     <XAxis dataKey="month" tick={{ fill: 'currentColor', fontSize: 12 }} tickLine={false} axisLine={false} minTickGap={16} />
     <YAxis allowDecimals={false} domain={[0, 'auto']} width={sales ? 78 : 40} tick={{ fill: 'currentColor', fontSize: 12 }} tickLine={false} axisLine={false}
      tickFormatter={value => sales ? `৳${new Intl.NumberFormat('en', { notation: 'compact' }).format(value)}` : value} />
     <Tooltip formatter={value => [sales ? currency(value) : value, sales ? 'Delivered sales' : 'Orders']}
      contentStyle={{ background: 'var(--color-base-100)', color: 'var(--color-base-content)', borderRadius: 12, border: '1px solid var(--color-base-300)' }} />
     <Line type="linear" dataKey={key} stroke={sales ? '#059669' : '#6366f1'} strokeWidth={3} dot={{ r: 4 }} activeDot={{ r: 6 }} isAnimationActive={false} />
    </LineChart>
   </ResponsiveContainer>
  </div>
 </article>;
}
