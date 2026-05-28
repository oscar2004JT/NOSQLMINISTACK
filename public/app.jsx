const { useEffect, useMemo, useState } = React;
const CART_STORAGE_KEY = "ecocart.cart";

const categories = ["Electrónica", "Ropa", "Hogar", "Deportes"];

function SearchIcon() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <circle cx="11" cy="11" r="7"></circle>
      <path d="m16.5 16.5 4 4"></path>
    </svg>
  );
}

function CartIcon() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M5 6h15l-2 8H8L6 3H3"></path>
      <circle cx="9" cy="20" r="1.7"></circle>
      <circle cx="17" cy="20" r="1.7"></circle>
    </svg>
  );
}

function UserIcon() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <circle cx="12" cy="8" r="4"></circle>
      <path d="M4 21c1.4-4.2 4.1-6.3 8-6.3s6.6 2.1 8 6.3"></path>
    </svg>
  );
}

function LeafIcon() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M20 4C11.5 4 5.8 8.4 4 16.6c5.8 1 11.4-1.8 14-7.1"></path>
      <path d="M4 20c2.5-6.3 6.8-10 13-11"></path>
    </svg>
  );
}

function formatMoney(value) {
  return `$ ${new Intl.NumberFormat("es-CO", {
    maximumFractionDigits: 0,
  }).format(value || 0)} COP`;
}

function apiUrl(path) {
  const normalizedPath = path.startsWith("/") ? path : `/${path}`;
  const configuredBaseUrl = window.APP_CONFIG?.apiBaseUrl;

  if (configuredBaseUrl) {
    return `${configuredBaseUrl.replace(/\/$/, "")}${normalizedPath}`;
  }

  const marker = "/_user_request_";
  const markerIndex = window.location.pathname.indexOf(marker);
  if (markerIndex !== -1) {
    return `${window.location.pathname.slice(0, markerIndex + marker.length)}${normalizedPath}`;
  }

  return normalizedPath;
}

function appHomeUrl() {
  const marker = "/_user_request_";
  const markerIndex = window.location.pathname.indexOf(marker);
  if (markerIndex !== -1) {
    return `${window.location.pathname.slice(0, markerIndex + marker.length)}/ecommerce`;
  }

  return "/";
}

function loadStoredCart() {
  try {
    const storedCart = JSON.parse(localStorage.getItem(CART_STORAGE_KEY) || "[]");
    return Array.isArray(storedCart)
      ? storedCart.filter((item) => item && item.id && Number.isFinite(item.quantity))
      : [];
  } catch {
    return [];
  }
}

