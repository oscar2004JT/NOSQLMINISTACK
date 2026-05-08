const { useEffect, useState } = React;

function buildApiUrl(path) {
    const baseUrl = (window.APP_CONFIG.apiBaseUrl || "").replace(/\/$/, "");

    if (baseUrl) {
        return `${baseUrl}${path}`;
    }

    return `/api${path}`;
}

function formatCurrency(value) {
    return new Intl.NumberFormat("es-CO", {
        style: "currency",
        currency: "USD",
        maximumFractionDigits: 0
    }).format(value ?? 0);
}

function StatusBadge({ status }) {
    const tone = status === "Pago exitoso" ? "success" : "warning";

    return (
        <span className={`status ${tone}`}>
            <span className="status-dot"></span>
            {status}
        </span>
    );
}

function ProfileCard({ profile }) {
    return (
        <section className="card">
            <h2 className="card-title">Mi Perfil</h2>
            <div className="profile-box">
                <img className="avatar" src="https://i.pravatar.cc/120?img=47" alt="Perfil" />
                <div>
                    <h3>{profile.nombre}</h3>
                    <div className="email">{profile.email}</div>

                    <div className="label">Direcciones</div>
                    <div className="pill-list">
                        {profile.direcciones.map((direccion) => (
                            <span key={direccion} className="pill">{direccion}</span>
                        ))}
                    </div>

                    <div className="label" style={{ marginTop: "14px" }}>Pagos</div>
                    <div className="pill-list">
                        {profile.pagos.map((pago) => (
                            <span key={pago} className="pill">{pago}</span>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}

function OrdersTable({ orders, onSelectOrder, selectedOrderId }) {
    return (
        <section className="card">
            <h2 className="card-title">Pedidos Recientes</h2>
            <div className="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th>Fecha Creacion</th>
                            <th>Direccion Envio</th>
                        </tr>
                    </thead>
                    <tbody>
                        {orders.map((order) => (
                            <tr
                                key={order.order_id}
                                onClick={() => onSelectOrder(order.order_id)}
                                style={{
                                    cursor: "pointer",
                                    background: order.order_id === selectedOrderId ? "rgba(47, 126, 161, 0.08)" : "transparent"
                                }}
                            >
                                <td><StatusBadge status={order.estado} /></td>
                                <td>{order.fecha}</td>
                                <td>{order.direccion}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function OrderDetail({ order, items }) {
    if (!order) {
        return (
            <section className="card">
                <h2 className="card-title">Detalle del Pedido</h2>
                <div className="empty">Selecciona un pedido para ver el detalle.</div>
            </section>
        );
    }

    return (
        <section className="card">
            <h2 className="card-title">Detalle del Pedido ORD#{order.order_id}</h2>
            <h3 className="label" style={{ marginBottom: "12px" }}>Informacion General</h3>
            <div className="order-summary">
                <div>
                    <div className="label">Pedido</div>
                    <div className="order-code">ORD#{order.order_id}</div>
                    <div style={{ marginTop: "10px" }}>
                        <StatusBadge status={order.estado} />
                    </div>
                </div>
                <div>
                    <div className="label">Fecha</div>
                    <div className="value">{order.fecha}</div>
                    <div className="label" style={{ marginTop: "16px" }}>Total</div>
                    <div className="value">{formatCurrency(order.total)}</div>
                </div>
                <div>
                    <div className="label">Direccion</div>
                    <div className="value">{order.direccion}</div>
                    <div className="label" style={{ marginTop: "16px" }}>Items</div>
                    <div className="value">{items.length}</div>
                </div>
            </div>

            <h3 className="card-title" style={{ fontSize: "1.15rem", marginTop: "0", marginBottom: "12px" }}>
                Items del pedido
            </h3>
            <div className="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((item) => (
                            <tr key={item.item_id}>
                                <td>{item.producto}</td>
                                <td>{item.cantidad}</td>
                                <td>{formatCurrency(item.precio)}</td>
                                <td>{formatCurrency(item.subtotal)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function LoadingScreen() {
    return (
        <div className="loading">
            <div className="message-box">
                <h2>Cargando datos del mercado</h2>
                <p>Espera un momento mientras cargan tus pedidos.</p>
            </div>
        </div>
    );
}

function ErrorScreen({ message }) {
    return (
        <div className="error">
            <div className="message-box">
                <h2>No se pudo cargar la informacion</h2>
                <p>{message}</p>
            </div>
        </div>
    );
}

function App() {
    const { userId, featuredOrderId } = window.APP_CONFIG;
    const [userData, setUserData] = useState(null);
    const [selectedOrderId, setSelectedOrderId] = useState(featuredOrderId);
    const [orderDetail, setOrderDetail] = useState(null);
    const [loadingUser, setLoadingUser] = useState(true);
    const [loadingOrder, setLoadingOrder] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        async function loadUserData() {
            try {
                setLoadingUser(true);
                const response = await fetch(buildApiUrl(`/usuarios/${userId}`));
                if (!response.ok) {
                    throw new Error("La API no devolvio el usuario solicitado.");
                }

                const payload = await response.json();
                setUserData(payload);
            } catch (err) {
                setError(err.message);
            } finally {
                setLoadingUser(false);
            }
        }

        loadUserData();
    }, [userId]);

    useEffect(() => {
        async function loadOrderDetail() {
            try {
                setLoadingOrder(true);
                const response = await fetch(buildApiUrl(`/usuarios/${userId}/pedidos/${selectedOrderId}`));
                if (!response.ok) {
                    throw new Error("La API no devolvio el pedido solicitado.");
                }

                const payload = await response.json();
                setOrderDetail(payload);
            } catch (err) {
                setError(err.message);
            } finally {
                setLoadingOrder(false);
            }
        }

        loadOrderDetail();
    }, [userId, selectedOrderId]);

    if (loadingUser) {
        return <LoadingScreen />;
    }

    if (error) {
        return <ErrorScreen message={error} />;
    }

    return (
        <main className="page-shell">
            <header className="hero">
                <div>
                    <h1>Mi Mercado Global</h1>
                </div>
            </header>

            <div className="breadcrumb">
                Inicio &gt; Usuario &gt; {userData.perfil.nombre} &gt; Pedidos recientes
            </div>

            <div className="grid">
                <div className="column">
                    <ProfileCard profile={userData.perfil} />
                    <OrdersTable
                        orders={userData.pedidos}
                        onSelectOrder={setSelectedOrderId}
                        selectedOrderId={selectedOrderId}
                    />
                </div>

                <div className="column">
                    {loadingOrder ? (
                        <section className="card">
                            <h2 className="card-title">Detalle del Pedido</h2>
                            <p>Cargando detalle...</p>
                        </section>
                    ) : (
                        <OrderDetail order={orderDetail?.pedido} items={orderDetail?.items ?? []} />
                    )}
                </div>
            </div>
        </main>
    );
}

const root = ReactDOM.createRoot(document.getElementById("root"));
root.render(<App />);
