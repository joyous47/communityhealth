function sanitizeHTML(html) {
    const div = document.createElement('div');
    div.textContent = html;
    return div.innerHTML;
}

function encodeHTML(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function decodeHTML(html) {
    const txt = document.createElement('textarea');
    txt.innerHTML = html;
    return txt.value;
}

function elementExists(selector) {
    return document.querySelector(selector) !== null;
}

function getElement(id) {
    return document.getElementById(id);
}

function getElements(selector) {
    return document.querySelectorAll(selector);
}

function isVisible(element) {
    return element.offsetParent !== null;
}

function addClass(element, className) {
    if (element) {
        element.classList.add(className);
    }
}

function removeClass(element, className) {
    if (element) {
        element.classList.remove(className);
    }
}

function toggleClass(element, className) {
    if (element) {
        element.classList.toggle(className);
    }
}

function hasClass(element, className) {
    return element && element.classList.contains(className);
}

function getText(selector) {
    const element = typeof selector === 'string' ? document.querySelector(selector) : selector;
    return element ? element.textContent.trim() : '';
}

function setText(selector, text) {
    const element = typeof selector === 'string' ? document.querySelector(selector) : selector;
    if (element) {
        element.textContent = text;
    }
}

function getHTML(selector) {
    const element = typeof selector === 'string' ? document.querySelector(selector) : selector;
    return element ? element.innerHTML : '';
}

function setHTML(selector, html) {
    const element = typeof selector === 'string' ? document.querySelector(selector) : selector;
    if (element) {
        element.innerHTML = html;
    }
}

function getAttribute(selector, attribute) {
    const element = typeof selector === 'string' ? document.querySelector(selector) : selector;
    return element ? element.getAttribute(attribute) : null;
}

function setAttribute(selector, attribute, value) {
    const element = typeof selector === 'string' ? document.querySelector(selector) : selector;
    if (element) {
        element.setAttribute(attribute, value);
    }
}

function openModal(modalId) {
    const modal = getElement(modalId);
    if (modal) {
        addClass(modal, 'modal-open');
        addClass(modal, 'show');
        addClass(document.body, 'modal-open');
        
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = getElement(modalId);
    if (modal) {
        removeClass(modal, 'modal-open');
        removeClass(modal, 'show');
        removeClass(document.body, 'modal-open');
        
        document.body.style.overflow = '';
    }
}

function toggleModal(modalId) {
    const modal = getElement(modalId);
    if (modal && hasClass(modal, 'show')) {
        closeModal(modalId);
    } else {
        openModal(modalId);
    }
}

function setupModalCloseButtons() {
    const closeButtons = getElements('[data-close-modal]');
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modalId = this.getAttribute('data-close-modal');
            closeModal(modalId);
        });
    });
}

function setupModalBackgroundClose() {
    const modals = getElements('[data-modal]');
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });
}

function setupModalEscapeKey() {
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModals = getElements('.modal-open');
            if (openModals.length > 0) {
                closeModal(openModals[openModals.length - 1].id);
            }
        }
    });
}

function initializeModals() {
    setupModalCloseButtons();
    setupModalBackgroundClose();
    setupModalEscapeKey();
}

function showToast(message, type = 'info', duration = 3000) {
    let container = getElement('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        `;
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.style.cssText = `
        background: #339af0;
        color: white;
        padding: 16px 20px;
        border-radius: 8px;
        margin-bottom: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        animation: slideIn 0.3s ease-out;
        display: flex;
        align-items: center;
        gap: 12px;
        border: 2px solid white;
    `;

    const icon = getToastIcon(type);
    toast.innerHTML = `
        <span style="font-size: 1.2em;">${icon}</span>
        <span>${encodeHTML(message)}</span>
        <button class="toast-close" style="
            background: none;
            border: none;
            color: white;
            font-size: 1.2em;
            cursor: pointer;
            padding: 0;
            margin-left: auto;
        ">×</button>
    `;

    toast.querySelector('.toast-close').addEventListener('click', function() {
        toast.remove();
    });

    container.appendChild(toast);

    if (duration > 0) {
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, duration);
    }
}

