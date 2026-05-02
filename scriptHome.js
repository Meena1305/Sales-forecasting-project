// ========== GLOBAL FUNCTIONS ==========
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        sidebar.classList.toggle('active');
    }
}

function confirmLogout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = 'logout.php';
    }
    return false;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showNotification(msg, type) {
    const existing = document.querySelector('.notification');
    if (existing) existing.remove();

    const notif = document.createElement('div');
    notif.className = 'notification';
    notif.textContent = msg;
    notif.style.cssText = `position:fixed;top:20px;right:20px;padding:12px 20px;border-radius:8px;color:white;font-weight:500;z-index:10001;animation:slideIn 0.3s ease;background:${type === 'success' ? '#10b981' : '#ef4444'};box-shadow:0 4px 12px rgba(0,0,0,0.15);`;
    document.body.appendChild(notif);

    setTimeout(() => {
        if (notif && notif.remove) notif.remove();
    }, 5000);
}

// ========== CHECK FOR DATA UPDATES ACROSS PAGES ==========
function checkForDataUpdates() {
    // Check if data was just updated in another page
    const wasUpdated = sessionStorage.getItem('data_updated');
    const updateTime = sessionStorage.getItem('update_timestamp');

    if (wasUpdated === 'true' && updateTime) {
        const now = Date.now();
        const timeDiff = now - parseInt(updateTime);

        // If update happened in the last 10 seconds
        if (timeDiff < 10000) {
            console.log('Data was recently updated, reloading...');
            showNotification('New data available, refreshing page...', 'info');

            // Clear the flag
            sessionStorage.removeItem('data_updated');
            sessionStorage.removeItem('update_timestamp');

            // Reload the page after a short delay
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            // Clear old flag
            sessionStorage.removeItem('data_updated');
            sessionStorage.removeItem('update_timestamp');
        }
    }
}

// ========== GLOBAL SEARCH FUNCTIONALITY ==========
const searchInput = document.getElementById('globalSearchInput');
const searchDropdown = document.getElementById('searchResultsDropdown');

// Searchable data
const globalSearchData = {
    products: [],
    customers: [],
    orders: []
};

// Load searchable data from server
function loadSearchData() {
    fetch('get_search_data.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                globalSearchData.products = data.products || [];
                globalSearchData.customers = data.customers || [];
                globalSearchData.orders = data.orders || [];
            }
        })
        .catch(error => console.error('Error loading search data:', error));
}

// Perform search
function performGlobalSearch(query) {
    const lowerQuery = query.toLowerCase().trim();

    if (lowerQuery === '') {
        searchDropdown.style.display = 'none';
        return;
    }

    const results = [];

    // Search products
    globalSearchData.products.forEach(product => {
        if (product.name && product.name.toLowerCase().includes(lowerQuery) ||
            product.sku && product.sku.toLowerCase().includes(lowerQuery)) {
            results.push({
                type: 'Product',
                name: product.name,
                detail: `SKU: ${product.sku} | Stock: ${product.stock}`,
                link: `products.php?search=${encodeURIComponent(product.name)}`
            });
        }
    });

    // Search customers
    globalSearchData.customers.forEach(customer => {
        if (customer.name && customer.name.toLowerCase().includes(lowerQuery) ||
            customer.email && customer.email.toLowerCase().includes(lowerQuery)) {
            results.push({
                type: 'Customer',
                name: customer.name,
                detail: `${customer.email} | ${customer.orders || 0} orders`,
                link: `customers.php?id=${customer.id}`
            });
        }
    });

    // Search orders
    globalSearchData.orders.forEach(order => {
        if (order.order_id && order.order_id.toLowerCase().includes(lowerQuery)) {
            results.push({
                type: 'Order',
                name: `Order #${order.order_id}`,
                detail: `₹${order.amount} | ${order.status}`,
                link: `orders.php?id=${order.order_id}`
            });
        }
    });

    if (results.length === 0) {
        searchDropdown.innerHTML = `<div style="padding: 15px; text-align: center; color: #94a3b8;">No results found for "${escapeHtml(query)}"</div>`;
        searchDropdown.style.display = 'block';
        return;
    }

    let html = '';
    results.forEach(result => {
        html += `
            <div class="search-result-item" data-link="${result.link}" style="padding: 12px 16px; border-bottom: 1px solid #e2e8f0; cursor: pointer; transition: background 0.2s;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 32px; height: 32px; background: ${result.type === 'Product' ? '#eff6ff' : result.type === 'Customer' ? '#ecfdf5' : '#fef3c7'}; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class='bx ${result.type === 'Product' ? 'bxs-box' : result.type === 'Customer' ? 'bxs-user' : 'bxs-file-pdf'}' style="color: ${result.type === 'Product' ? '#3b82f6' : result.type === 'Customer' ? '#10b981' : '#f59e0b'};"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 0.8rem; font-weight: 600; color: #0f172a;">${escapeHtml(result.name)}</div>
                        <div style="font-size: 0.7rem; color: #64748b;">${escapeHtml(result.detail)}</div>
                    </div>
                    <div style="font-size: 0.65rem; color: #94a3b8;">${result.type}</div>
                </div>
            </div>
        `;
    });

    searchDropdown.innerHTML = html;
    searchDropdown.style.display = 'block';

    // Add click handlers
    document.querySelectorAll('.search-result-item').forEach(item => {
        item.addEventListener('click', () => {
            const link = item.getAttribute('data-link');
            if (link) window.location.href = link;
        });
        item.addEventListener('mouseenter', () => {
            item.style.background = '#f8fafc';
        });
        item.addEventListener('mouseleave', () => {
            item.style.background = 'white';
        });
    });
}

if (searchInput) {
    searchInput.addEventListener('input', (e) => {
        performGlobalSearch(e.target.value);
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (searchInput && !searchInput.contains(e.target) && searchDropdown && !searchDropdown.contains(e.target)) {
            searchDropdown.style.display = 'none';
        }
    });
}

// Load search data on page load
loadSearchData();

// ========== NOTIFICATION SYSTEM (Stock Alerts, New Customers, etc.) ==========

let notificationsList = [];
let notificationInterval = null;

// Add notification function
function addDashboardNotification(title, message, type = 'info') {
    const newNotif = {
        id: Date.now(),
        title: title,
        message: message,
        time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        date: new Date().toISOString(),
        type: type,
        read: false,
        synced: false
    };
    
    notificationsList.unshift(newNotif);
    
    // Keep only last 50 notifications
    if (notificationsList.length > 50) notificationsList.pop();
    
    // Update bell badge
    updateNotificationBadge();
    
    // Save to localStorage
    localStorage.setItem('dashboardNotifications', JSON.stringify(notificationsList));
    
    // Show toast notification
    showToastNotification(title, message, type);
    
    // Store in database via AJAX - FIXED to ensure it works
    fetch('save_notification.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            title: title, 
            message: message, 
            type: type,
            created_at: newNotif.date
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Notification saved to DB:', title, data);
        newNotif.synced = true;
        localStorage.setItem('dashboardNotifications', JSON.stringify(notificationsList));
    })
    .catch(e => console.error('Error saving notification:', e));
}

function showToastNotification(title, message, type) {
    const toast = document.createElement('div');
    toast.className = 'toast-notification';

    const bgColor = type === 'alert' ? '#ef4444' : type === 'customer' ? '#10b981' : type === 'stock' ? '#f59e0b' : '#3b82f6';

    toast.innerHTML = `
        <div style="position: fixed; bottom: 20px; right: 20px; background: ${bgColor}; color: white; padding: 14px 20px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 10000; max-width: 350px; animation: slideInRight 0.3s ease;">
            <div style="font-weight: 600; margin-bottom: 4px;">${escapeHtml(title)}</div>
            <div style="font-size: 0.8rem; opacity: 0.9;">${escapeHtml(message)}</div>
        </div>
    `;

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 5000);
}

