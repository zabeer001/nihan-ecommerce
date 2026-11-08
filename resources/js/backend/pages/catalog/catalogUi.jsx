import { apiRequest } from '../../../shared/apiClient';
export const request = async (path, options = {}) => {
  const response = await apiRequest('/api/admin/' + path, {
  ...options,
  auth: true
});
  return response?.status === 'success' ? response.data : response;
};
export const money = n => new Intl.NumberFormat('en-BD', {
  style: 'currency',
  currency: 'BDT'
}).format(n / 100);
export const cents = n => Math.round(Number(n || 0) * 100);
export function Field({
  label,
  children
}) {
  return <label className="form-control flex flex-col gap-1">
    <span className="text-sm font-medium">{label}</span>
    {children}
  </label>;
}
export const input = 'input input-bordered w-full';
