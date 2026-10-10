<?php
/**
 * Раздел "Клиенты" — CRUD операции с таблицей klient
 * Поиск по имени или номеру паспорта
 */
require_once __DIR__ . '/../db_connect.php';

// ========= ОБРАБОТКА AJAX-ЗАПРОСОВ =========
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json; charset=utf-8');

    $action = req_val('action');

    try {
        switch ($action) {
            // Получить данные клиента
            case 'get':
                $id = (int)get_val('id', 0);
                $stmt = $pdo->prepare("SELECT * FROM klient WHERE client_id = ?");
                $stmt->execute([$id]);
                $client = $stmt->fetch();
                echo json_encode($client ? ['success' => true, 'data' => $client] : ['success' => false, 'message' => 'Клиент не найден']);
                break;

            // Добавить клиента
            case 'add':
                $name = trim(post_val('client_full_name'));
                $phone = trim(post_val('phone'));
                $email = trim(post_val('email'));
                $passport = trim(post_val('posport_num'));

                if (empty($name) || empty($phone) || empty($passport)) {
                    echo json_encode(['success' => false, 'message' => 'Заполните обязательные поля: ФИО, телефон, паспорт']);
                    break;
                }

                // Проверка уникальности паспорта
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM klient WHERE posport_num = ?");
                $stmt->execute([$passport]);
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Клиент с таким номером паспорта уже существует']);
                    break;
                }

                $stmt = $pdo->prepare("INSERT INTO klient (client_full_name, phone, email, posport_num) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $phone, $email, $passport]);
                echo json_encode(['success' => true, 'message' => 'Клиент успешно добавлен']);
                break;

            // Обновить клиента
            case 'edit':
                $id = (int)post_val('client_id', 0);
                $name = trim(post_val('client_full_name'));
                $phone = trim(post_val('phone'));
                $email = trim(post_val('email'));
                $passport = trim(post_val('posport_num'));

                if ($id <= 0 || empty($name) || empty($phone) || empty($passport)) {
                    echo json_encode(['success' => false, 'message' => 'Заполните обязательные поля']);
                    break;
                }

                // Проверка уникальности паспорта (кроме текущего клиента)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM klient WHERE posport_num = ? AND client_id != ?");
                $stmt->execute([$passport, $id]);
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Другой клиент с таким номером паспорта уже существует']);
                    break;
                }

                $stmt = $pdo->prepare("UPDATE klient SET client_full_name = ?, phone = ?, email = ?, posport_num = ? WHERE client_id = ?");
                $stmt->execute([$name, $phone, $email, $passport, $id]);
                echo json_encode(['success' => true, 'message' => 'Данные клиента обновлены']);
                break;

            // Удалить клиента
            case 'delete':
                $id = (int)post_val('client_id', 0);
                if ($id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Некорректный ID']);
                    break;
                }

                // Проверка: есть ли у клиента брони
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM bron WHERE client_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Невозможно удалить: у клиента есть бронирования']);
                    break;
                }

                $stmt = $pdo->prepare("DELETE FROM klient WHERE client_id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Клиент удалён']);
                break;

            // История бронирований клиента (сколько раз ездил, на какие туры, сколько потратил)
            case 'history':
                $id = (int)get_val('id', 0);
                $stmt = $pdo->prepare("SELECT * FROM klient WHERE client_id = ?");
                $stmt->execute([$id]);
                $client = $stmt->fetch();
                if (!$client) {
                    echo json_encode(['success' => false, 'message' => 'Клиент не найден']);
                    break;
                }

                $stmt = $pdo->prepare("
                    SELECT b.bron_id, b.bron_kun, b.adam_san, b.status,
                           t.tour_name, t.price AS tour_price, (t.price * b.adam_san) AS total_sum,
                           COALESCE((SELECT SUM(tolem_sum) FROM tolem WHERE bron_id = b.bron_id), 0) AS paid_sum
                    FROM bron b
                    LEFT JOIN tour t ON b.tour_id = t.tour_id
                    WHERE b.client_id = ?
                    ORDER BY b.bron_id DESC
                ");
                $stmt->execute([$id]);
                $bookings = $stmt->fetchAll();

                $totalTrips = count($bookings);
                $totalSpent = 0;
                foreach ($bookings as $b) {
                    $totalSpent += (float)$b['paid_sum'];
                }

                echo json_encode([
                    'success' => true,
                    'client' => $client,
                    'total_trips' => $totalTrips,
                    'total_spent' => $totalSpent,
                    'bookings' => $bookings
                ]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Неизвестное действие']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Ошибка БД: ' . $e->getMessage()]);
    }
    exit;
}