function updateNotificationBadge() {
    const unreadCount = notificationsList.filter(n => !n.read).length;
    const badge = document.querySelector('.notification-badge');
    if (badge) {
        if (unreadCount > 0) {
            badge.style.display = 'block';
            badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
        } else {
            badge.style.display = 'none';
        }
    }
}

function saveNotificationToDatabase(title, message, type) {
    fetch('save_notification.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title: title, message: message, type: type })
    }).catch(e => console.error('Error saving notification:', e));
}

// Check stock levels and generate alerts - MODIFIED to check if data exists
function checkStockLevels() {
    // Check if we have any products by looking for table rows or using a flag
    const productCount = document.querySelector('#productsTableBody tr') ?
        document.querySelectorAll('#productsTableBody tr').length : 0;

    if (productCount === 0 && notificationsList.length === 0) {
        console.log('No products found, skipping stock check');
        return;
    }

    fetch('get_low_stock.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.low_stock_products && data.low_stock_products.length > 0) {
                data.low_stock_products.forEach(product => {
                    if (product.stock <= product.reorder_level) {
                        addDashboardNotification(
                            '⚠️ Stock Alert',
                            `${product.name} has only ${product.stock} units left! Reorder soon.`,
                            'stock'
                        );
                    }
                });
            }
        })
        .catch(error => console.error('Error checking stock:', error));
}

// Check for new customers (compare with last check)
let lastCustomerCount = 0;

function checkNewCustomers() {
    fetch('get_customer_count.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                if (lastCustomerCount > 0 && data.count > lastCustomerCount) {
                    const newCount = data.count - lastCustomerCount;
                    addDashboardNotification(
                        '🎉 New Customers',
                        `${newCount} new customer${newCount > 1 ? 's' : ''} joined in the last hour!`,
                        'customer'
                    );
                }
                lastCustomerCount = data.count;
            }
        })
        .catch(error => console.error('Error checking customers:', error));
}

// Check low performing products - MODIFIED to check if data exists
function checkLowPerformingProducts() {
    // Check if we have any orders
    const orderCount = document.querySelector('#ordersTableBody tr') ?
        document.querySelectorAll('#ordersTableBody tr').length : 0;

    if (orderCount === 0 && notificationsList.length === 0) {
        console.log('No orders found, skipping performance check');
        return;
    }

    fetch('get_low_performing_products.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.products && data.products.length > 0) {
                // Only show first 3 low performing products to avoid spam
                data.products.slice(0, 3).forEach(product => {
                    if (product.sales_count < 5) {
                        addDashboardNotification(
                            '📉 Low Performance Alert',
                            `${product.name} has only ${product.sales_count} sales this month. Consider promotion.`,
                            'alert'
                        );
                    }
                });
            }
        })
        .catch(error => console.error('Error checking product performance:', error));
}

// Check pending orders - MODIFIED to check if data exists
function checkPendingOrders() {
    // Check if we have any orders
    const orderCount = document.querySelector('#ordersTableBody tr') ?
        document.querySelectorAll('#ordersTableBody tr').length : 0;

    if (orderCount === 0 && notificationsList.length === 0) {
        console.log('No orders found, skipping pending orders check');
        return;
    }

    fetch('get_pending_orders.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.count > 0) {
                addDashboardNotification(
                    '📦 Pending Orders',
                    `You have ${data.count} pending order${data.count > 1 ? 's' : ''} that need attention.`,
                    'alert'
                );
            }
        })
        .catch(error => console.error('Error checking pending orders:', error));
}

// Load saved notifications from localStorage
function loadSavedNotifications() {
    const saved = localStorage.getItem('dashboardNotifications');
    if (saved) {
        try {
            notificationsList = JSON.parse(saved);
            updateNotificationBadge();
        } catch (e) { }
    }
}

// Sync localStorage notifications to database
function syncNotificationsToDatabase() {
    const saved = localStorage.getItem('dashboardNotifications');
    if (saved) {
        try {
            const notifications = JSON.parse(saved);
            notifications.forEach(notif => {
                // Only send notifications that haven't been synced
                if (!notif.synced) {
                    fetch('save_notification.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            title: notif.title,
                            message: notif.message,
                            type: notif.type
                        })
                    });
                    notif.synced = true;
                }
            });
            localStorage.setItem('dashboardNotifications', JSON.stringify(notifications));
        } catch (e) { }
    }
}

// Call this after loading saved notifications
// Start notification intervals - UPDATED
function startNotificationServices() {
    // Load from localStorage first
    loadSavedNotifications();
    
    // Also try to load from database and merge
    fetch('get_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.notifications && data.notifications.length > 0) {
                // Merge database notifications with localStorage
                const existingIds = new Set(notificationsList.map(n => n.id));
                data.notifications.forEach(dbNotif => {
                    if (!existingIds.has(dbNotif.id)) {
                        notificationsList.push({
                            id: dbNotif.id,
                            title: dbNotif.title,
                            message: dbNotif.message,
                            time: dbNotif.created_at,
                            type: dbNotif.type,
                            read: dbNotif.is_read == 1,
                            synced: true
                        });
                    }
                });
                // Sort by id (newest first)
                notificationsList.sort((a, b) => b.id - a.id);
                // Keep only last 50
                if (notificationsList.length > 50) notificationsList = notificationsList.slice(0, 50);
                localStorage.setItem('dashboardNotifications', JSON.stringify(notificationsList));
                updateNotificationBadge();
            }
        })
        .catch(e => console.error('Error loading notifications from DB:', e));
    
    // Clear any existing old notifications (optional - comment out if you want to keep them)
    // if (notificationsList.length > 0) {
    //     console.log('Found ' + notificationsList.length + ' existing notifications');
    // }
    
    // Initial checks with delay only if we have data
    setTimeout(() => {
        // Only run checks if there's data in the system
        const hasProducts = document.querySelector('#productsTableBody tr') !== null;
        const hasOrders = document.querySelector('#ordersTableBody tr') !== null;
        
        if (hasProducts || hasOrders) {
            checkStockLevels();
            checkNewCustomers();
            checkLowPerformingProducts();
            checkPendingOrders();
        } else {
            console.log('No data found, skipping initial notification checks');
        }
    }, 10000);
    
    // Set intervals
    setInterval(checkStockLevels, 300000);
    setInterval(checkNewCustomers, 3600000);
    setInterval(checkLowPerformingProducts, 1800000);
    setInterval(checkPendingOrders, 600000);
}

// Notification click handler - show panel
const notificationButton = document.querySelector('.icon-button');
let notificationPanel = null;

