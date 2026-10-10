/**
 * Главный JS-файл приложения бронирования туров
 * Содержит: тосты, модальные окна, AJAX-запросы, валидацию форм
 */

// ========= TOAST-УВЕДОМЛЕНИЯ =========
const ToastManager = {
    container: null,

    init() {
        this.container = document.getElementById('toast-container');
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
    },

    /**
     * Показать уведомление
     * @param {string} message - текст
     * @param {string} type - 'success' | 'error' | 'info'
     * @param {number} duration - миллисекунды
     */
    show(message, type = 'info', duration = 3500) {
        if (!this.container) this.init();

        const icons = { success: '✓', error: '✕', info: 'ℹ' };
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `<span style="font-size:18px;">${icons[type] || ''}</span> ${message}`;
        this.container.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('removing');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
};

// ========= МОДАЛЬНЫЕ ОКНА =========
const Modal = {
    /**
     * Открыть модальное окно
     * @param {string} id - ID элемента .modal-overlay
     */
    open(id) {
        const overlay = document.getElementById(id);
        if (overlay) {
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    },

    /**
     * Закрыть модальное окно
     * @param {string} id - ID элемента .modal-overlay
     */
    close(id) {
        const overlay = document.getElementById(id);
        if (overlay) {
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    },

    /**
     * Инициализация: закрытие по клику на оверлей и кнопкам
     */
    init() {
        // Закрытие по клику на оверлей (вне модалки)
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // Кнопки закрытия
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', () => {
                const overlay = btn.closest('.modal-overlay');
                if (overlay) {
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // Закрытие по Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(overlay => {
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }
        });
    }
};

// ========= AJAX-УТИЛИТА =========
/**
 * Обёртка над fetch для AJAX-запросов
 * @param {string} url
 * @param {object} data - данные для отправки
 * @param {string} method - GET|POST
 * @returns {Promise<object>}
 */
async function ajaxRequest(url, data = {}, method = 'POST') {
    try {
        const options = {
            method,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        };

        if (method === 'POST') {
            // Отправляем как FormData для совместимости с PHP $_POST
            const formData = new FormData();
            for (const [key, value] of Object.entries(data)) {
                formData.append(key, value);
            }
            options.body = formData;
        } else {
            // Для GET — добавляем параметры в URL
            const params = new URLSearchParams(data);
            url += '?' + params.toString();
        }

        const response = await fetch(url, options);
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('AJAX Error:', error);
        return { success: false, message: 'Ошибка соединения с сервером' };
    }
}

// ========= ВАЛИДАЦИЯ ФОРМ =========
const Validator = {
    /**
     * Показать ошибку под полем
     */
    showError(el, message) {
        if (!el) return;
        el.classList.add('is-invalid');
        const formGroup = el.closest('.form-group');
        if (formGroup) {
            let errorEl = formGroup.querySelector('.form-error');
            if (!errorEl) {
                errorEl = document.createElement('div');
                errorEl.className = 'form-error';
                formGroup.appendChild(errorEl);
            }
            errorEl.textContent = message;
            errorEl.classList.add('visible');
        }
    },

    /**
     * Очистить ошибку поля
     */
    clearFieldError(el) {
        if (!el) return;
        el.classList.remove('is-invalid');
        const formGroup = el.closest('.form-group');
        if (formGroup) {
            const errorEl = formGroup.querySelector('.form-error');
            if (errorEl) {
                errorEl.textContent = '';
                errorEl.classList.remove('visible');
            }
        }
    },

    /**
     * Проверить что поле не пустое
     */
    required(value, fieldName) {
        if (!value || value.toString().trim() === '') {
            return `Поле "${fieldName}" обязательно для заполнения`;
        }
        return null;
    },

    /**
     * Проверить email
     */
    email(value) {
        if (value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())) {
            return 'Введите корректный email (например, name@mail.kz)';
        }
        return null;
    },

    /**
     * Проверить что значение > 0
     */
    positiveNumber(value, fieldName) {
        if (value === '' || isNaN(value) || Number(value) <= 0) {
            return `"${fieldName}" должно быть числом больше 0`;
        }
        return null;
    },

    /**
     * Проверить телефон
     */
    phone(value) {
        if (value && !/^[\d\s\-\+\(\)]{7,25}$/.test(value.trim())) {
            return 'Введите корректный номер телефона (от 7 цифр)';
        }
        return null;
    },

    /**
     * Валидировать форму по правилам
     * @param {object} rules - { fieldId: [{ type: 'required', name: '...' }, ...] }
     * @returns {string[]} массив ошибок
     */
    validateForm(rules) {
        const errors = [];

        for (const [fieldId, fieldRules] of Object.entries(rules)) {
            const el = document.getElementById(fieldId);
            if (!el) continue;

            const value = el.value;
            let fieldError = null;

            for (const rule of fieldRules) {
                switch (rule.type) {
                    case 'required':
                        fieldError = this.required(value, rule.name);
                        break;
                    case 'email':
                        fieldError = this.email(value);
                        break;
                    case 'positiveNumber':
                        fieldError = this.positiveNumber(value, rule.name);
                        break;
                    case 'phone':
                        fieldError = this.phone(value);
                        break;
                }
                if (fieldError) break;
            }

            if (fieldError) {
                errors.push(fieldError);
                this.showError(el, fieldError);
            } else {
                this.clearFieldError(el);
            }
        }

        return errors;
    },

    /**
     * Очистить стили ошибок во всей форме
     */
    clearErrors(formId) {
        const form = document.getElementById(formId);
        if (form) {
            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            form.querySelectorAll('.form-error').forEach(el => {
                el.textContent = '';
                el.classList.remove('visible');
            });
        }
    }
};

