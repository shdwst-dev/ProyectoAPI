let token = localStorage.getItem('token') || null;
let currentUser = JSON.parse(localStorage.getItem('user') || 'null');
let cart = JSON.parse(localStorage.getItem('cart') || '[]');
let productsList = [];

const statusLabels = {
    PENDING: { text: 'Pendiente', cls: 'badge-pending' },
    PAID: { text: 'Pagado', cls: 'badge-paid' },
    SHIPPED: { text: 'Enviado', cls: 'badge-shipped' },
    CANCELLED: { text: 'Cancelado', cls: 'badge-cancelled' }
};

function formatCurrency(amount) {
    return '$' + Number(amount).toLocaleString('es-MX', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function showAlert(message, type = 'error') {
    const box = document.getElementById('global-alert');
    box.textContent = message;
    box.className = 'alert ' + (type === 'success' ? 'alert-success' : 'alert-error');
    box.style.display = 'block';
    setTimeout(() => {
        box.style.display = 'none';
    }, 4000);
}

function isAuthenticated() {
    return Boolean(token && currentUser);
}

function isAdmin() {
    return isAuthenticated() && Array.isArray(currentUser.roles) && currentUser.roles.includes('ROLE_ADMIN');
}

function updateNav() {
    const navbar = document.getElementById('main-navbar');
    const navAdmin = document.getElementById('nav-admin');
    const userName = document.getElementById('current-user-name');

    if (isAuthenticated()) {
        navbar.style.display = 'block';
        userName.textContent = currentUser.name;
        navAdmin.style.display = isAdmin() ? 'inline-block' : 'none';
    } else {
        navbar.style.display = 'none';
    }

    const totalItems = cart.reduce((acc, item) => acc + item.quantity, 0);
    document.getElementById('cart-count').textContent = totalItems;
}

function showView(viewName) {
    // Bloquear cualquier vista interna si no ha iniciado sesión
    if (!isAuthenticated() && viewName !== 'login' && viewName !== 'register') {
        viewName = 'login';
    }

    // Si ya inició sesión e intenta ir a login/register, enviarlo al catálogo
    if (isAuthenticated() && (viewName === 'login' || viewName === 'register')) {
        viewName = 'catalog';
    }

    if (viewName === 'admin' && !isAdmin()) {
        viewName = 'catalog';
    }

    document.querySelectorAll('.view-section').forEach(sec => sec.classList.remove('active'));
    const target = document.getElementById('view-' + viewName);
    if (target) {
        target.classList.add('active');
    }

    ['catalog', 'cart', 'orders', 'admin'].forEach(nav => {
        const btn = document.getElementById('nav-' + nav);
        if (btn) {
            btn.classList.toggle('active', nav === viewName);
        }
    });

    if (viewName === 'catalog') loadCatalog();
    if (viewName === 'cart') renderCart();
    if (viewName === 'orders') loadMyOrders();
    if (viewName === 'admin') {
        loadCatalog().then(() => renderAdminProducts());
    }
}

async function apiFetch(path, options = {}) {
    const headers = {
        'Accept': 'application/json',
        ...(options.headers || {})
    };

    if (options.body && typeof options.body === 'object') {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(options.body);
    }

    if (token) {
        headers['Authorization'] = 'Bearer ' + token;
    }

    const response = await fetch(path, { ...options, headers });
    const data = await response.json().catch(() => ({}));

    if (response.status === 401 && token) {
        logout();
    }

    return { ok: response.ok, status: response.status, data };
}

/* ================= CATÁLOGO ================= */
async function loadCatalog() {
    if (!isAuthenticated()) return;

    const search = document.getElementById('search-input').value.trim();
    const category = document.getElementById('category-select').value;
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (category) params.set('category', category);

    const query = params.toString() ? '?' + params.toString() : '';
    const res = await apiFetch('/api/products' + query);
    const grid = document.getElementById('products-grid');

    if (!res.ok || !res.data.products) {
        grid.innerHTML = '<p>No se pudieron cargar los productos.</p>';
        return;
    }

    productsList = res.data.products;

    if (productsList.length === 0) {
        grid.innerHTML = '<p style="color: #6c757d;">No se encontraron productos con esos filtros.</p>';
        return;
    }

    grid.innerHTML = productsList.map(p => `
        <div class="product-card">
            <div>
                <div class="product-category">${escapeHtml(p.category)}</div>
                <h3 class="product-title">${escapeHtml(p.name)}</h3>
                <p class="product-desc">${escapeHtml(p.description)}</p>
            </div>
            <div class="product-footer">
                <div>
                    <span class="product-price">${formatCurrency(p.price)}</span>
                    <span class="product-stock">${p.stock > 0 ? p.stock + ' disponibles' : 'Agotado'}</span>
                </div>
                <button class="btn btn-primary btn-sm" ${p.stock <= 0 ? 'disabled' : ''} onclick="addToCart(${p.id})">
                    Agregar
                </button>
            </div>
        </div>
    `).join('');
}

function resetFilters() {
    document.getElementById('search-input').value = '';
    document.getElementById('category-select').value = '';
    loadCatalog();
}

/* ================= CARRITO ================= */
function saveCart() {
    localStorage.setItem('cart', JSON.stringify(cart));
    updateNav();
}

function addToCart(productId) {
    const product = productsList.find(p => p.id === productId);
    if (!product) return;

    const existing = cart.find(item => item.productId === productId);
    if (existing) {
        if (existing.quantity >= product.stock) {
            showAlert('No hay más unidades disponibles en stock.');
            return;
        }
        existing.quantity += 1;
    } else {
        cart.push({
            productId: product.id,
            name: product.name,
            price: product.price,
            stock: product.stock,
            quantity: 1
        });
    }

    saveCart();
    showAlert('Producto agregado al carrito.', 'success');
}

function changeCartQty(productId, delta) {
    const item = cart.find(i => i.productId === productId);
    if (!item) return;

    const newQty = item.quantity + delta;
    if (newQty <= 0) {
        cart = cart.filter(i => i.productId !== productId);
    } else if (newQty <= item.stock) {
        item.quantity = newQty;
    }
    saveCart();
    renderCart();
}

function removeFromCart(productId) {
    cart = cart.filter(i => i.productId !== productId);
    saveCart();
    renderCart();
}

function clearCart() {
    cart = [];
    saveCart();
    renderCart();
}

function renderCart() {
    const emptyEl = document.getElementById('cart-empty');
    const contentEl = document.getElementById('cart-content');
    const tbody = document.getElementById('cart-tbody');
    const totalEl = document.getElementById('cart-total');

    if (cart.length === 0) {
        emptyEl.style.display = 'block';
        contentEl.style.display = 'none';
        return;
    }

    emptyEl.style.display = 'none';
    contentEl.style.display = 'block';

    let total = 0;
    tbody.innerHTML = cart.map(item => {
        const subtotal = item.price * item.quantity;
        total += subtotal;
        return `
            <tr>
                <td>${escapeHtml(item.name)}</td>
                <td>${formatCurrency(item.price)}</td>
                <td>
                    <button class="qty-btn" onclick="changeCartQty(${item.productId}, -1)">-</button>
                    <span style="margin: 0 8px;">${item.quantity}</span>
                    <button class="qty-btn" onclick="changeCartQty(${item.productId}, 1)">+</button>
                </td>
                <td><strong>${formatCurrency(subtotal)}</strong></td>
                <td>
                    <button class="btn btn-secondary btn-sm" onclick="removeFromCart(${item.productId})">Quitar</button>
                </td>
            </tr>
        `;
    }).join('');

    totalEl.textContent = formatCurrency(total);
}

async function checkout() {
    const shippingAddress = document.getElementById('shipping-address').value.trim();
    if (!shippingAddress) {
        showAlert('Ingresa una dirección de envío.');
        return;
    }

    const items = cart.map(i => ({ productId: i.productId, quantity: i.quantity }));
    const res = await apiFetch('/api/orders', {
        method: 'POST',
        body: { shippingAddress, items }
    });

    if (!res.ok) {
        showAlert(res.data.error || 'No se pudo procesar el pedido.');
        return;
    }

    clearCart();
    document.getElementById('shipping-address').value = '';
    showAlert('Tu pedido se ha registrado correctamente.', 'success');
    showView('orders');
}

/* ================= MIS PEDIDOS ================= */
async function loadMyOrders() {
    const container = document.getElementById('orders-container');
    const res = await apiFetch('/api/orders');

    if (!res.ok || !res.data.orders) {
        container.innerHTML = '<div class="card">No se pudieron cargar tus pedidos.</div>';
        return;
    }

    if (res.data.orders.length === 0) {
        container.innerHTML = '<div class="card" style="color: #6c757d;">Aún no has realizado ningún pedido.</div>';
        return;
    }

    container.innerHTML = res.data.orders.map(order => {
        const st = statusLabels[order.status] || { text: order.status, cls: 'badge-pending' };
        const date = new Date(order.createdAt).toLocaleDateString('es-MX', {
            year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
        });

        return `
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <div>
                        <strong>Pedido #${order.id}</strong>
                        <span style="color: #6c757d; font-size: 0.85rem; margin-left: 10px;">${date}</span>
                    </div>
                    <span class="badge ${st.cls}">${st.text}</span>
                </div>
                <p style="font-size: 0.88rem; color: #495057; margin-bottom: 12px;">
                    <strong>Dirección de envío:</strong> ${escapeHtml(order.shippingAddress)}
                </p>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio unitario</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${order.items.map(i => `
                            <tr>
                                <td>${escapeHtml(i.productName)}</td>
                                <td>${i.quantity}</td>
                                <td>${formatCurrency(i.unitPrice)}</td>
                                <td>${formatCurrency(i.subtotal)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
                <div style="text-align: right; margin-top: 12px; font-size: 1.05rem;">
                    <strong>Total: ${formatCurrency(order.total)}</strong>
                </div>
            </div>
        `;
    }).join('');
}

/* ================= AUTENTICACIÓN ================= */
async function handleLogin(e) {
    e.preventDefault();
    const email = document.getElementById('login-email').value.trim();
    const password = document.getElementById('login-password').value;

    const res = await apiFetch('/api/login', {
        method: 'POST',
        body: { email, password }
    });

    if (!res.ok) {
        showAlert(res.data.error || 'Credenciales incorrectas.');
        return;
    }

    token = res.data.token;
    currentUser = res.data.user;
    localStorage.setItem('token', token);
    localStorage.setItem('user', JSON.stringify(currentUser));

    document.getElementById('login-password').value = '';
    updateNav();
    showView('catalog');
}

async function handleRegister(e) {
    e.preventDefault();
    const name = document.getElementById('reg-name').value.trim();
    const email = document.getElementById('reg-email').value.trim();
    const password = document.getElementById('reg-password').value;

    const res = await apiFetch('/api/register', {
        method: 'POST',
        body: { name, email, password }
    });

    if (!res.ok) {
        showAlert(res.data.error || 'No se pudo completar el registro.');
        return;
    }

    token = res.data.token;
    currentUser = res.data.user;
    localStorage.setItem('token', token);
    localStorage.setItem('user', JSON.stringify(currentUser));

    document.getElementById('reg-name').value = '';
    document.getElementById('reg-email').value = '';
    document.getElementById('reg-password').value = '';

    updateNav();
    showAlert('Cuenta creada correctamente.', 'success');
    showView('catalog');
}

function logout() {
    token = null;
    currentUser = null;
    cart = [];
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    localStorage.removeItem('cart');
    localStorage.removeItem('tienda_jwt');
    localStorage.removeItem('tienda_user');
    updateNav();
    showView('login');
}

/* ================= ADMINISTRACIÓN ================= */
function showAdminSection(section) {
    ['products', 'orders', 'users'].forEach(sec => {
        document.getElementById('admin-sec-' + sec).style.display = sec === section ? 'block' : 'none';
        document.getElementById('admin-tab-' + sec).classList.toggle('active', sec === section);
    });

    if (section === 'products') {
        loadCatalog().then(() => renderAdminProducts());
    } else if (section === 'orders') {
        loadAdminOrders();
    } else if (section === 'users') {
        loadAdminUsers();
    }
}

function renderAdminProducts() {
    const tbody = document.getElementById('admin-products-tbody');
    tbody.innerHTML = productsList.map(p => `
        <tr>
            <td>${p.id}</td>
            <td>${escapeHtml(p.name)}</td>
            <td>${escapeHtml(p.category)}</td>
            <td>${formatCurrency(p.price)}</td>
            <td>${p.stock}</td>
            <td>
                <button class="btn btn-secondary btn-sm" onclick="editProduct(${p.id})">Editar</button>
                <button class="btn btn-danger btn-sm" onclick="deleteProduct(${p.id})">Eliminar</button>
            </td>
        </tr>
    `).join('');
}

function editProduct(id) {
    const p = productsList.find(item => item.id === id);
    if (!p) return;

    document.getElementById('product-form-title').textContent = 'Editar producto #' + p.id;
    document.getElementById('prod-id').value = p.id;
    document.getElementById('prod-name').value = p.name;
    document.getElementById('prod-category').value = p.category;
    document.getElementById('prod-price').value = p.price;
    document.getElementById('prod-stock').value = p.stock;
    document.getElementById('prod-description').value = p.description;
    document.getElementById('prod-cancel-btn').style.display = 'inline-block';
}

function resetProductForm() {
    document.getElementById('product-form-title').textContent = 'Agregar producto';
    document.getElementById('prod-id').value = '';
    document.getElementById('prod-name').value = '';
    document.getElementById('prod-category').value = '';
    document.getElementById('prod-price').value = '';
    document.getElementById('prod-stock').value = '';
    document.getElementById('prod-description').value = '';
    document.getElementById('prod-cancel-btn').style.display = 'none';
}

async function saveProduct(e) {
    e.preventDefault();
    const id = document.getElementById('prod-id').value;
    const payload = {
        name: document.getElementById('prod-name').value.trim(),
        category: document.getElementById('prod-category').value.trim(),
        price: parseFloat(document.getElementById('prod-price').value),
        stock: parseInt(document.getElementById('prod-stock').value, 10),
        description: document.getElementById('prod-description').value.trim()
    };

    const method = id ? 'PUT' : 'POST';
    const url = id ? `/api/products/${id}` : '/api/products';

    const res = await apiFetch(url, { method, body: payload });
    if (!res.ok) {
        showAlert(res.data.error || 'No se pudo guardar el producto.');
        return;
    }

    resetProductForm();
    showAlert('Producto guardado correctamente.', 'success');
    await loadCatalog();
    renderAdminProducts();
}

async function deleteProduct(id) {
    if (!confirm('¿Seguro que deseas eliminar este producto?')) return;

    const res = await apiFetch(`/api/products/${id}`, { method: 'DELETE' });
    if (!res.ok) {
        showAlert(res.data.error || 'Error al eliminar producto.');
        return;
    }

    showAlert('Producto eliminado.', 'success');
    await loadCatalog();
    renderAdminProducts();
}

async function loadAdminOrders() {
    const tbody = document.getElementById('admin-orders-tbody');
    const res = await apiFetch('/api/orders');

    if (!res.ok || !res.data.orders) {
        tbody.innerHTML = '<tr><td colspan="6">Error al cargar pedidos.</td></tr>';
        return;
    }

    tbody.innerHTML = res.data.orders.map(o => `
        <tr>
            <td>#${o.id}</td>
            <td>${escapeHtml(o.customer.name)}<br><small style="color:#6c757d;">${escapeHtml(o.customer.email)}</small></td>
            <td>${escapeHtml(o.shippingAddress)}</td>
            <td>${o.items.map(i => `${i.quantity}x ${escapeHtml(i.productName)}`).join('<br>')}</td>
            <td><strong>${formatCurrency(o.total)}</strong></td>
            <td>
                <select class="form-select" style="font-size: 0.82rem; padding: 4px 8px;" onchange="changeOrderStatus(${o.id}, this.value)">
                    <option value="PENDING" ${o.status === 'PENDING' ? 'selected' : ''}>Pendiente</option>
                    <option value="PAID" ${o.status === 'PAID' ? 'selected' : ''}>Pagado</option>
                    <option value="SHIPPED" ${o.status === 'SHIPPED' ? 'selected' : ''}>Enviado</option>
                    <option value="CANCELLED" ${o.status === 'CANCELLED' ? 'selected' : ''}>Cancelado</option>
                </select>
            </td>
        </tr>
    `).join('');
}

async function changeOrderStatus(orderId, status) {
    const res = await apiFetch(`/api/orders/${orderId}/status`, {
        method: 'PATCH',
        body: { status }
    });

    if (res.ok) {
        showAlert('Estado del pedido actualizado.', 'success');
    } else {
        showAlert(res.data.error || 'No se pudo actualizar el estado.');
    }
}

async function loadAdminUsers() {
    const tbody = document.getElementById('admin-users-tbody');
    const res = await apiFetch('/api/users');

    if (!res.ok || !res.data.users) {
        tbody.innerHTML = '<tr><td colspan="5">Error al cargar usuarios.</td></tr>';
        return;
    }

    tbody.innerHTML = res.data.users.map(u => `
        <tr>
            <td>${u.id}</td>
            <td>${escapeHtml(u.name)}</td>
            <td>${escapeHtml(u.email)}</td>
            <td>${u.roles.includes('ROLE_ADMIN') ? 'Administrador' : 'Cliente'}</td>
            <td>${new Date(u.createdAt).toLocaleDateString('es-MX')}</td>
        </tr>
    `).join('');
}

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// Inicialización: si no hay sesión activa, mostrar únicamente Login
updateNav();
showView(isAuthenticated() ? 'catalog' : 'login');
