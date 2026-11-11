import React, { useEffect, useState } from 'react';
import { apiRequest } from '../../../shared/apiClient';
import { usePermissions } from '../../layouts/PermissionsContext';
export default function DashBoardPage() {
 const { can } = usePermissions(); const canView = can('dashboard.view'); const [summary,setSummary]=useState(null),[error,setError]=useState('');
 useEffect(()=>{if(canView)apiRequest('/api/admin/summary',{auth:true}).then(setSummary).catch(e=>setError(e.message));},[canView]);
 return <section className="space-y-6"><h1 className="text-3xl font-bold">Dashboard</h1><p>Welcome to your workspace.</p>{canView&&error&&<p className="alert alert-error">{error}</p>}{canView&&summary&&<><div className="grid gap-4 md:grid-cols-3">{[['Products',summary.products],['Low stock (5 or fewer)',summary.low_stock],['Delivered sales',new Intl.NumberFormat('en-BD',{style:'currency',currency:'BDT'}).format(summary.delivered_sales_cents/100)]].map(([label,value])=><article key={label} className="card bg-base-100 p-6 border border-base-300"><p>{label}</p><strong className="text-3xl mt-2">{value}</strong></article>)}</div><div className="grid gap-3 md:grid-cols-3">{['Pending','Confirmed','Processing','Shipped','Delivered','Cancelled'].map(status=><div className="card bg-base-100 p-4" key={status}>{status}: {summary.orders[status]||0}</div>)}</div></>}</section>;
}