// ========= БОКОВОЕ МЕНЮ (мобильная версия) =========
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    let backdrop = document.querySelector('.sidebar-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'sidebar-backdrop';
        backdrop.onclick = toggleSidebar;
        document.body.appendChild(backdrop);
    }

    if (sidebar) {
        const isOpen = sidebar.classList.toggle('open');
        backdrop.classList.toggle('active', isOpen);
    }
}

// ========= ФОРМАТИРОВАНИЕ =========
function formatMoney(value) {
    if (value === null || value === undefined || isNaN(value)) return '0 ₸';
    return Number(value).toLocaleString('ru-RU') + ' ₸';
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const parts = dateStr.split('-');
    if (parts.length === 3) {
        return `${parts[2]}.${parts[1]}.${parts[0]}`;
    }
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    return `${day}.${month}.${year}`;
}

// ========= ПОДТВЕРЖДЕНИЕ УДАЛЕНИЯ =========
function confirmDelete(message = 'Вы уверены, что хотите удалить эту запись?') {
    return confirm(message);
}

// ========= ЭКСПОРТ В CSV/EXCEL =========
/**
 * Экспорт данных в CSV с поддержкой кириллицы (UTF-8 BOM)
 * @param {string} filename - Имя скачиваемого файла
 * @param {string[]} headers - Заголовки столбцов
 * @param {Array<Array>} rows - Двумерный массив строк данных
 */