function createNotificationPanel() {
    if (notificationPanel) {
        notificationPanel.remove();
        notificationPanel = null;
        return;
    }

    notificationPanel = document.createElement('div');
    notificationPanel.className = 'notification-panel';
    notificationPanel.style.cssText = `
        position: absolute;
        top: 60px;
        right: 20px;
        width: 380px;
        max-height: 500px;
        background: white;
        border-radius: 16px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        z-index: 1000;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    `;

    function renderNotifications() {
        const unreadCount = notificationsList.filter(n => !n.read).length;

        notificationPanel.innerHTML = `
            <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <div style="font-weight: 600;">Notifications ${unreadCount > 0 ? `<span style="background: #ef4444; color: white; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; margin-left: 8px;">${unreadCount}</span>` : ''}</div>
                <button id="markAllReadBtn" style="background: none; border: none; color: #3b82f6; font-size: 0.7rem; cursor: pointer;">Mark all as read</button>
            </div>
            <div style="flex: 1; overflow-y: auto; max-height: 400px;">
                ${notificationsList.length === 0 ? '<div style="padding: 40px; text-align: center; color: #94a3b8;">No notifications yet</div>' :
                notificationsList.map(notif => `
                        <div class="notif-item" data-id="${notif.id}" style="padding: 14px 20px; border-bottom: 1px solid #f1f5f9; cursor: pointer; ${notif.read ? 'opacity: 0.7;' : 'background: #fefce8;'}">
                            <div style="font-weight: 600; font-size: 0.85rem; margin-bottom: 4px;">${escapeHtml(notif.title)}</div>
                            <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 6px;">${escapeHtml(notif.message)}</div>
                            <div style="font-size: 0.65rem; color: #94a3b8;">${notif.time}</div>
                        </div>
                    `).join('')
            }
            </div>
            <div style="padding: 12px 20px; border-top: 1px solid #e2e8f0; text-align: center;">
                <a href="notifications.php" style="font-size: 0.75rem; color: #3b82f6; text-decoration: none;">View all notifications →</a>
            </div>
        `;

        // Add click handlers
        document.querySelectorAll('.notif-item').forEach(item => {
            item.addEventListener('click', () => {
                const id = parseInt(item.getAttribute('data-id'));
                const notif = notificationsList.find(n => n.id === id);
                if (notif) notif.read = true;
                updateNotificationBadge();
                localStorage.setItem('dashboardNotifications', JSON.stringify(notificationsList));
                renderNotifications();
            });
        });

        const markAllBtn = document.getElementById('markAllReadBtn');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', () => {
                notificationsList.forEach(n => n.read = true);
                updateNotificationBadge();
                localStorage.setItem('dashboardNotifications', JSON.stringify(notificationsList));
                renderNotifications();
            });
        }
    }

    renderNotifications();
    document.body.appendChild(notificationPanel);

    // Close panel when clicking outside
    setTimeout(() => {
        document.addEventListener('click', function closePanel(e) {
            if (notificationPanel && !notificationPanel.contains(e.target) && e.target !== notificationButton) {
                notificationPanel.remove();
                notificationPanel = null;
                document.removeEventListener('click', closePanel);
            }
        });
    }, 100);
}

if (notificationButton) {
    notificationButton.addEventListener('click', (e) => {
        e.stopPropagation();
        createNotificationPanel();
    });
}

// Start notification intervals - MODIFIED to add delay and prevent immediate alerts
function startNotificationServices() {
    loadSavedNotifications();

    // Clear any existing notifications that might be old
    if (notificationsList.length > 0) {
        console.log('Clearing existing notifications from localStorage');
        localStorage.removeItem('dashboardNotifications');
        notificationsList = [];
        updateNotificationBadge();
    }

    // Initial checks with longer delay to allow page to load first
    setTimeout(() => {
        checkStockLevels();
        checkNewCustomers();
        checkLowPerformingProducts();
        checkPendingOrders();
    }, 10000); // Increased from 2000 to 10000ms (10 seconds)

    // Set intervals
    setInterval(checkStockLevels, 300000); // Every 5 minutes
    setInterval(checkNewCustomers, 3600000); // Every hour
    setInterval(checkLowPerformingProducts, 1800000); // Every 30 minutes
    setInterval(checkPendingOrders, 600000); // Every 10 minutes
}

// Add CSS animation
const notificationStyle = document.createElement('style');
notificationStyle.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .search-result-item:hover {
        background: #f8fafc !important;
    }
    
    .notification-panel {
        animation: slideInRight 0.2s ease;
    }