function EcoCartApp() {
  const userId = window.APP_CONFIG?.userId || "123";
  const [products, setProducts] = useState([]);
  const [userProfile, setUserProfile] = useState(null);
  const [query, setQuery] = useState("");
  const [selectedCategory, setSelectedCategory] = useState("Todas");
  const [cartItems, setCartItems] = useState(loadStoredCart);
  const [cartOpen, setCartOpen] = useState(false);
  const [profileOpen, setProfileOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    let mounted = true;

    fetch(apiUrl("/api/productos"))
      .then((response) => {
        if (!response.ok) {
          throw new Error("No se pudieron cargar los productos.");
        }
        return response.json();
      })
      .then((payload) => {
        if (mounted) {
          setProducts(Array.isArray(payload.items) ? payload.items : []);
          setError("");
        }
      })
      .catch((requestError) => {
        if (mounted) {
          setError(requestError.message);
        }
      })
      .finally(() => {
        if (mounted) {
          setLoading(false);
        }
      });

    return () => {
      mounted = false;
    };
  }, []);

  useEffect(() => {
    let mounted = true;

    fetch(apiUrl(`/api/usuarios/${userId}`))
      .then((response) => {
        if (!response.ok) {
          throw new Error("No se pudo cargar el perfil.");
        }
        return response.json();
      })
      .then((payload) => {
        if (mounted) {
          setUserProfile(payload.perfil || null);
        }
      })
      .catch(() => {
        if (mounted) {
          setUserProfile(null);
        }
      });

    return () => {
      mounted = false;
    };
  }, [userId]);

  useEffect(() => {
    localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cartItems));
  }, [cartItems]);

  function addToCart(product) {
    setCartItems((currentItems) => {
      const existingItem = currentItems.find((item) => item.id === product.id);

      if (existingItem) {
        return currentItems.map((item) =>
          item.id === product.id
            ? { ...item, quantity: item.quantity + 1 }
            : item
        );
      }

      return [
        ...currentItems,
        {
          id: product.id,
          name: product.name,
          price: product.price,
          image: product.image,
          quantity: 1,
        },
      ];
    });
  }

  const visibleProducts = useMemo(() => {
    const term = query.trim().toLowerCase();

    return products.filter((product) => {
      const matchesCategory =
        selectedCategory === "Todas" || product.category === selectedCategory;
      const matchesSearch =
        term.length === 0 ||
        product.name.toLowerCase().includes(term) ||
        product.brand.toLowerCase().includes(term);

      return matchesCategory && matchesSearch;
    });
  }, [products, query, selectedCategory]);

  const userName = userProfile?.nombre || "Usuario";
  const cartCount = cartItems.reduce((total, item) => total + item.quantity, 0);
  const cartTotal = cartItems.reduce(
    (total, item) => total + item.price * item.quantity,
    0
  );
  const shippingAddress = userProfile?.direcciones?.[0] || "Sin dirección registrada";

  return (
    <main className="scene">
      <section className="window-shell">
        <header className="main-header">
          <a className="brand" href={appHomeUrl()} aria-label="EcoCart inicio">
            <span className="brand-icon">
              <LeafIcon />
            </span>
            <span>
              <strong>Eco</strong>Cart
            </span>
          </a>

          <label className="search-box">
            <input
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Buscar productos..."
            />
            <SearchIcon />
          </label>

          <nav className="top-nav" aria-label="Navegación principal">
            <a href={appHomeUrl()}>Inicio</a>
            <button onClick={() => setSelectedCategory("Todas")}>Categorías</button>
            <button>Ofertas</button>
            <button>Marcas</button>
          </nav>

          <button
            className="cart-button"
            type="button"
            aria-label={`Carrito: ${cartCount} productos`}
            aria-expanded={cartOpen}
            onClick={() => {
              setCartOpen((open) => !open);
              setProfileOpen(false);
            }}
          >
            <CartIcon />
            <span className="cart-badge">{cartCount}</span>
            <span>Carrito</span>
          </button>

          {cartOpen && (
            <div className="cart-panel" role="dialog" aria-label="Productos en el carrito">
              <h2>Carrito</h2>

              {cartItems.length === 0 ? (
                <p className="cart-empty">Tu carrito esta vacio.</p>
              ) : (
                <>
                  <ul className="cart-list">
                    {cartItems.map((item) => (
                      <li className="cart-item" key={item.id}>
                        <img src={item.image} alt={item.name} />
                        <div>
                          <strong>{item.name}</strong>
                          <span>
                            {item.quantity} x {formatMoney(item.price)}
                          </span>
                        </div>
                        <b>{formatMoney(item.price * item.quantity)}</b>
                      </li>
                    ))}
                  </ul>
                  <div className="cart-total">
                    <span>Total</span>
                    <strong>{formatMoney(cartTotal)}</strong>
                  </div>
                </>
              )}
            </div>
          )}

          <button
            className="profile-button"
            aria-label="Perfil de usuario"
            aria-expanded={profileOpen}
            onClick={() => {
              setProfileOpen((open) => !open);
              setCartOpen(false);
            }}
          >
            <UserIcon />
          </button>

          {profileOpen && (
            <div className="profile-card" role="status">
              <h2>Bienvenida, {userName}</h2>
              <p>
                <strong>Dirección de envío:</strong>
                <br />
                {shippingAddress}
              </p>
              <button>Cerrar sesión</button>
            </div>
          )}
        </header>

        <div className="content-grid">
          <aside className="sidebar">
            <h2>Categorías</h2>
            <button
              className={selectedCategory === "Todas" ? "active" : ""}
              onClick={() => setSelectedCategory("Todas")}
            >
              Todas
            </button>
            {categories.map((category) => (
              <button
                key={category}
                className={selectedCategory === category ? "active" : ""}
                onClick={() => setSelectedCategory(category)}
              >
                {category}
              </button>
            ))}
          </aside>

          <section className="products-section" aria-live="polite">
            <h1>Nuestros Productos</h1>

            {loading && <p className="state-text">Cargando productos...</p>}
            {error && <p className="state-text">{error}</p>}
            {!loading && !error && visibleProducts.length === 0 && (
              <p className="state-text">No hay productos para mostrar.</p>
            )}

            <div className="products-grid">
              {visibleProducts.map((product) => (
                <article className="product-card" key={product.id}>
                  <figure>
                    <img src={product.image} alt={product.name} />
                  </figure>
                  <h2>{product.name}</h2>
                  <p className="price">{formatMoney(product.price)}</p>
                  <p className="stock">Stock: {product.stock}</p>
                  <button type="button" onClick={() => addToCart(product)}>
                    Añadir al Carrito
                  </button>
                </article>
              ))}
            </div>
          </section>
        </div>

        <footer className="footer">
          <nav aria-label="Enlaces de soporte">
            <a href="#">Mis Pedidos</a>
            <a href="#">Soporte</a>
            <a href="#">Política de Privacidad</a>
          </nav>
          <span>© 2024 EcoCart Inc.</span>
        </footer>
      </section>
    </main>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<EcoCartApp />);