function exportToCSV(filename, headers, rows) {
    const escapeCell = (val) => {
        if (val === null || val === undefined) return '""';
        let str = val.toString().replace(/"/g, '""');
        return `"${str}"`;
    };

    let csvContent = '\uFEFF'; // BOM для правильного открытия UTF-8 в Excel
    csvContent += headers.map(escapeCell).join(';') + '\r\n';

    rows.forEach(row => {
        csvContent += row.map(escapeCell).join(';') + '\r\n';
    });

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', filename.endsWith('.csv') ? filename : filename + '.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

// ========= КЛИЕНТСКАЯ ПАГИНАЦИЯ ТАБЛИЦЫ =========
class TablePaginator {
    constructor(options) {
        this.tableBody = document.querySelector(options.tableBodySelector);
        this.container = document.querySelector(options.paginationContainerSelector);
        this.pageSize = options.pageSize || 15;
        this.currentPage = 1;
        this.allRows = [];
        this.visibleRows = [];
        this.init();
    }

    init() {
        if (!this.tableBody) return;
        this.refreshRows();
    }

    refreshRows() {
        if (!this.tableBody) return;
        // Собираем все строки данных (без empty-state)
        this.allRows = Array.from(this.tableBody.querySelectorAll('tr:not(.empty-row)'));
        this.visibleRows = this.allRows.filter(r => r.style.display !== 'none');
        this.render();
    }

    setFilter(filterFn) {
        this.allRows.forEach(row => {
            const match = filterFn(row);
            row.dataset.filtered = match ? 'true' : 'false';
        });
        this.visibleRows = this.allRows.filter(r => r.dataset.filtered !== 'false');
        this.currentPage = 1;
        this.render();
    }

    goToPage(page) {
        this.currentPage = page;
        this.render();
    }

    render() {
        const total = this.visibleRows.length;
        const totalPages = Math.ceil(total / this.pageSize) || 1;

        if (this.currentPage > totalPages) this.currentPage = totalPages;
        if (this.currentPage < 1) this.currentPage = 1;

        const startIdx = (this.currentPage - 1) * this.pageSize;
        const endIdx = startIdx + this.pageSize;

        // Показываем только строки текущей страницы из видимых
        this.allRows.forEach(row => {
            if (row.dataset.filtered === 'false') {
                row.style.display = 'none';
            } else {
                const idx = this.visibleRows.indexOf(row);
                if (idx >= startIdx && idx < endIdx) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });

        // Рендерим панель пагинации
        if (!this.container) return;

        if (total === 0) {
            this.container.innerHTML = '';
            return;
        }

        const showingFrom = Math.min(startIdx + 1, total);
        const showingTo = Math.min(endIdx, total);

        let paginationHtml = `
            <div class="pagination-info">
                Показано <strong>${showingFrom}–${showingTo}</strong> из <strong>${total}</strong>
            </div>
            <div class="pagination">
                <button class="page-btn" ${this.currentPage === 1 ? 'disabled' : ''} onclick="window.__activePaginator.goToPage(${this.currentPage - 1})">‹</button>
        `;

        for (let p = 1; p <= totalPages; p++) {
            // Ограничим видимые номера страниц, если страниц много
            if (totalPages > 7) {
                if (p !== 1 && p !== totalPages && Math.abs(p - this.currentPage) > 1) {
                    if (p === 2 || p === totalPages - 1) {
                        paginationHtml += `<span style="padding:0 4px; color:var(--text-muted);">…</span>`;
                    }
                    continue;
                }
            }
            paginationHtml += `
                <button class="page-btn ${p === this.currentPage ? 'active' : ''}" onclick="window.__activePaginator.goToPage(${p})">${p}</button>
            `;
        }

        paginationHtml += `
                <button class="page-btn" ${this.currentPage === totalPages ? 'disabled' : ''} onclick="window.__activePaginator.goToPage(${this.currentPage + 1})">›</button>
            </div>
        `;

        this.container.innerHTML = paginationHtml;
        window.__activePaginator = this;
    }
}

// ========= КЛИЕНТСКАЯ СОРТИРОВКА ТАБЛИЦ =========
function setupTableSorting(tableSelector, onSortedCallback) {
    const table = document.querySelector(tableSelector);
    if (!table) return;

    const headers = table.querySelectorAll('thead th.sortable');
    headers.forEach((th, colIdx) => {
        th.addEventListener('click', () => {
            const currentDir = th.classList.contains('sort-asc') ? 'asc' : (th.classList.contains('sort-desc') ? 'desc' : null);
            const newDir = currentDir === 'asc' ? 'desc' : 'asc';

            headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
            th.classList.add(newDir === 'asc' ? 'sort-asc' : 'sort-desc');

            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr:not(.empty-row)'));
            const type = th.dataset.sortType || 'string';

            rows.sort((a, b) => {
                const cellA = a.children[colIdx]?.innerText.trim() || '';
                const cellB = b.children[colIdx]?.innerText.trim() || '';

                let valA, valB;
                if (type === 'number') {
                    // Извлекаем числовые значения (удаляем пробелы, ₸, дн. и т.д.)
                    valA = parseFloat(cellA.replace(/[^\d.-]/g, '')) || 0;
                    valB = parseFloat(cellB.replace(/[^\d.-]/g, '')) || 0;
                } else if (type === 'date') {
                    const parseD = (s) => {
                        const parts = s.split('.');
                        return parts.length === 3 ? new Date(`${parts[2]}-${parts[1]}-${parts[0]}`).getTime() : 0;
                    };
                    valA = parseD(cellA);
                    valB = parseD(cellB);
                } else {
                    valA = cellA.toLowerCase();
                    valB = cellB.toLowerCase();
                }

                if (valA < valB) return newDir === 'asc' ? -1 : 1;
                if (valA > valB) return newDir === 'asc' ? 1 : -1;
                return 0;
            });

            rows.forEach(r => tbody.appendChild(r));
            if (onSortedCallback) onSortedCallback();
        });
    });
}

// ========= ИНИЦИАЛИЗАЦИЯ =========
document.addEventListener('DOMContentLoaded', () => {
    ToastManager.init();
    Modal.init();

    // Подсветка активного пункта навигации
    const currentPath = window.location.pathname;
    document.querySelectorAll('.nav-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href) {
            const cleanHref = href.split('?')[0];
            if (currentPath === cleanHref || (cleanHref !== '/index.php' && currentPath.startsWith(cleanHref.replace('/index.php', '')))) {
                link.classList.add('active');
            } else if (currentPath === '/' && cleanHref === '/index.php') {
                link.classList.add('active');
            }
        }
    });

    // Навешиваем очистку ошибок при вводе в поля
    document.querySelectorAll('input, select, textarea').forEach(input => {
        input.addEventListener('input', () => Validator.clearFieldError(input));
        input.addEventListener('change', () => Validator.clearFieldError(input));
    });
});