`;
document.head.appendChild(notificationStyle);

// Initialize notifications
startNotificationServices();

function showLoading() {
    let loader = document.querySelector('.upload-loader');
    if (!loader) {
        loader = document.createElement('div');
        loader.className = 'upload-loader';
        loader.innerHTML = '<div class="spinner"></div><div>Processing file...</div>';
        loader.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:white;padding:20px;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.2);z-index:10000;text-align:center;';
        document.body.appendChild(loader);

        if (!document.querySelector('#loader-styles')) {
            const style = document.createElement('style');
            style.id = 'loader-styles';
            style.textContent = '.spinner{width:40px;height:40px;margin:0 auto 10px;border:3px solid #f3f3f3;border-top:3px solid #7494ec;border-radius:50%;animation:spin 1s linear infinite}@keyframes spin{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}';
            document.head.appendChild(style);
        }
    }
    loader.style.display = 'block';
}

function hideLoading() {
    const loader = document.querySelector('.upload-loader');
    if (loader) loader.style.display = 'none';
}

// ========== GLOBAL DATA MANAGEMENT (NEW) ==========

// Load global data from server (for all pages)
function loadGlobalData() {
    console.log('Loading global data from server...');

    fetch('get_global_data.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                console.log('Global data loaded:', data);
                updateDashboardWithExactValues(data);

                // Store in localStorage as backup
                localStorage.setItem('globalDashboardData', JSON.stringify(data));
            } else {
                console.log('No global data found, checking localStorage...');
                // Try to load from localStorage as fallback
                const savedData = localStorage.getItem('globalDashboardData');
                if (savedData) {
                    console.log('Loading from localStorage fallback');
                    updateDashboardWithExactValues(JSON.parse(savedData));
                }
            }
        })
        .catch(error => {
            console.error('Error loading global data:', error);
            // Fallback to localStorage
            const savedData = localStorage.getItem('globalDashboardData');
            if (savedData) {
                updateDashboardWithExactValues(JSON.parse(savedData));
            }
        });
}

// ========== REFRESH ALL PAGES AFTER UPLOAD ==========
function refreshAllPagesData() {
    console.log('Refreshing all pages data...');

    // Show notification
    showNotification('Updating all pages with new data...', 'info');

    // Set a flag in sessionStorage to indicate data was updated
    sessionStorage.setItem('data_updated', 'true');
    sessionStorage.setItem('update_timestamp', Date.now().toString());

    // Make a request to update all pages
    fetch('update_all_pages.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=refresh'
    })
        .then(response => response.json())
        .then(data => {
            console.log('Refresh response:', data);
            if (data.status === 'success') {
                showNotification('All pages updated successfully!', 'success');

                // Reload current page to show updated data after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showNotification('Warning: Some pages may need manual refresh', 'info');
            }
        })
        .catch(error => {
            console.error('Error refreshing pages:', error);
            // Fallback - just reload the page
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        });
}

// ========== FILE UPLOAD HANDLER ==========
document.addEventListener('DOMContentLoaded', function () {
    // Create hidden file input if not exists
    if (!document.getElementById('dashboardUpload')) {
        const input = document.createElement('input');
        input.type = 'file';
        input.id = 'dashboardUpload';
        input.accept = '.csv';
        input.style.display = 'none';
        document.body.appendChild(input);
    }

    // Find Upload link in sidebar (using ID)
    const uploadLink = document.getElementById('uploadFileLink');

    if (uploadLink) {
        uploadLink.addEventListener('click', function (e) {
            e.preventDefault();
            document.getElementById('dashboardUpload').click();
        });
    }

    // Alternative: Find by text if ID doesn't exist
    if (!uploadLink) {
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            if (item.textContent.includes('Upload file')) {
                item.addEventListener('click', function (e) {
                    e.preventDefault();
                    document.getElementById('dashboardUpload').click();
                });
            }
        });
    }

    // File input handler
    const fileInput = document.getElementById('dashboardUpload');
    if (fileInput) {
        fileInput.addEventListener('change', function (e) {
            const file = this.files[0];
            if (!file) return;
            handleFileUpload(file);
            this.value = ''; // Reset file input
        });
    }
});

function handleFileUpload(file) {
    // Validate file type
    if (!file.name.toLowerCase().endsWith('.csv')) {
        showNotification('❌ Please upload a CSV file', 'error');
        return;
    }

    // Validate file size (max 10MB)
    if (file.size > 10 * 1024 * 1024) {
        showNotification('❌ File size should be less than 10MB', 'error');
        return;
    }

    showLoading();
    const formData = new FormData();
    formData.append('uploadedFile', file);

    fetch('process_upload.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            console.log('Upload Response:', data);
            hideLoading();

            if (data.status === 'success') {
                // Update dashboard with exact values from CSV
                updateDashboardWithExactValues(data);
                showNotification('✅ ' + data.message, 'success');

                // Store in localStorage for persistence
                localStorage.setItem('globalDashboardData', JSON.stringify(data));

                // Also save to global JSON via separate call
                fetch('save_uploaded_data.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ csv_data: data })
                }).catch(e => console.error('Error saving to global storage:', e));

                // Refresh all pages data after successful upload
                refreshAllPagesData();

                setTimeout(() => {
                    showNotification('Dashboard updated with CSV data!', 'success');
                }, 500);
            } else {
                showNotification('❌ ' + (data.message || 'Upload failed'), 'error');
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            hideLoading();
            showNotification('❌ Network error: ' + error.message, 'error');
        });
}

// Main function: Update dashboard with exact values
function updateDashboardWithExactValues(data) {
    console.log('Updating dashboard with:', data);

    // Update Active Sales
    const activeSalesSpan = document.getElementById('active-sales-val');
    if (activeSalesSpan) {
        activeSalesSpan.textContent = data.active_sales;
        console.log('Set active sales to:', data.active_sales);
    }

    // Update Customer Count
    const custCountSpan = document.getElementById('cust-count-val');
    if (custCountSpan && data.customer_count !== undefined) {
        custCountSpan.textContent = data.customer_count;
    }

    // Update Product Revenue
    const revenueSpan = document.getElementById('product-revenue-val');
    if (revenueSpan && data.product_revenue) {
        revenueSpan.textContent = data.product_revenue;
    }

    // Update Product Sold
    const productSoldSpan = document.getElementById('product-sold-val');
    if (productSoldSpan && data.product_sold !== undefined) {
        productSoldSpan.textContent = data.product_sold;
    }

    // Update Conversion Rate
    const conversionSpan = document.getElementById('conversion-rate-val');
    if (conversionSpan && data.conversion_rate) {
        conversionSpan.textContent = data.conversion_rate;
    }

    // Update change percentages if they exist
    if (data.revenue_change) {
        const revenueChangeSpan = document.getElementById('revenue-change');
        if (revenueChangeSpan) revenueChangeSpan.textContent = data.revenue_change;

        const revenueChangeSpan2 = document.getElementById('revenue-change2');
        if (revenueChangeSpan2) revenueChangeSpan2.textContent = data.revenue_change;
    }

    if (data.units_change) {
        const unitsChangeSpan = document.getElementById('units-change');
        if (unitsChangeSpan) unitsChangeSpan.textContent = data.units_change;
    }

    if (data.conv_change) {
        const convChangeSpan = document.getElementById('conv-change');
        if (convChangeSpan) convChangeSpan.textContent = data.conv_change;
    }

    // Update Performance Score
    const scoreValue = document.querySelector('.score-value');
    if (scoreValue && data.performance_score) {
        scoreValue.textContent = data.performance_score;
    }

    const scoreBadge = document.querySelector('.score-badge');
    if (scoreBadge && data.score_change !== undefined) {
        const symbol = data.score_change.toString().startsWith('+') ? '' : '+';
        scoreBadge.textContent = `${symbol}${data.score_change}`;
        scoreBadge.style.backgroundColor = '#10b981';
    }

    // Update performance message
    const teamMessageP = document.querySelector('.team-message p');
    if (teamMessageP && data.performance_message) {
        teamMessageP.textContent = data.performance_message;
    }

    // Update Total Visits
    const totalVisit = document.querySelector('.total-number');
    if (totalVisit && data.total_visits) {
        totalVisit.textContent = data.total_visits;
    }

    // Update visit change percentage
    const totalChange = document.querySelector('.total-change');
    if (totalChange && data.visits_change) {
        totalChange.innerHTML = `vs last month <i class='bx bx-up-arrow-alt'></i>${data.visits_change}%`;
    }

    // Update Mobile/Website stats
    const statValues = document.querySelectorAll('.visit-stats .stat-value');
    if (statValues.length >= 2) {
        if (statValues[0] && data.mobile_visits) statValues[0].textContent = data.mobile_visits;
        if (statValues[1] && data.website_visits) statValues[1].textContent = data.website_visits;
    }

    // Update Donut Chart percentages
    if (data.mobile_percent !== undefined) {
        const donutTexts = document.querySelectorAll('.donut-chart svg text');
        if (donutTexts.length >= 2) {
            donutTexts[0].textContent = data.mobile_percent + '%';
            donutTexts[1].textContent = (100 - data.mobile_percent) + '%';
        }

        // Update donut chart circle
        const donutCircle = document.querySelector('.donut-chart svg circle:last-child');
        if (donutCircle) {
            const circumference = 2 * Math.PI * 80;
            const dashArray = (data.mobile_percent / 100) * circumference;
            donutCircle.setAttribute('stroke-dasharray', `${dashArray} ${circumference}`);
        }
    }

    // Update chart bars if monthly data is available
    if (data.monthly_sales && data.monthly_sales.length > 0) {
        updateChartBars(data.monthly_sales);
    }

    // Update gauge chart
    if (data.performance_score) {
        updateGaugeChart(data.performance_score);
    }

    console.log('Dashboard updated successfully!');
}

function updateGaugeChart(score) {
    const gaugePath = document.querySelector('.gauge-arc path:last-child');
    if (gaugePath) {
        const percentage = Math.min(score, 100) / 100;
        const startX = 20;
        const endX = 20 + (160 * percentage);
        const radius = 80;
        const centerY = 100;

        const angle = Math.PI * percentage;
        const x = 100 + radius * Math.cos(Math.PI - angle);
        const y = centerY - radius * Math.sin(Math.PI - angle);

        gaugePath.setAttribute('d', `M 20 100 A 80 80 0 0 1 ${x} ${y}`);
    }
}

function updateChartBars(monthlySales) {
    const chartBars = document.querySelectorAll('.chart-bar');
    const monthMap = {
        'Jan': 0, 'Feb': 1, 'Mar': 2, 'Apr': 3, 'May': 4, 'Jun': 5,
        'Jul': 6, 'Aug': 7, 'Sep': 8, 'Oct': 9, 'Nov': 10, 'Dec': 11
    };

    const maxRevenue = Math.max(...monthlySales.map(s => s.amount), 1);

    chartBars.forEach(bar => {
        const labelSpan = bar.querySelector('.chart-label');
        if (labelSpan) {
            const monthText = labelSpan.textContent.trim();
            const monthData = monthlySales.find(s => {
                const monthNum = parseInt(s.month.split('-')[1]);
                return monthMap[monthText] === monthNum - 1;
            });

            if (monthData) {
                const heightPercent = (monthData.amount / maxRevenue) * 80;
                bar.style.height = Math.max(5, heightPercent) + '%';

                const tooltip = bar.querySelector('.chart-tooltip');
                if (tooltip) {
                    tooltip.innerHTML = `
                        <div>${monthText}: ${monthData.month.split('-')[0]}</div>
                        <div>Revenue: <i class='bx bx-rupee'></i>${Math.round(monthData.amount).toLocaleString()}</div>
                        <div>Orders: ${monthData.orders || 0}</div>
                    `;
                }
            }
        }
    });
}

// ========== MAIN INITIALIZATION ==========
document.addEventListener('DOMContentLoaded', function () {
    // Check for data updates from other pages
    checkForDataUpdates();

    // Load global data from server (for dashboard)
    loadGlobalData();

    // Also load from localStorage if available
    const savedData = localStorage.getItem('globalDashboardData');
    if (savedData) {
        try {
            const data = JSON.parse(savedData);
            console.log('Loaded saved data from localStorage');
            updateDashboardWithExactValues(data);
        } catch (e) {
            console.error('Failed to load saved data', e);
        }
    }
});

// ========== ORDERS PAGE FUNCTIONALITY ==========
if (document.querySelector('.orders-table') || document.querySelector('#ordersTableBody')) {
    // Filter functionality
    const filterBtn = document.getElementById('filterBtn');
    const statusFilter = document.getElementById('statusFilter');
    let currentFilter = 'all';

    if (filterBtn && statusFilter) {
        filterBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (statusFilter.style.display === 'none' || statusFilter.style.display === '') {
                statusFilter.style.display = 'flex';
            } else {
                statusFilter.style.display = 'none';
            }
        });
    }

    const filterOptions = document.querySelectorAll('.filter-option');
    if (filterOptions.length > 0) {
        filterOptions.forEach(function (btn) {
            btn.addEventListener('click', function () {
                filterOptions.forEach(function (b) {
                    b.classList.remove('active');
                });
                this.classList.add('active');
                currentFilter = this.getAttribute('data-status');
                if (statusFilter) statusFilter.style.display = 'none';
                filterTable();
                goToPage(1);
            });
        });
    }

    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function () {
            filterTable();
            goToPage(1);
        });
    }

    function filterTable() {
        const rows = document.querySelectorAll('#ordersTableBody tr');
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';

        rows.forEach(function (row) {
            const status = row.getAttribute('data-status');
            const orderId = row.querySelector('.order-id') ? row.querySelector('.order-id').innerText.toLowerCase() : '';
            const customerName = row.querySelector('.customer-name') ? row.querySelector('.customer-name').innerText.toLowerCase() : '';
            const products = row.querySelector('.products-list') ? row.querySelector('.products-list').innerText.toLowerCase() : '';

            const matchesStatus = currentFilter === 'all' || status === currentFilter;
            const matchesSearch = searchTerm === '' || orderId.includes(searchTerm) || customerName.includes(searchTerm) || products.includes(searchTerm);

            row.style.display = matchesStatus && matchesSearch ? '' : 'none';
        });

        updatePagination();
    }

    let currentPage = 1;
    const rowsPerPage = 10;

    function updatePagination() {
        const allRows = document.querySelectorAll('#ordersTableBody tr');
        const rows = Array.from(allRows).filter(function (row) {
            return row.style.display !== 'none';
        });
        const totalRows = rows.length;
        const totalPages = Math.ceil(totalRows / rowsPerPage);

        const totalCountElem = document.getElementById('totalCount');
        if (totalCountElem) totalCountElem.innerText = totalRows;

        const pageNumbers = document.getElementById('pageNumbers');
        if (pageNumbers) {
            pageNumbers.innerHTML = '';
            if (totalPages > 0) {
                for (let i = 1; i <= totalPages; i++) {
                    const pageBtn = document.createElement('button');
                    pageBtn.className = 'page-number';
                    if (i === currentPage) pageBtn.classList.add('active');
                    pageBtn.innerText = i;
                    pageBtn.onclick = (function (page) {
                        return function () { goToPage(page); };
                    })(i);
                    pageNumbers.appendChild(pageBtn);
                }
            }
        }

        const prevBtn = document.getElementById('prevPage');
        const nextBtn = document.getElementById('nextPage');
        if (prevBtn) prevBtn.disabled = currentPage === 1;
        if (nextBtn) nextBtn.disabled = currentPage === totalPages || totalPages === 0;
    }

    function goToPage(page) {
        const allRows = document.querySelectorAll('#ordersTableBody tr');
        const rows = Array.from(allRows).filter(function (row) {
            return row.style.display !== 'none';
        });
        const totalPages = Math.ceil(rows.length / rowsPerPage);

        if (totalPages === 0) {
            currentPage = 1;
        } else {
            currentPage = Math.max(1, Math.min(page, totalPages));
        }

        const start = (currentPage - 1) * rowsPerPage;
        const end = start + rowsPerPage;

        rows.forEach(function (row, index) {
            if (index >= start && index < end) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });

        const showingStart = rows.length > 0 ? start + 1 : 0;
        const showingEnd = Math.min(end, rows.length);

        const startElem = document.getElementById('showingStart');
        const endElem = document.getElementById('showingEnd');
        if (startElem) startElem.innerText = showingStart;
        if (endElem) endElem.innerText = showingEnd;

        updatePagination();
    }

    const prevPageBtn = document.getElementById('prevPage');
    const nextPageBtn = document.getElementById('nextPage');

    if (prevPageBtn) {
        prevPageBtn.addEventListener('click', function () {
            goToPage(currentPage - 1);
        });
    }

    if (nextPageBtn) {
        nextPageBtn.addEventListener('click', function () {
            goToPage(currentPage + 1);
        });
    }

    window.viewOrder = function (orderId) {
        const modal = document.getElementById('orderModal');
        const modalBody = document.getElementById('modalBody');

        if (!modal || !modalBody) return;

        const rows = document.querySelectorAll('#ordersTableBody tr');
        let orderRow = null;

        for (let i = 0; i < rows.length; i++) {
            const orderIdElement = rows[i].querySelector('.order-id');
            if (orderIdElement && orderIdElement.innerText === '#' + orderId) {
                orderRow = rows[i];
                break;
            }
        }

        if (orderRow) {
            const orderIdText = orderRow.querySelector('.order-id') ? orderRow.querySelector('.order-id').innerText : '';
            const dateCell = orderRow.cells[1];
            const customerNameElement = orderRow.querySelector('.customer-name');
            const productsElement = orderRow.querySelector('.products-list');
            const quantityCell = orderRow.cells[4];
            const amountElement = orderRow.querySelector('.amount');
            const statusElement = orderRow.querySelector('.status-badge');

            modalBody.innerHTML = `
                <div class="order-detail-row"><div class="detail-label">Order ID:</div><div class="detail-value">${escapeHtml(orderIdText)}</div></div>
                <div class="order-detail-row"><div class="detail-label">Order Date:</div><div class="detail-value">${escapeHtml(dateCell ? dateCell.innerText : '')}</div></div>
                <div class="order-detail-row"><div class="detail-label">Customer:</div><div class="detail-value">${escapeHtml(customerNameElement ? customerNameElement.innerText : '')}</div></div>
                <div class="order-detail-row"><div class="detail-label">Products:</div><div class="detail-value">${escapeHtml(productsElement ? productsElement.innerText : '')}</div></div>
                <div class="order-detail-row"><div class="detail-label">Total Quantity:</div><div class="detail-value">${escapeHtml(quantityCell ? quantityCell.innerText : '')}</div></div>
                <div class="order-detail-row"><div class="detail-label">Total Amount:</div><div class="detail-value">${escapeHtml(amountElement ? amountElement.innerText : '')}</div></div>
                <div class="order-detail-row"><div class="detail-label">Status:</div><div class="detail-value"><span class="status-badge ${escapeHtml(statusElement ? statusElement.innerText.toLowerCase() : '')}">${escapeHtml(statusElement ? statusElement.innerText : '')}</span></div></div>
            `;
        }

        modal.style.display = 'flex';
    }

    window.editOrder = function (orderId) {
        alert('Edit functionality for order #' + orderId + ' will be implemented soon.');
    }

    window.closeModal = function () {
        const modal = document.getElementById('orderModal');
        if (modal) modal.style.display = 'none';
    }

    window.onclick = function (event) {
        const modal = document.getElementById('orderModal');
        if (event.target === modal && modal) {
            modal.style.display = 'none';
        }
    }

    const exportBtn = document.getElementById('exportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            alert('Export functionality will be implemented soon.');
        });
    }

    // Initialize orders page
    if (typeof filterTable === 'function') {
        filterTable();
        goToPage(1);
    }
}

// ========== PRODUCTS PAGE FUNCTIONALITY ==========
if (document.querySelector('.products-table') || document.querySelector('#productsTableBody')) {
    const searchInput = document.getElementById('searchProducts');
    if (searchInput) {
        searchInput.addEventListener('keyup', function () {
            filterProductsTable();
        });
    }

    function filterProductsTable() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const rows = document.querySelectorAll('#productsTableBody tr');

        rows.forEach(function (row) {
            const productName = row.querySelector('.product-name') ? row.querySelector('.product-name').innerText.toLowerCase() : '';
            const category = row.querySelector('.product-category') ? row.querySelector('.product-category').innerText.toLowerCase() : '';
            const sku = row.querySelector('.product-sku') ? row.querySelector('.product-sku').innerText.toLowerCase() : '';

            if (productName.includes(searchTerm) || category.includes(searchTerm) || sku.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    window.editProduct = function (productId) {
        window.location.href = 'edit_product.php?id=' + productId;
    }

    window.deleteProduct = function (productId) {
        if (confirm('Are you sure you want to delete this product?')) {
            fetch('delete_product.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: productId })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showNotification('Product deleted successfully', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification('Failed to delete product', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Error: ' + error.message, 'error');
                });
        }
    }

    // Initialize products page
    filterProductsTable();
}

// ========== CUSTOMERS PAGE FUNCTIONALITY ==========
if (document.querySelector('.customers-table') || document.querySelector('#customersTableBody')) {
    const searchInput = document.getElementById('searchCustomers');
    if (searchInput) {
        searchInput.addEventListener('keyup', function () {
            filterCustomersTable();
        });
    }

    function filterCustomersTable() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const rows = document.querySelectorAll('#customersTableBody tr');

        rows.forEach(function (row) {
            const customerName = row.querySelector('.customer-name') ? row.querySelector('.customer-name').innerText.toLowerCase() : '';
            const email = row.querySelector('.customer-email') ? row.querySelector('.customer-email').innerText.toLowerCase() : '';
            const phone = row.querySelector('.customer-phone') ? row.querySelector('.customer-phone').innerText.toLowerCase() : '';

            if (customerName.includes(searchTerm) || email.includes(searchTerm) || phone.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    window.viewCustomer = function (customerId) {
        window.location.href = 'customer_details.php?id=' + customerId;
    }

    window.editCustomer = function (customerId) {
        window.location.href = 'edit_customer.php?id=' + customerId;
    }

    // Initialize customers page
    filterCustomersTable();
}

// ========== INVENTORY PAGE FUNCTIONALITY ==========
if (document.querySelector('.inventory-table') || document.querySelector('#inventoryTableBody')) {
    const searchInput = document.getElementById('searchInventory');
    if (searchInput) {
        searchInput.addEventListener('keyup', function () {
            filterInventoryTable();
        });
    }

    function filterInventoryTable() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const rows = document.querySelectorAll('#inventoryTableBody tr');

        rows.forEach(function (row) {
            const productName = row.querySelector('.inventory-product') ? row.querySelector('.inventory-product').innerText.toLowerCase() : '';
            const sku = row.querySelector('.inventory-sku') ? row.querySelector('.inventory-sku').innerText.toLowerCase() : '';
            const category = row.querySelector('.inventory-category') ? row.querySelector('.inventory-category').innerText.toLowerCase() : '';

            if (productName.includes(searchTerm) || sku.includes(searchTerm) || category.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    window.updateStock = function (productId) {
        const newStock = prompt('Enter new stock quantity:');
        if (newStock !== null && !isNaN(newStock) && newStock >= 0) {
            fetch('update_stock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: productId, quantity: parseInt(newStock) })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showNotification('Stock updated successfully', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification('Failed to update stock', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Error: ' + error.message, 'error');
                });
        }
    }

    window.reorderStock = function (productName) {
        if (confirm('Would you like to reorder ' + productName + '?')) {
            showNotification('Reorder request sent for ' + productName, 'success');
        }
    }

    // Initialize inventory page
    filterInventoryTable();
}

// ========== REPORTS PAGE FUNCTIONALITY ==========
if (document.querySelector('.reports-container')) {
    function initializeReportsPage() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keyup', function () {
                filterReportsTable();
            });
        }

        const reportPeriod = document.getElementById('reportPeriod');
        if (reportPeriod) {
            reportPeriod.addEventListener('change', function () {
                toggleCustomDateRange();
            });
        }

        setDefaultDates();
    }

    function filterReportsTable() {
        const searchInput = document.getElementById('searchInput');
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const rows = document.querySelectorAll('#reportsTableBody tr');

        rows.forEach(function (row) {
            const reportName = row.querySelector('.report-name') ? row.querySelector('.report-name').innerText.toLowerCase() : '';
            const reportType = row.querySelector('.report-type-badge') ? row.querySelector('.report-type-badge').innerText.toLowerCase() : '';

            if (reportName.includes(searchTerm) || reportType.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    window.selectReportType = function (type) {
        const reportOptions = document.getElementById('reportOptions');
        if (reportOptions) {
            reportOptions.style.display = 'block';
        }

        const reportTypes = document.querySelectorAll('.report-type');
        reportTypes.forEach(function (el) {
            el.classList.remove('selected');
        });

        if (window.event && window.event.currentTarget) {
            window.event.currentTarget.classList.add('selected');
        }
    }

    window.generateReport = function () {
        const periodInput = document.getElementById('reportPeriod');
        const formatInput = document.getElementById('reportFormat');
        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');

        const period = periodInput ? periodInput.value : 'month';
        const format = formatInput ? formatInput.value : 'pdf';
        const startDate = startDateInput ? startDateInput.value : '';
        const endDate = endDateInput ? endDateInput.value : '';

        let dateRange = '';
        if (period === 'custom' && startDate && endDate) {
            dateRange = ' from ' + startDate + ' to ' + endDate;
        } else {
            dateRange = ' for ' + period;
        }

        showNotification('Generating ' + format.toUpperCase() + ' report' + dateRange + '...', 'success');

        const selectedType = document.querySelector('.report-type.selected');
        let reportType = 'Report';
        if (selectedType) {
            const headingElement = selectedType.querySelector('h4');
            if (headingElement) {
                reportType = headingElement.innerText;
            }
        }

        addToRecentReports(reportType, format);
        cancelReport();
    }

    function addToRecentReports(reportType, format) {
        const reportsTable = document.getElementById('reportsTableBody');
        if (!reportsTable) return;

        const currentDate = new Date();
        const formattedDate = currentDate.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });

        let fileSize = '1.2 MB';
        let fileExtension = 'pdf';

        if (format === 'excel') {
            fileSize = '856 KB';
            fileExtension = 'xlsx';
        } else if (format === 'csv') {
            fileSize = '234 KB';
            fileExtension = 'csv';
        }

        const dateStr = currentDate.toISOString().slice(0, 10);
        const fileName = reportType.replace(/\s/g, '_') + '_Report_' + dateStr + '.' + fileExtension;

        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td class="report-name"><i class='bx bx-file'></i>${escapeHtml(fileName)}</td>
            <td><span class="report-type-badge">${escapeHtml(reportType)}</span></td>
            <td>${escapeHtml(formattedDate)}</td>
            <td>${escapeHtml(fileSize)}</td>
            <td><div class="action-buttons"><button class="action-btn download" onclick="downloadReport('${escapeHtml(fileName)}')"><i class='bx bx-download'></i></button><button class="action-btn delete" onclick="deleteReport(this)"><i class='bx bx-trash'></i></button></div></td>
        `;

        reportsTable.insertBefore(newRow, reportsTable.firstChild);
    }

    window.cancelReport = function () {
        const reportOptions = document.getElementById('reportOptions');
        if (reportOptions) {
            reportOptions.style.display = 'none';
        }

        const reportTypes = document.querySelectorAll('.report-type');
        reportTypes.forEach(function (el) {
            el.classList.remove('selected');
        });

        const reportPeriod = document.getElementById('reportPeriod');
        if (reportPeriod) reportPeriod.value = 'month';

        const customDateRange = document.getElementById('customDateRange');
        if (customDateRange) customDateRange.style.display = 'none';
    }

    function toggleCustomDateRange() {
        const reportPeriod = document.getElementById('reportPeriod');
        const customDateRange = document.getElementById('customDateRange');

        if (reportPeriod && customDateRange) {
            if (reportPeriod.value === 'custom') {
                customDateRange.style.display = 'block';
            } else {
                customDateRange.style.display = 'none';
            }
        }
    }

    function setDefaultDates() {
        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');

        if (startDateInput && endDateInput) {
            const today = new Date();
            const lastMonth = new Date();
            lastMonth.setMonth(lastMonth.getMonth() - 1);

            startDateInput.value = lastMonth.toISOString().slice(0, 10);
            endDateInput.value = today.toISOString().slice(0, 10);
        }
    }

    window.downloadReport = function (reportName) {
        showNotification('Downloading ' + reportName + '...', 'success');
    }

    window.deleteReport = function (button) {
        if (confirm('Are you sure you want to delete this report?')) {
            var row = button;
            while (row && row.tagName !== 'TR') {
                row = row.parentElement;
            }
            if (row) {
                row.remove();
                showNotification('Report deleted successfully', 'success');
            }
        }
    }

    window.refreshReports = function () {
        if (confirm('Refresh the reports list?')) {
            location.reload();
        }
    }

    window.clearAllReports = function () {
        if (confirm('Are you sure you want to clear ALL reports? This action cannot be undone.')) {
            const reportsTable = document.getElementById('reportsTableBody');
            if (reportsTable) {
                reportsTable.innerHTML = '';
                showNotification('All reports cleared', 'success');
            }
        }
    }

    window.exportChart = function () {
        const canvas = document.getElementById('salesChart');
        if (canvas) {
            try {
                const link = document.createElement('a');
                link.download = 'sales_chart.png';
                link.href = canvas.toDataURL();
                link.click();
                showNotification('Chart exported successfully!', 'success');
            } catch (e) {
                showNotification('Error exporting chart: ' + e.message, 'error');
            }
        } else {
            showNotification('Chart not found!', 'error');
        }
    }

    // Initialize reports page
    initializeReportsPage();
}

