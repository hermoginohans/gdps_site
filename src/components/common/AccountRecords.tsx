import { useEffect, useState } from 'react';
import { apiRequest } from '../../context/AuthContext';
export function AccountRecords({ wallet = false }: { wallet?: boolean }) {
  const [data, setData] = useState<{orders: {id: number; number: string; status: string; amount_centavos: number; created_at: string}[]; walletEntries: {id: number; description: string; amount_centavos: number; created_at: string}[]; balanceCentavos: number} | null>(null);
  const [error, setError] = useState('');
  useEffect(() => { apiRequest('/api/account').then(setData).catch(e => setError(e.message)); }, []);
  return <section className="dashboard-wallet-card"><h2 className="text-xl font-bold mb-5">{wallet ? 'Balance history' : 'Order history'}</h2>{error ? <p role="alert">{error}</p> : !data ? <p>Loading…</p> : <>{wallet && <p className="text-brand-gold text-2xl mb-5">Balance: ₱{(data.balanceCentavos / 100).toFixed(2)}</p>}{(wallet ? data.walletEntries : data.orders).length === 0 ? <p className="text-gray-400">No {wallet ? 'balance transactions' : 'orders'} yet.</p> : (wallet ? data.walletEntries : data.orders).map(row => <div key={row.id} className="border-b border-brand-cardBorder py-4"><strong>{'number' in row ? row.number : row.description}</strong><p>₱{(row.amount_centavos / 100).toFixed(2)} {'status' in row ? row.status : ''}</p><p className="text-xs text-gray-400">{row.created_at}</p></div>)}</>}</section>;
}
