import { createContext, useContext, useEffect, useState, useCallback, useRef, ReactNode } from 'react';
import { useRouter } from './RouterContext';
import { OfficialProduct } from '../data/officialData';
import { apiRequest } from './AuthContext';
import { assetUrl } from '../utils/assets';
const CatalogContext = createContext<{ products: OfficialProduct[]; loading: boolean; error: string; reload: () => Promise<void> }>({ products: [], loading: true, error: '', reload: async () => {} });
export function CatalogProvider({ children }: { children: ReactNode }) {
  const [products, setProducts] = useState<OfficialProduct[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const { currentPath } = useRouter();
  const requestVersion = useRef(0);
  const reload = useCallback(async () => {
    const version = ++requestVersion.current;
    try {
      const data = await apiRequest('/api/products', { cache: 'no-store' });
      if (version !== requestVersion.current) return;
      const next = data.products.map((p: OfficialProduct) => ({ ...p, picture: p.picture.startsWith('/') ? assetUrl(p.picture) : p.picture }));
      setProducts(previous => JSON.stringify(previous) === JSON.stringify(next) ? previous : next);
      setError('');
    } catch (e) {
      if (version === requestVersion.current) setError(e instanceof Error ? e.message : 'Catalog unavailable');
    } finally {
      if (version === requestVersion.current) setLoading(false);
    }
  }, []);
  useEffect(() => { void reload(); }, [currentPath, reload]);
  useEffect(() => {
    const refresh = () => { if (document.visibilityState === 'visible') void reload(); };
    window.addEventListener('focus', refresh);
    document.addEventListener('visibilitychange', refresh);
    const timer = window.setInterval(refresh, 30000);
    return () => {
      window.removeEventListener('focus', refresh);
      document.removeEventListener('visibilitychange', refresh);
      window.clearInterval(timer);
    };
  }, [reload]);
  return <CatalogContext.Provider value={{ products, loading, error, reload }}>{children}</CatalogContext.Provider>;
}
export const useCatalog = () => useContext(CatalogContext);