// ========== CHART AND FILTER FUNCTIONALITY ==========
document.addEventListener('DOMContentLoaded', function () {
    const chartBars = document.querySelectorAll('.chart-bar');
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    chartBars.forEach(bar => {
        const labelSpan = bar.querySelector('.chart-label');
        if (labelSpan) {
            const monthText = labelSpan.textContent.trim();
            if (monthText && monthNames.includes(monthText)) {
                bar.style.cursor = 'pointer';
                bar.addEventListener('click', function (event) {
                    event.stopPropagation();
                    window.location.href = `month.php?month=${monthText}`;
                });
            }
        }
    });

    const filterBtn = document.querySelector('.chart-controls .btn:first-child');
    if (filterBtn) {
        filterBtn.addEventListener('click', function (e) {
            e.preventDefault();
            showFilterModal();
        });
    }

    const lastYearBtn = document.querySelector('.chart-controls .btn:nth-child(2)');
    if (lastYearBtn) {
        const newBtn = lastYearBtn.cloneNode(true);
        lastYearBtn.parentNode.replaceChild(newBtn, lastYearBtn);
        newBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            showYearDropdown();
        });
    }

    const expandBtn = document.querySelector('.chart-controls .btn:last-child');
    if (expandBtn) {
        expandBtn.addEventListener('click', function () {
            const analyticsCard = document.querySelector('.content-grid .card:last-child');
            if (analyticsCard) {
                if (!document.fullscreenElement) {
                    if (analyticsCard.requestFullscreen) {
                        analyticsCard.requestFullscreen();
                    } else if (analyticsCard.webkitRequestFullscreen) {
                        analyticsCard.webkitRequestFullscreen();
                    }
                    analyticsCard.style.background = '#fff';
                    analyticsCard.style.overflow = 'auto';
                } else {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    }
                }
            }
        });
    }
});

