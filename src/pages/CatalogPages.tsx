import { useState } from 'react';
import { useCatalog } from '../context/CatalogContext';
import { Link, useRouter } from '../context/RouterContext';
export function CatalogPages({ detail = false }: { detail?: boolean }) {
  const { products, loading, error, reload } = useCatalog();
  const { params } = useRouter();
  const [search, setSearch] = useState('');
  if (loading) return <p className="p-12 text-center">Loading catalog…</p>;
  if (error) return <div className="p-12 text-center"><p role="alert">Catalog unavailable.</p><button onClick={() => void reload()}>Try again</button></div>;
  if (detail) {
    const product = products.find(p => p.slug === params.gameId);
    if (!product) return <div className="p-12 text-center"><h1>Product not found</h1><Link to="/games">Browse games</Link></div>;
    return <section className="max-w-5xl mx-auto p-6 py-12"><Link to="/games" className="text-brand-gold">← All games</Link><div className="grid md:grid-cols-2 gap-8 mt-6"><img src={product.picture} alt={product.name} className="rounded-2xl w-full max-w-md" /><div className="space-y-5"><span className="text-brand-gold">{product.category}</span><h1 className="text-3xl font-bold">{product.name}</h1><p className="text-gray-300">{product.description}</p><p className="text-2xl text-brand-gold">From ₱{product.minPrice.toLocaleString('en-PH')}</p><section className="space-y-3" aria-label="Available packages">{!!product.packages?.length && <h2 className="font-bold text-xl">Available packages</h2>}<div className="grid sm:grid-cols-2 gap-3">{product.packages?.map(item => <div key={item.id} className="admin-panel"><h3 className="font-bold">{item.name}</h3><p className="text-brand-gold mt-2">PHP {Number(item.price).toLocaleString("en-PH", { minimumFractionDigits: 2 })}</p></div>)}</div></section><p className="text-sm text-gray-400">Online checkout for database products is not available yet. Contact support for product availability.</p><Link to="/contact" className="account-gold-button inline-block">Contact support</Link></div></div></section>;
  }
  return <section className="max-w-7xl mx-auto p-6 py-12"><h1 className="text-3xl font-bold mb-6">Game catalog</h1><input className="dashboard-wallet-input mb-6" aria-label="Search catalog" placeholder="Search games…" value={search} onChange={e => setSearch(e.target.value)} /><div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">{products.filter(p => p.name.toLowerCase().includes(search.toLowerCase())).map(p => <Link key={p.id} to={`/games/${p.slug}`} className="admin-panel"><img src={p.picture} alt="" loading="lazy" className="rounded-xl aspect-square object-cover mb-3" /><h2 className="text-sm font-bold">{p.name}</h2><p className="text-brand-gold text-sm mt-2">₱{p.minPrice.toLocaleString('en-PH')}</p></Link>)}</div></section>;
}
