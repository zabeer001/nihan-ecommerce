import { apiRequest } from '../../../../shared/apiClient';
export default function StockHistory({
  history,
  run,
  setHistory
}) {
  return <div className="card bg-base-100 p-5">
    <h2 className="text-xl">Stock history</h2>
    {history.data.map(h => <p key={h.id}>{h.created_at} · {h.quantity > 0 ? '+' : ''}
{h.quantity} → {h.balance} · {h.reason} · Staff #{h.user_id}</p>)}
    <div className="flex gap-2 mt-3">
      <button className="btn btn-sm" disabled={!history.prev_page_url} onClick={() => run(async () => setHistory(await apiRequest(history.prev_page_url, {
        auth: true
      })))}>Previous</button>
      <button className="btn btn-sm" disabled={!history.next_page_url} onClick={() => run(async () => setHistory(await apiRequest(history.next_page_url, {
        auth: true
      })))}>Next</button>
    </div>
  </div>;
}