function showFilterModal() {
    const existingModal = document.getElementById('filterModal');
    if (existingModal) {
        existingModal.remove();
    }

    const modal = document.createElement('div');
    modal.id = 'filterModal';
    modal.style.cssText = `position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:10000;`;

    modal.innerHTML = `
        <div style="background:white;border-radius:20px;width:450px;max-width:90%;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="font-size:1.2rem;color:#0f172a;margin:0;"><i class='bx bx-filter-alt' style="color:#3b82f6;"></i> Filter Options</h3>
                <button onclick="closeFilterModal()" style="background:none;border:none;font-size:28px;cursor:pointer;color:#64748b;">&times;</button>
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block;font-size:0.75rem;color:#64748b;margin-bottom:6px;font-weight:600;">DATE RANGE</label>
                <select id="filterDateRange" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;">
                    <option value="last7">Last 7 days</option>
                    <option value="last30">Last 30 days</option>
                    <option value="last90">Last 90 days</option>
                    <option value="lastYear">Last Year</option>
                </select>
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block;font-size:0.75rem;color:#64748b;margin-bottom:6px;font-weight:600;">CATEGORY</label>
                <select id="filterCategory" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;">
                    <option value="all">All Categories</option>
                    <option value="electronics">Electronics</option>
                    <option value="clothing">Clothing</option>
                    <option value="books">Books</option>
                </select>
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block;font-size:0.75rem;color:#64748b;margin-bottom:6px;font-weight:600;">SORT BY</label>
                <select id="filterSort" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;">
                    <option value="date_desc">Latest First</option>
                    <option value="date_asc">Oldest First</option>
                    <option value="revenue_high">Highest Revenue</option>
                    <option value="revenue_low">Lowest Revenue</option>
                </select>
            </div>
            <div style="display:flex;gap:12px;margin-top:20px;">
                <button onclick="applyFilterAndReload()" style="flex:1;background:#3b82f6;color:white;border:none;padding:12px;border-radius:10px;cursor:pointer;font-weight:600;"><i class='bx bx-check'></i> Apply Filter</button>
                <button onclick="closeFilterModal()" style="flex:1;background:#f1f5f9;color:#64748b;border:none;padding:12px;border-radius:10px;cursor:pointer;">Cancel</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
}

function closeFilterModal() {
    const modal = document.getElementById('filterModal');
    if (modal) modal.remove();
}

function applyFilterAndReload() {
    const dateRange = document.getElementById('filterDateRange').value;
    const category = document.getElementById('filterCategory').value;
    const sortBy = document.getElementById('filterSort').value;
    window.location.href = `indexHome.php?filter=${dateRange}&category=${category}&sort=${sortBy}`;
}

function showYearDropdown() {
    const existingDropdown = document.getElementById('yearDropdown');
    if (existingDropdown) {
        existingDropdown.remove();
        return;
    }

    const currentYear = new Date().getFullYear();
    const years = [];
    for (let y = currentYear; y >= currentYear - 5; y--) {
        years.push(y);
    }

    const lastYearBtn = document.querySelector('.chart-controls .btn:nth-child(2)');
    if (!lastYearBtn) return;

    const rect = lastYearBtn.getBoundingClientRect();
    const dropdown = document.createElement('div');
    dropdown.id = 'yearDropdown';
    dropdown.style.cssText = `position:absolute;top:${rect.bottom + window.scrollY + 5}px;left:${rect.left + window.scrollX}px;background:white;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.15);border:1px solid #e2e8f0;z-index:1000;min-width:120px;overflow:hidden;`;

    let dropdownHTML = '';
    years.forEach(year => {
        dropdownHTML += `<div class="year-option" data-year="${year}" style="padding:10px 20px;cursor:pointer;transition:background 0.2s;font-size:0.85rem;color:#1e293b;text-align:center;">${year}</div>`;
    });

    dropdown.innerHTML = dropdownHTML;
    document.body.appendChild(dropdown);

    const options = dropdown.querySelectorAll('.year-option');
    options.forEach(opt => {
        opt.addEventListener('mouseenter', () => { opt.style.background = '#f1f5f9'; });
        opt.addEventListener('mouseleave', () => { opt.style.background = 'white'; });
        opt.addEventListener('click', () => {
            const selectedYear = opt.getAttribute('data-year');
            updateChartForYear(selectedYear);
            dropdown.remove();
            const btn = document.querySelector('.chart-controls .btn:nth-child(2)');
            if (btn) {
                btn.innerHTML = `${selectedYear} <i class='bx bx-chevron-down'></i>`;
            }
        });
    });

    setTimeout(() => {
        document.addEventListener('click', function closeDropdown(e) {
            if (dropdown && !dropdown.contains(e.target) && e.target !== lastYearBtn) {
                dropdown.remove();
                document.removeEventListener('click', closeDropdown);
            }
        });
    }, 100);
}