function getToastIcon(type) {
    const icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
    };
    return icons[type] || icons.info;
}

function showLoading(message = 'Loading...') {
    let overlay = getElement('loading-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loading-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9998;
        `;
        document.body.appendChild(overlay);
    }

    overlay.innerHTML = `
        <div style="
            background: white;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            border: 2px solid #339af0;
        ">
            <div style="
                width: 40px;
                height: 40px;
                border: 4px solid #e7f5ff;
                border-top: 4px solid #339af0;
                border-radius: 50%;
                margin: 0 auto 20px;
                animation: spin 1s linear infinite;
            "></div>
            <p style="color: #000000; font-weight: 600; margin: 0;">${encodeHTML(message)}</p>
        </div>
    `;

    overlay.style.display = 'flex';
}

function hideLoading() {
    const overlay = getElement('loading-overlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

function showButtonLoading(button, originalText = 'Loading...') {
    if (button) {
        button.disabled = true;
        button.dataset.originalText = button.textContent;
        button.innerHTML = `
            <span style="
                display: inline-block;
                width: 14px;
                height: 14px;
                border: 2px solid rgba(255,255,255,0.3);
                border-top: 2px solid white;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
                margin-right: 8px;
            "></span>
            ${encodeHTML(originalText)}
        `;
    }
}

function hideButtonLoading(button) {
    if (button) {
        button.disabled = false;
        button.textContent = button.dataset.originalText || 'Submit';
    }
}

function showConfirm(title, message, onConfirm, onCancel = null) {
    let modal = getElement('confirm-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'confirm-modal';
        modal.className = 'modal';
        modal.setAttribute('data-modal', '');
        document.body.appendChild(modal);
    }

    modal.innerHTML = `
        <div class="modal-content" style="max-width: 400px; background: white; border: 2px solid #339af0; border-radius: 8px;">
            <div class="modal-header" style="padding: 20px; border-bottom: 2px solid #339af0;">
                <h5 style="margin: 0; font-size: 1.3rem; color: #000000;">${encodeHTML(title)}</h5>
                <button class="btn-close" data-close-modal="confirm-modal" style="background: none; border: none; color: #000000; cursor: pointer;">×</button>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <p style="color: #000000;">${encodeHTML(message)}</p>
            </div>
            <div class="modal-footer" style="padding: 20px; display: flex; gap: 10px; justify-content: flex-end; border-top: 2px solid #339af0;">
                <button id="confirm-cancel" class="btn" style="background: #666666; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">Cancel</button>
                <button id="confirm-ok" class="btn" style="background: #339af0; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">Confirm</button>
            </div>
        </div>
    `;

    const confirmBtn = getElement('confirm-ok');
    const cancelBtn = getElement('confirm-cancel');

    confirmBtn.addEventListener('click', function() {
        closeModal('confirm-modal');
        if (onConfirm) onConfirm();
    });

    cancelBtn.addEventListener('click', function() {
        closeModal('confirm-modal');
        if (onCancel) onCancel();
    });

    openModal('confirm-modal');
}

function sortTable(tableId, columnIndex, direction = 'asc') {
    const table = getElement(tableId);
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const isNumeric = !isNaN(rows[0].cells[columnIndex].textContent);

    rows.sort((a, b) => {
        const aValue = a.cells[columnIndex].textContent.trim();
        const bValue = b.cells[columnIndex].textContent.trim();

        if (isNumeric) {
            return direction === 'asc'
                ? parseFloat(aValue) - parseFloat(bValue)
                : parseFloat(bValue) - parseFloat(aValue);
        } else {
            return direction === 'asc'
                ? aValue.localeCompare(bValue)
                : bValue.localeCompare(aValue);
        }
    });

    const tbody = table.querySelector('tbody');
    rows.forEach(row => tbody.appendChild(row));

    table.querySelectorAll('th').forEach((th, index) => {
        th.classList.remove('sort-asc', 'sort-desc');
        if (index === columnIndex) {
            th.classList.add(`sort-${direction}`);
        }
    });
}

function filterTable(tableId, searchText, columnIndexes = null) {
    const table = getElement(tableId);
    if (!table) return;

    const rows = table.querySelectorAll('tbody tr');
    const search = searchText.toLowerCase();

    rows.forEach(row => {
        let matches = false;

        if (columnIndexes) {
            matches = columnIndexes.some(index => {
                const cell = row.cells[index];
                return cell && cell.textContent.toLowerCase().includes(search);
            });
        } else {
            matches = row.textContent.toLowerCase().includes(search);
        }

        row.style.display = matches ? '' : 'none';
    });
}

function setupTableSorting(tableId) {
    const table = getElement(tableId);
    if (!table) return;

    const headers = table.querySelectorAll('thead th');
    let currentSort = { column: -1, direction: 'asc' };

    headers.forEach((header, index) => {
        header.style.cursor = 'pointer';
        header.addEventListener('click', function() {
            if (currentSort.column === index) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.column = index;
                currentSort.direction = 'asc';
            }
            sortTable(tableId, index, currentSort.direction);
        });
    });
}

function setupTableSearch(searchInputId, tableId, columnIndexes = null) {
    const searchInput = getElement(searchInputId);
    if (!searchInput) return;

    searchInput.addEventListener('keyup', function() {
        filterTable(tableId, this.value, columnIndexes);
    });
}

function saveToStorage(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch (e) {
        console.error('Local storage error:', e);
    }
}

function getFromStorage(key, defaultValue = null) {
    try {
        const value = localStorage.getItem(key);
        return value ? JSON.parse(value) : defaultValue;
    } catch (e) {
        console.error('Local storage error:', e);
        return defaultValue;
    }
}

function removeFromStorage(key) {
    try {
        localStorage.removeItem(key);
    } catch (e) {
        console.error('Local storage error:', e);
    }
}

function clearStorage() {
    try {
        localStorage.clear();
    } catch (e) {
        console.error('Local storage error:', e);
    }
}

async function fetchData(url, options = {}) {
    try {
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    } catch (error) {
        console.error('Fetch error:', error);
        showToast('Error loading data', 'error');
        throw error;
    }
}

async function submitData(url, data, options = {}) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            body: JSON.stringify(data),
            ...options
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    } catch (error) {
        console.error('Submit error:', error);
        showToast('Error submitting data', 'error');
        throw error;
    }
}

function setupMobileMenu(toggleBtnId, menuId) {
    const toggleBtn = getElement(toggleBtnId);
    const menu = getElement(menuId);

    if (toggleBtn && menu) {
        toggleBtn.addEventListener('click', function() {
            toggleClass(menu, 'show');
            toggleClass(this, 'active');
        });

        document.addEventListener('click', function(e) {
            if (!menu.contains(e.target) && !toggleBtn.contains(e.target)) {
                removeClass(menu, 'show');
                removeClass(toggleBtn, 'active');
            }
        });
    }
}

function formatDate(date, format = 'YYYY-MM-DD') {
    const d = new Date(date);
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    const seconds = String(d.getSeconds()).padStart(2, '0');

    return format
        .replace('YYYY', year)
        .replace('MM', month)
        .replace('DD', day)
        .replace('HH', hours)
        .replace('mm', minutes)
        .replace('ss', seconds);
}

function getTimeAgo(date) {
    const d = new Date(date);
    const now = new Date();
    const seconds = Math.floor((now - d) / 1000);

    if (seconds < 60) return 'just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)} minutes ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)} hours ago`;
    if (seconds < 604800) return `${Math.floor(seconds / 86400)} days ago`;
    return formatDate(d, 'MM/DD/YYYY');
}

function addAnimationStyles() {
    if (!getElement('animation-styles')) {
        const style = document.createElement('style');
        style.id = 'animation-styles';
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            
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
            
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    addAnimationStyles();
    initializeModals();
});