// ========= ОБЫЧНАЯ ЗАГРУЗКА =========
try {
    $clients = $pdo->query("SELECT * FROM klient ORDER BY client_id DESC")->fetchAll();
} catch (PDOException $e) {
    $clients = [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Клиенты — TourManager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="app-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">T</div>
                <div class="sidebar-brand">
                    <h1>TourManager</h1>
                    <span>Бронирование туров</span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section-title">Основное</div>
                <a href="/index.php" class="nav-link"><span class="icon">📊</span> Дашборд</a>
                <a href="/tours/index.php" class="nav-link"><span class="icon">🌍</span> Туры</a>
                <a href="/clients/index.php" class="nav-link active"><span class="icon">👤</span> Клиенты</a>
                <a href="/staff/index.php" class="nav-link"><span class="icon">💼</span> Сотрудники</a>
                <div class="nav-section-title">Бизнес</div>
                <a href="/bookings/index.php" class="nav-link"><span class="icon">📋</span> Брони</a>
                <a href="/payments/index.php" class="nav-link"><span class="icon">💳</span> Оплаты</a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <button class="mobile-toggle" onclick="toggleSidebar()">☰</button>
                    <h2>👤 Клиенты</h2>
                    <p>Управление клиентской базой</p>
                </div>
                <button class="btn btn-primary" onclick="openAddModal()">+ Добавить клиента</button>
            </div>

            <div class="page-body">
                <div class="table-container">
                    <div class="table-toolbar">
                        <div class="search-box">
                            <input type="text" id="searchClients" placeholder="Поиск по ФИО или номеру паспорта..." oninput="searchClients()">
                        </div>
                    </div>
                    <div class="table-scroll">
                        <table id="clientsTable">
                            <thead>
                                <tr>
                                    <th class="sortable" data-sort-type="number">ID</th>
                                    <th class="sortable" data-sort-type="string">ФИО</th>
                                    <th>Телефон</th>
                                    <th>Email</th>
                                    <th class="sortable" data-sort-type="string">Паспорт</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="clientsTableBody">
                                <?php if (empty($clients)): ?>
                                    <tr class="empty-row">
                                        <td colspan="6">
                                            <div class="empty-state">
                                                <div class="empty-icon">👤</div>
                                                <h4>Нет клиентов</h4>
                                                <p>Добавьте первого клиента</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($clients as $c): ?>
                                        <tr data-id="<?= $c['client_id'] ?>">
                                            <td>#<?= $c['client_id'] ?></td>
                                            <td>
                                                <a href="javascript:void(0)" onclick="openHistoryModal(<?= $c['client_id'] ?>)" 
                                                   style="font-weight:600; color:var(--text-primary); text-decoration:underline dashed rgba(108,99,255,0.5); cursor:pointer;" 
                                                   title="Посмотреть историю поездок">
                                                    <?= htmlspecialchars($c['client_full_name']) ?>
                                                </a>
                                            </td>
                                            <td><?= htmlspecialchars($c['phone']) ?></td>
                                            <td><?= htmlspecialchars(!empty($c['email']) ? $c['email'] : '—') ?></td>
                                            <td><span style="font-family:monospace;"><?= htmlspecialchars($c['posport_num']) ?></span></td>
                                            <td>
                                                <div class="actions-cell">
                                                    <button class="btn btn-secondary btn-sm" title="История поездок" onclick="openHistoryModal(<?= $c['client_id'] ?>)">📋</button>
                                                    <button class="btn btn-secondary btn-sm" title="Редактировать" onclick="openEditModal(<?= $c['client_id'] ?>)">✏️</button>
                                                    <button class="btn btn-danger btn-sm" title="Удалить" onclick="deleteClient(<?= $c['client_id'] ?>)">🗑️</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Пагинация -->
                    <div class="pagination-container" id="clientsPagination"></div>
                </div>
            </div>
        </main>
    </div>

    <!-- Модальное окно клиента (Добавление / Редактирование) -->
    <div class="modal-overlay" id="clientModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="clientModalTitle">Добавить клиента</h3>
                <button class="modal-close">✕</button>
            </div>
            <div class="modal-body">
                <form id="clientForm" onsubmit="event.preventDefault(); saveClient();">
                    <input type="hidden" id="edit_client_id" value="">

                    <div class="form-group">
                        <label for="client_full_name">ФИО *</label>
                        <input type="text" class="form-control" id="client_full_name" placeholder="Иванов Иван Иванович" maxlength="50" required>
                        <div class="form-error"></div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Телефон *</label>
                            <input type="tel" class="form-control" id="phone" placeholder="+7 (777) 123-45-67" maxlength="50" required>
                            <div class="form-error"></div>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" placeholder="client@mail.ru" maxlength="50">
                            <div class="form-error"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="posport_num">Номер паспорта *</label>
                        <input type="text" class="form-control" id="posport_num" placeholder="N12345678" maxlength="50" required>
                        <div class="form-error"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="Modal.close('clientModal')">Отмена</button>
                <button class="btn btn-primary" id="saveClientBtn" onclick="saveClient()">Сохранить</button>
            </div>
        </div>
    </div>

    <!-- Модальное окно: История бронирований клиента -->
    <div class="modal-overlay" id="clientHistoryModal">
        <div class="modal" style="max-width: 760px;">
            <div class="modal-header">
                <h3>История поездок: <span id="historyClientName" style="color:var(--primary-light);"></span></h3>
                <button class="modal-close">✕</button>
            </div>
            <div class="modal-body">
                <!-- Карточки статистики клиента -->
                <div class="history-stats">
                    <div class="history-stat-card">
                        <div class="history-stat-val" id="historyTripsCount">0</div>
                        <div class="history-stat-label">Всего поездок</div>
                    </div>
                    <div class="history-stat-card">
                        <div class="history-stat-val accent" id="historyTotalSpent">0 ₸</div>
                        <div class="history-stat-label">Всего оплачено</div>
                    </div>
                    <div class="history-stat-card">
                        <div class="history-stat-val" id="historyPassport" style="font-size:15px; font-family:monospace; padding-top:4px;">—</div>
                        <div class="history-stat-label">Паспорт</div>
                    </div>
                </div>

                <!-- Таблица бронирований этого клиента -->
                <h4 style="font-size:14px; margin-bottom:12px; color:var(--text-secondary);">Все бронирования клиента:</h4>
                <div class="table-scroll" style="max-height: 320px; overflow-y: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Тур</th>
                                <th>Дата</th>
                                <th>Чел.</th>
                                <th>Статус</th>
                                <th>Стоимость</th>
                                <th>Оплачено</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody">
                            <!-- Заполняется динамически -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="Modal.close('clientHistoryModal')">Закрыть</button>
            </div>
        </div>
    </div>

    <div id="toast-container" class="toast-container"></div>
    <script src="/assets/js/main.js"></script>
    <script>
        let clientsPaginator;

        document.addEventListener('DOMContentLoaded', () => {
            clientsPaginator = new TablePaginator({
                tableBodySelector: '#clientsTableBody',
                paginationContainerSelector: '#clientsPagination',
                pageSize: 15
            });

            setupTableSorting('#clientsTable', () => {
                clientsPaginator.refreshRows();
            });
        });

        function openAddModal() {
            document.getElementById('clientModalTitle').textContent = 'Добавить клиента';
            document.getElementById('clientForm').reset();
            document.getElementById('edit_client_id').value = '';
            Validator.clearErrors('clientForm');
            Modal.open('clientModal');
        }

        async function openEditModal(id) {
            const result = await ajaxRequest('/clients/index.php', { action: 'get', id }, 'GET');
            if (result.success) {
                document.getElementById('clientModalTitle').textContent = 'Редактировать клиента';
                document.getElementById('edit_client_id').value = result.data.client_id;
                document.getElementById('client_full_name').value = result.data.client_full_name;
                document.getElementById('phone').value = result.data.phone;
                document.getElementById('email').value = result.data.email || '';
                document.getElementById('posport_num').value = result.data.posport_num;
                Validator.clearErrors('clientForm');
                Modal.open('clientModal');
            } else {
                ToastManager.show(result.message, 'error');
            }
        }

        // Просмотр истории поездок клиента
        async function openHistoryModal(id) {
            const result = await ajaxRequest('/clients/index.php', { action: 'history', id }, 'GET');
            if (result.success) {
                const client = result.client;
                document.getElementById('historyClientName').textContent = client.client_full_name;
                document.getElementById('historyTripsCount').textContent = result.total_trips;
                document.getElementById('historyTotalSpent').textContent = formatMoney(result.total_spent);
                document.getElementById('historyPassport').textContent = client.posport_num;

                const tbody = document.getElementById('historyTableBody');
                if (result.bookings.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state" style="padding:30px;"><div class="empty-icon">🌍</div><h4>Нет поездок</h4><p>Клиент ещё не оформлял туры</p></div></td></tr>`;
                } else {
                    tbody.innerHTML = result.bookings.map(b => {
                        const sClass = getStatusClass(b.status);
                        const isFullyPaid = Number(b.paid_sum) >= Number(b.total_sum);
                        return `
                            <tr>
                                <td>#${b.bron_id}</td>
                                <td style="font-weight:600; color:var(--text-primary);">${escapeHtml(b.tour_name || '—')}</td>
                                <td>${formatDate(b.bron_kun)}</td>
                                <td>${b.adam_san} чел.</td>
                                <td><span class="status-badge ${sClass}">${escapeHtml(b.status || '—')}</span></td>
                                <td>${formatMoney(b.total_sum)}</td>
                                <td style="color:${isFullyPaid ? 'var(--secondary)' : 'var(--accent)'}; font-weight:600;">
                                    ${formatMoney(b.paid_sum)}
                                </td>
                            </tr>
                        `;
                    }).join('');
                }

                Modal.open('clientHistoryModal');
            } else {
                ToastManager.show(result.message || 'Ошибка загрузки истории', 'error');
            }
        }

        function getStatusClass(status) {
            const s = (status || '').toLowerCase().trim();
            if (['новая', 'new', 'күтуде', 'күтілуде'].includes(s)) return 'күтуде';
            if (['подтверждена', 'confirmed', 'расталды'].includes(s)) return 'расталды';
            if (['отменена', 'cancelled', 'бас тартылды', 'жойылды'].includes(s)) return 'бас-тартылды';
            if (['завершена', 'completed', 'аяқталды'].includes(s)) return 'аяқталды';
            return 'күтуде';
        }

        async function saveClient() {
            const errors = Validator.validateForm({
                'client_full_name': [{ type: 'required', name: 'ФИО' }],
                'phone': [{ type: 'required', name: 'Телефон' }, { type: 'phone' }],
                'posport_num': [{ type: 'required', name: 'Номер паспорта' }],
            });

            // Опциональная проверка email
            const emailVal = document.getElementById('email').value.trim();
            if (emailVal) {
                const emailErr = Validator.email(emailVal);
                if (emailErr) {
                    errors.push(emailErr);
                    Validator.showError(document.getElementById('email'), emailErr);
                }
            }

            if (errors.length > 0) {
                ToastManager.show(errors[0], 'error');
                return;
            }

            const editId = document.getElementById('edit_client_id').value;
            const data = {
                action: editId ? 'edit' : 'add',
                client_full_name: document.getElementById('client_full_name').value.trim(),
                phone: document.getElementById('phone').value.trim(),
                email: emailVal,
                posport_num: document.getElementById('posport_num').value.trim(),
            };
            if (editId) data.client_id = editId;

            const saveBtn = document.getElementById('saveClientBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner"></span> Сохранение...';

            const result = await ajaxRequest('/clients/index.php', data);
            saveBtn.disabled = false;
            saveBtn.textContent = 'Сохранить';

            if (result.success) {
                ToastManager.show(result.message, 'success');
                Modal.close('clientModal');
                setTimeout(() => location.reload(), 500);
            } else {
                ToastManager.show(result.message, 'error');
            }
        }

        async function deleteClient(id) {
            if (!confirmDelete('Вы уверены, что хотите удалить этого клиента?')) return;
            const result = await ajaxRequest('/clients/index.php', { action: 'delete', client_id: id });
            if (result.success) {
                ToastManager.show(result.message, 'success');
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (row) {
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(20px)';
                    row.style.transition = 'all 0.3s ease';
                    setTimeout(() => {
                        row.remove();
                        if (clientsPaginator) clientsPaginator.refreshRows();
                    }, 300);
                }
            } else {
                ToastManager.show(result.message, 'error');
            }
        }

        // Поиск клиентов в реальном времени через AJAX (debounce 300ms)
        let searchTimeout;
        function searchClients() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(async () => {
                const q = document.getElementById('searchClients').value.trim();
                const result = await ajaxRequest('/clients/index.php', { action: 'search', q }, 'GET');
                if (result.success) {
                    const tbody = document.getElementById('clientsTableBody');
                    if (result.data.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="6"><div class="empty-state"><div class="empty-icon">🔍</div><h4>Ничего не найдено</h4><p>Попробуйте изменить запрос</p></div></td></tr>`;
                    } else {
                        tbody.innerHTML = result.data.map(c => `
                            <tr data-id="${c.client_id}">
                                <td>#${c.client_id}</td>
                                <td>
                                    <a href="javascript:void(0)" onclick="openHistoryModal(${c.client_id})" 
                                       style="font-weight:600; color:var(--text-primary); text-decoration:underline dashed rgba(108,99,255,0.5); cursor:pointer;" 
                                       title="Посмотреть историю поездок">
                                        ${escapeHtml(c.client_full_name)}
                                    </a>
                                </td>
                                <td>${escapeHtml(c.phone)}</td>
                                <td>${escapeHtml(c.email || '—')}</td>
                                <td><span style="font-family:monospace;">${escapeHtml(c.posport_num)}</span></td>
                                <td>
                                    <div class="actions-cell">
                                        <button class="btn btn-secondary btn-sm" title="История поездок" onclick="openHistoryModal(${c.client_id})">📋</button>
                                        <button class="btn btn-secondary btn-sm" title="Редактировать" onclick="openEditModal(${c.client_id})">✏️</button>
                                        <button class="btn btn-danger btn-sm" title="Удалить" onclick="deleteClient(${c.client_id})">🗑️</button>
                                    </div>
                                </td>
                            </tr>
                        `).join('');
                    }
                    if (clientsPaginator) clientsPaginator.refreshRows();
                }
            }, 300);
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    </script>
</body>
</html>