function updateChartForYear(year) {
    showNotification(`Loading data for ${year}...`, 'success');
    setTimeout(() => {
        showNotification(`Data updated for ${year}`, 'success');
    }, 1000);
}

// Add animation keyframes to document
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);

// ========== EXPORT GLOBAL FUNCTIONS ==========
window.showNotification = showNotification;
window.toggleSidebar = toggleSidebar;
window.confirmLogout = confirmLogout;

// ========== FIXED SEARCH FUNCTIONALITY ==========
(function () {
    // Wait for DOM to be fully loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSearch);
    } else {
        initSearch();
    }

    function initSearch() {
        console.log('Search initialized');

        const searchInput = document.getElementById('globalSearchInput');
        const searchDropdown = document.getElementById('searchResultsDropdown');

        if (!searchInput) {
            console.error('Search input not found! Check ID: globalSearchInput');
            return;
        }

        if (!searchDropdown) {
            console.error('Search dropdown not found!');
            return;
        }

        // Sample data for testing (will be replaced with real data from server)
        let searchData = {
            products: [],
            customers: [],
            orders: []
        };

        // Fetch real data from your existing tables
        function fetchSearchData() {
            // Try to get products from the page if they exist
            const productsTable = document.querySelector('#productsTableBody');
            if (productsTable) {
                const rows = productsTable.querySelectorAll('tr');
                rows.forEach(row => {
                    const nameCell = row.querySelector('.product-name');
                    const skuCell = row.querySelector('.product-sku');
                    if (nameCell) {
                        searchData.products.push({
                            name: nameCell.innerText,
                            sku: skuCell ? skuCell.innerText : '',
                            stock: 'In stock'
                        });
                    }
                });
            }

            // Try to get customers from the page
            const customersTable = document.querySelector('#customersTableBody');
            if (customersTable) {
                const rows = customersTable.querySelectorAll('tr');
                rows.forEach(row => {
                    const nameCell = row.querySelector('.customer-name');
                    const emailCell = row.querySelector('.customer-email');
                    if (nameCell) {
                        searchData.customers.push({
                            name: nameCell.innerText,
                            email: emailCell ? emailCell.innerText : ''
                        });
                    }
                });
            }

            // If no data from DOM, use mock data for demo
            if (searchData.products.length === 0) {
                searchData.products = [
                    { name: 'Wireless Mouse', sku: 'MOUSE-001', stock: 45 },
                    { name: 'Mechanical Keyboard', sku: 'KB-002', stock: 23 },
                    { name: 'USB-C Hub', sku: 'HUB-003', stock: 12 },
                    { name: 'Monitor Stand', sku: 'STAND-004', stock: 8 },
                    { name: 'Laptop Bag', sku: 'BAG-005', stock: 31 },
                    { name: 'HDMI Cable', sku: 'CABLE-006', stock: 67 },
                    { name: 'Webcam HD', sku: 'CAM-007', stock: 15 },
                    { name: 'Microphone', sku: 'MIC-008', stock: 9 }
                ];
            }

            if (searchData.customers.length === 0) {
                searchData.customers = [
                    { name: 'John Doe', email: 'john@example.com' },
                    { name: 'Jane Smith', email: 'jane@example.com' },
                    { name: 'Mike Johnson', email: 'mike@example.com' },
                    { name: 'Sarah Williams', email: 'sarah@example.com' },
                    { name: 'David Brown', email: 'david@example.com' }
                ];
            }

            if (searchData.orders.length === 0) {
                searchData.orders = [
                    { id: 'ORD-001', amount: 1250, status: 'Completed' },
                    { id: 'ORD-002', amount: 890, status: 'Processing' },
                    { id: 'ORD-003', amount: 2100, status: 'Pending' },
                    { id: 'ORD-004', amount: 540, status: 'Completed' },
                    { id: 'ORD-005', amount: 3200, status: 'Shipped' }
                ];
            }
        }

        // Perform search
        function performSearch(query) {
            const lowerQuery = query.toLowerCase().trim();

            if (lowerQuery === '') {
                searchDropdown.style.display = 'none';
                return;
            }

            const results = [];

            // Search products
            searchData.products.forEach(product => {
                if (product.name.toLowerCase().includes(lowerQuery) ||
                    (product.sku && product.sku.toLowerCase().includes(lowerQuery))) {
                    results.push({
                        type: '📦 Product',
                        name: product.name,
                        detail: `SKU: ${product.sku || 'N/A'} | Stock: ${product.stock}`,
                        icon: 'bxs-box',
                        color: '#3b82f6',
                        link: 'products.php'
                    });
                }
            });

            // Search customers
            searchData.customers.forEach(customer => {
                if (customer.name.toLowerCase().includes(lowerQuery) ||
                    (customer.email && customer.email.toLowerCase().includes(lowerQuery))) {
                    results.push({
                        type: '👤 Customer',
                        name: customer.name,
                        detail: customer.email,
                        icon: 'bxs-user',
                        color: '#10b981',
                        link: 'customers.php'
                    });
                }
            });

            // Search orders
            searchData.orders.forEach(order => {
                if (order.id.toLowerCase().includes(lowerQuery)) {
                    results.push({
                        type: '📄 Order',
                        name: `Order #${order.id}`,
                        detail: `₹${order.amount} | ${order.status}`,
                        icon: 'bxs-file-pdf',
                        color: '#f59e0b',
                        link: 'orders.php'
                    });
                }
            });

            if (results.length === 0) {
                searchDropdown.innerHTML = `
                    <div style="padding: 20px; text-align: center; color: #94a3b8;">
                        <i class='bx bx-search' style="font-size: 2rem; margin-bottom: 8px; display: block;"></i>
                        No results found for "${escapeHtml(query)}"
                    </div>
                `;
                searchDropdown.style.display = 'block';
                return;
            }

            let html = '<div style="padding: 8px 12px; background: #f8fafc; font-size: 0.7rem; color: #64748b; border-bottom: 1px solid #e2e8f0;">Search Results</div>';

            results.forEach(result => {
                html += `
                    <div class="search-result-item" data-link="${result.link}" data-name="${escapeHtml(result.name)}" style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background 0.2s;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 36px; height: 36px; background: ${result.color}10; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <i class='bx ${result.icon}' style="color: ${result.color}; font-size: 1.2rem;"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-size: 0.85rem; font-weight: 600; color: #0f172a;">${escapeHtml(result.name)}</div>
                                <div style="font-size: 0.7rem; color: #64748b;">${escapeHtml(result.detail)}</div>
                            </div>
                            <div style="font-size: 0.65rem; color: #94a3b8; background: #f1f5f9; padding: 2px 8px; border-radius: 20px;">${result.type}</div>
                        </div>
                    </div>
                `;
            });

            searchDropdown.innerHTML = html;
            searchDropdown.style.display = 'block';

            // Add click handlers
            document.querySelectorAll('.search-result-item').forEach(item => {
                item.addEventListener('click', function (e) {
                    e.stopPropagation();
                    const link = this.getAttribute('data-link');
                    const name = this.getAttribute('data-name');

                    // Show notification that item was selected
                    showNotification(`Selected: ${name}`, 'success');

                    if (link) {
                        window.location.href = link;
                    }
                });

                item.addEventListener('mouseenter', function () {
                    this.style.background = '#f8fafc';
                });

                item.addEventListener('mouseleave', function () {
                    this.style.background = 'white';
                });
            });
        }

        // Input event listener
        searchInput.addEventListener('input', function (e) {
            const query = e.target.value;
            if (query.length > 0) {
                performSearch(query);
            } else {
                searchDropdown.style.display = 'none';
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (searchInput && !searchInput.contains(e.target) && searchDropdown && !searchDropdown.contains(e.target)) {
                searchDropdown.style.display = 'none';
            }
        });

        // Prevent dropdown from closing when clicking inside
        if (searchDropdown) {
            searchDropdown.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }

        // Load initial data
        fetchSearchData();

        console.log('Search is ready! Found ' + searchData.products.length + ' products, ' + searchData.customers.length + ' customers');
    }
})();