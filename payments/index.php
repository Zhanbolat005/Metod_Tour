<?php
/**
 * Раздел "Оплаты" — управление оплатами, привязанными к бронированиям
 * Показывает сумму тура, сколько оплачено и остаток
 */
require_once __DIR__ . '/../db_connect.php';

// ========= AJAX-ЗАПРОСЫ =========
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json; charset=utf-8');

    $action = req_val('action');

    try {
        switch ($action) {
            // Получить данные оплаты
            case 'get':
                $id = (int)get_val('id', 0);
                $stmt = $pdo->prepare("SELECT * FROM tolem WHERE tolem_id = ?");
                $stmt->execute([$id]);
                $payment = $stmt->fetch();
                echo json_encode($payment ? ['success' => true, 'data' => $payment] : ['success' => false, 'message' => 'Оплата не найдена']);
                break;

            // Получить информацию о брони для формы оплаты
            case 'get_booking_info':
                $bronId = (int)get_val('bron_id', 0);
                $stmt = $pdo->prepare("
                    SELECT b.bron_id, b.adam_san, t.price AS tour_price, t.tour_name,
                           k.client_full_name,
                           COALESCE(SUM(p.tolem_sum), 0) AS paid
                    FROM bron b
                    LEFT JOIN tour t ON b.tour_id = t.tour_id
                    LEFT JOIN klient k ON b.client_id = k.client_id
                    LEFT JOIN tolem p ON b.bron_id = p.bron_id
                    WHERE b.bron_id = ?
                    GROUP BY b.bron_id
                ");
                $stmt->execute([$bronId]);
                $info = $stmt->fetch();
                if ($info) {
                    $total = $info['tour_price'] * $info['adam_san'];
                    $info['total_price'] = $total;
                    $info['remaining'] = $total - $info['paid'];
                    echo json_encode(['success' => true, 'data' => $info]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Бронь не найдена']);
                }
                break;

            // Добавить оплату
            case 'add':
                $bronId = (int)post_val('bron_id', 0);
                $date = trim(post_val('tolem_kuni'));
                $sum = (int)post_val('tolem_sum', 0);
                $type = trim(post_val('tolem_turi'));

                if ($bronId <= 0 || empty($date) || $sum <= 0 || empty($type)) {
                    echo json_encode(['success' => false, 'message' => 'Заполните все поля корректно']);
                    break;
                }

                // Проверка: не превышает ли оплата остаток
                $stmt = $pdo->prepare("
                    SELECT (t.price * b.adam_san) - COALESCE(SUM(p.tolem_sum), 0) AS remaining
                    FROM bron b
                    LEFT JOIN tour t ON b.tour_id = t.tour_id
                    LEFT JOIN tolem p ON b.bron_id = p.bron_id
                    WHERE b.bron_id = ?
                    GROUP BY b.bron_id
                ");
                $stmt->execute([$bronId]);
                $remainingInfo = $stmt->fetch();

                if ($remainingInfo && $sum > $remainingInfo['remaining']) {
                    echo json_encode(['success' => false, 'message' => 'Сумма оплаты превышает остаток (' . number_format($remainingInfo['remaining'], 0, '.', ' ') . ' ₸)']);
                    break;
                }

                $stmt = $pdo->prepare("INSERT INTO tolem (bron_id, tolem_kuni, tolem_sum, tolem_turi) VALUES (?, ?, ?, ?)");
                $stmt->execute([$bronId, $date, $sum, $type]);
                echo json_encode(['success' => true, 'message' => 'Оплата добавлена']);
                break;

            // Обновить оплату
            case 'edit':
                $id = (int)post_val('tolem_id', 0);
                $bronId = (int)post_val('bron_id', 0);
                $date = trim(post_val('tolem_kuni'));
                $sum = (int)post_val('tolem_sum', 0);
                $type = trim(post_val('tolem_turi'));

                if ($id <= 0 || $bronId <= 0 || empty($date) || $sum <= 0 || empty($type)) {
                    echo json_encode(['success' => false, 'message' => 'Заполните все поля']);
                    break;
                }

                $stmt = $pdo->prepare("UPDATE tolem SET bron_id = ?, tolem_kuni = ?, tolem_sum = ?, tolem_turi = ? WHERE tolem_id = ?");
                $stmt->execute([$bronId, $date, $sum, $type, $id]);
                echo json_encode(['success' => true, 'message' => 'Оплата обновлена']);
                break;

            // Удалить оплату
            case 'delete':
                $id = (int)post_val('tolem_id', 0);
                if ($id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Некорректный ID']);
                    break;
                }
                $stmt = $pdo->prepare("DELETE FROM tolem WHERE tolem_id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Оплата удалена']);
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
    // Все оплаты с JOIN-ами, общей суммой тура, суммой оплат по брони и остатком
    $payments = $pdo->query("
        SELECT p.*, 
               b.bron_id, b.adam_san,
               k.client_full_name,
               t.tour_name, t.price AS tour_price,
               (t.price * b.adam_san) AS tour_total,
               COALESCE((SELECT SUM(tolem_sum) FROM tolem WHERE bron_id = p.bron_id), 0) AS total_paid_for_bron,
               ((t.price * b.adam_san) - COALESCE((SELECT SUM(tolem_sum) FROM tolem WHERE bron_id = p.bron_id), 0)) AS remaining_balance
        FROM tolem p
        LEFT JOIN bron b ON p.bron_id = b.bron_id
        LEFT JOIN klient k ON b.client_id = k.client_id
        LEFT JOIN tour t ON b.tour_id = t.tour_id
        ORDER BY p.tolem_id DESC
    ")->fetchAll();

    // Брони для выбора в форме (с информацией о клиенте, туре, суммах и остатке)
    $bookings = $pdo->query("
        SELECT b.bron_id, k.client_full_name, t.tour_name, t.price, b.adam_san,
               (t.price * b.adam_san) AS total_sum,
               COALESCE((SELECT SUM(tolem_sum) FROM tolem WHERE bron_id = b.bron_id), 0) AS paid_sum,
               ((t.price * b.adam_san) - COALESCE((SELECT SUM(tolem_sum) FROM tolem WHERE bron_id = b.bron_id), 0)) AS remaining_sum
        FROM bron b
        LEFT JOIN klient k ON b.client_id = k.client_id
        LEFT JOIN tour t ON b.tour_id = t.tour_id
        ORDER BY b.bron_id DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $payments = $bookings = [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оплаты — TourManager</title>
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
                <a href="/clients/index.php" class="nav-link"><span class="icon">👤</span> Клиенты</a>
                <a href="/staff/index.php" class="nav-link"><span class="icon">💼</span> Сотрудники</a>
                <div class="nav-section-title">Бизнес</div>
                <a href="/bookings/index.php" class="nav-link"><span class="icon">📋</span> Брони</a>
                <a href="/payments/index.php" class="nav-link active"><span class="icon">💳</span> Оплаты</a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <button class="mobile-toggle" onclick="toggleSidebar()">☰</button>
                    <h2>💳 Оплаты</h2>
                    <p>Учёт платежей, остатков и финансовой аналитики</p>
                </div>
                <div style="display:flex; gap:10px;">
                    <button class="btn btn-secondary" onclick="exportPaymentsCSV()">📥 Экспорт в CSV</button>
                    <button class="btn btn-primary" onclick="openAddModal()">+ Добавить оплату</button>
                </div>
            </div>

            <div class="page-body">
                <div class="table-container">
                    <!-- Панель фильтрации -->
                    <div class="table-toolbar">
                        <div class="filter-bar">
                            <div class="search-box" style="flex: 1.5; min-width: 200px;">
                                <input type="text" id="searchPayments" placeholder="Поиск по клиенту или туру..." oninput="applyPaymentFilters()">
                            </div>

                            <div class="filter-group">
                                <label for="filterType">Способ:</label>
                                <select id="filterType" class="filter-control" onchange="applyPaymentFilters()">
                                    <option value="">Все способы</option>
                                    <option value="наличные">💵 Наличные</option>
                                    <option value="қолма">💵 Қолма-қол</option>
                                    <option value="карта">💳 Карта</option>
                                    <option value="перевод">🏦 Банковский перевод</option>
                                    <option value="онлайн">🌐 Онлайн-оплата</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label>Дата с:</label>
                                <input type="date" id="filterDateFrom" class="filter-control" onchange="applyPaymentFilters()">
                            </div>

                            <div class="filter-group">
                                <label>по:</label>
                                <input type="date" id="filterDateTo" class="filter-control" onchange="applyPaymentFilters()">
                            </div>

                            <button class="btn btn-secondary btn-sm" onclick="resetPaymentFilters()">Сброс</button>
                        </div>
                    </div>

                    <div class="table-scroll">
                        <table id="paymentsTable">
                            <thead>
                                <tr>
                                    <th class="sortable" data-sort-type="number">ID</th>
                                    <th class="sortable" data-sort-type="number">Бронь</th>
                                    <th class="sortable" data-sort-type="string">Клиент</th>
                                    <th class="sortable" data-sort-type="string">Тур</th>
                                    <th class="sortable" data-sort-type="number">Сумма тура</th>
                                    <th class="sortable" data-sort-type="number">Платёж</th>
                                    <th class="sortable" data-sort-type="number">Всего оплачено</th>
                                    <th class="sortable" data-sort-type="number">Остаток</th>
                                    <th class="sortable" data-sort-type="date">Дата</th>
                                    <th>Способ</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="paymentsTableBody">
                                <?php if (empty($payments)): ?>
                                    <tr class="empty-row">
                                        <td colspan="11">
                                            <div class="empty-state">
                                                <div class="empty-icon">💳</div>
                                                <h4>Нет оплат</h4>
                                                <p>Добавьте первую оплату</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($payments as $p): 
                                        $tourTotal = (float)$p['tour_total'];
                                        $paidBron = (float)$p['total_paid_for_bron'];
                                        $rem = (float)$p['remaining_balance'];
                                        $isFull = ($rem <= 0);
                                    ?>
                                        <tr data-id="<?= $p['tolem_id'] ?>" 
                                            data-date="<?= $p['tolem_kuni'] ?>" 
                                            data-sum="<?= $p['tolem_sum'] ?>"
                                            data-type="<?= htmlspecialchars(mb_strtolower(!empty($p['tolem_turi']) ? $p['tolem_turi'] : '')) ?>">
                                            <td>#<?= $p['tolem_id'] ?></td>
                                            <td><span style="font-family:monospace; color:var(--primary-light);">#<?= $p['bron_id'] ?></span></td>
                                            <td style="font-weight:600; color:var(--text-primary);"><?= htmlspecialchars(!empty($p['client_full_name']) ? $p['client_full_name'] : '—') ?></td>
                                            <td><?= htmlspecialchars(!empty($p['tour_name']) ? $p['tour_name'] : '—') ?></td>
                                            <td><?= number_format($tourTotal, 0, '.', ' ') ?> ₸</td>
                                            <td style="color:var(--secondary); font-weight:700;"><?= number_format($p['tolem_sum'], 0, '.', ' ') ?> ₸</td>
                                            <td><?= number_format($paidBron, 0, '.', ' ') ?> ₸</td>
                                            <td>
                                                <!-- Цветной индикатор остатка -->
                                                <?php if ($isFull): ?>
                                                    <span class="payment-badge paid">0 ₸ (Оплачено)</span>
                                                <?php else: ?>
                                                    <span class="payment-badge unpaid"><?= number_format($rem, 0, '.', ' ') ?> ₸</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $p['tolem_kuni'] ? date('d.m.Y', strtotime($p['tolem_kuni'])) : '—' ?></td>
                                            <td><span class="status-badge подтверждена"><?= htmlspecialchars($p['tolem_turi']) ?></span></td>
                                            <td>
                                                <div class="actions-cell">
                                                    <button class="btn btn-secondary btn-sm" title="Редактировать" onclick="openEditModal(<?= $p['tolem_id'] ?>)">✏️</button>
                                                    <button class="btn btn-danger btn-sm" title="Удалить" onclick="deletePayment(<?= $p['tolem_id'] ?>)">🗑️</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Итоговая строка (Сумма всех оплат за выбранный период) -->
                    <div class="table-summary-bar">
                        <span class="summary-title">Итого оплат за выбранный период:</span>
                        <span class="summary-value" id="paymentsTotalSum">0 ₸</span>
                    </div>

                    <!-- Пагинация -->
                    <div class="pagination-container" id="paymentsPagination"></div>
                </div>
            </div>
        </main>
    </div>

    <!-- Модальное окно оплаты -->
    <div class="modal-overlay" id="paymentModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="paymentModalTitle">Добавить оплату</h3>
                <button class="modal-close">✕</button>
            </div>
            <div class="modal-body">
                <form id="paymentForm" onsubmit="event.preventDefault(); savePayment();">
                    <input type="hidden" id="edit_tolem_id" value="">

                    <div class="form-group">
                        <label for="bron_id">Бронирование *</label>
                        <select class="form-control" id="bron_id" required onchange="onBookingChange()">
                            <option value="">— Выберите бронь —</option>
                            <?php foreach ($bookings as $b): ?>
                                <option value="<?= $b['bron_id'] ?>" data-remaining="<?= $b['remaining_sum'] ?>">
                                    #<?= $b['bron_id'] ?> — <?= htmlspecialchars(!empty($b['client_full_name']) ? $b['client_full_name'] : '?') ?> / <?= htmlspecialchars(!empty($b['tour_name']) ? $b['tour_name'] : '?') ?> (Остаток: <?= number_format($b['remaining_sum'], 0, '.', ' ') ?> ₸)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-error"></div>
                    </div>

                    <!-- Информация о стоимости и оплатах выбранной брони -->
                    <div id="bookingInfoBlock" style="display:none; margin-bottom:18px; background:var(--bg-input); padding:12px 16px; border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                        <div class="info-row">
                            <span class="label">Стоимость тура (за 1 чел.):</span>
                            <span class="value" id="infoTourPrice">—</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Количество человек:</span>
                            <span class="value" id="infoPeople">—</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Итого к оплате:</span>
                            <span class="value" id="infoTotal">—</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Уже оплачено:</span>
                            <span class="value positive" id="infoPaid">—</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Остаток к оплате:</span>
                            <span class="value negative" id="infoRemaining">—</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="tolem_sum">Сумма платежа (₸) *</label>
                            <input type="number" class="form-control" id="tolem_sum" placeholder="50000" min="1" required>
                            <div class="form-error"></div>
                        </div>
                        <div class="form-group">
                            <label for="tolem_kuni">Дата оплаты *</label>
                            <input type="date" class="form-control" id="tolem_kuni" required>
                            <div class="form-error"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="tolem_turi">Способ оплаты *</label>
                        <select class="form-control" id="tolem_turi" required>
                            <option value="">— Выберите способ —</option>
                            <option value="наличные">💵 Наличные</option>
                            <option value="қолма-қол">💵 Қолма-қол</option>
                            <option value="карта">💳 Карта</option>
                            <option value="перевод">🏦 Банковский перевод</option>
                            <option value="онлайн">🌐 Онлайн-оплата</option>
                        </select>
                        <div class="form-error"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="Modal.close('paymentModal')">Отмена</button>
                <button class="btn btn-primary" id="savePaymentBtn" onclick="savePayment()">Сохранить</button>
            </div>
        </div>
    </div>

    <div id="toast-container" class="toast-container"></div>
    <script src="/assets/js/main.js"></script>
    <script>
        let paymentsPaginator;

        document.addEventListener('DOMContentLoaded', () => {
            paymentsPaginator = new TablePaginator({
                tableBodySelector: '#paymentsTableBody',
                paginationContainerSelector: '#paymentsPagination',
                pageSize: 15
            });

            setupTableSorting('#paymentsTable', () => {
                paymentsPaginator.refreshRows();
                recalcTotalSum();
            });

            recalcTotalSum();
        });

        // Пересчёт итоговой суммы всех видимых оплат
        function recalcTotalSum() {
            let total = 0;
            const rows = document.querySelectorAll('#paymentsTableBody tr[data-id]');
            rows.forEach(row => {
                if (row.dataset.filtered !== 'false' && row.dataset.sum) {
                    total += parseFloat(row.dataset.sum) || 0;
                }
            });
            const sumEl = document.getElementById('paymentsTotalSum');
            if (sumEl) sumEl.textContent = formatMoney(total);
        }

        function openAddModal() {
            document.getElementById('paymentModalTitle').textContent = 'Добавить оплату';
            document.getElementById('paymentForm').reset();
            document.getElementById('edit_tolem_id').value = '';
            document.getElementById('bookingInfoBlock').style.display = 'none';
            document.getElementById('tolem_kuni').value = new Date().toISOString().split('T')[0];
            Validator.clearErrors('paymentForm');
            Modal.open('paymentModal');
        }

        async function openEditModal(id) {
            const result = await ajaxRequest('/payments/index.php', { action: 'get', id }, 'GET');
            if (result.success) {
                document.getElementById('paymentModalTitle').textContent = 'Редактировать оплату';
                document.getElementById('edit_tolem_id').value = result.data.tolem_id;
                document.getElementById('bron_id').value = result.data.bron_id;
                document.getElementById('tolem_sum').value = result.data.tolem_sum;
                document.getElementById('tolem_kuni').value = result.data.tolem_kuni;
                document.getElementById('tolem_turi').value = result.data.tolem_turi;
                onBookingChange();
                Validator.clearErrors('paymentForm');
                Modal.open('paymentModal');
            } else {
                ToastManager.show(result.message, 'error');
            }
        }

        // При выборе брони — показать стоимость, оплаченное и остаток
        async function onBookingChange() {
            const bronId = document.getElementById('bron_id').value;
            const infoBlock = document.getElementById('bookingInfoBlock');

            if (!bronId) {
                infoBlock.style.display = 'none';
                return;
            }

            const result = await ajaxRequest('/payments/index.php', { action: 'get_booking_info', bron_id: bronId }, 'GET');
            if (result.success) {
                const d = result.data;
                document.getElementById('infoTourPrice').textContent = formatMoney(d.tour_price);
                document.getElementById('infoPeople').textContent = d.adam_san + ' чел.';
                document.getElementById('infoTotal').textContent = formatMoney(d.total_price);
                document.getElementById('infoPaid').textContent = formatMoney(d.paid);
                document.getElementById('infoRemaining').textContent = formatMoney(d.remaining);

                const remEl = document.getElementById('infoRemaining');
                remEl.className = d.remaining > 0 ? 'value negative' : 'value positive';

                // Если поле суммы пустое и есть остаток, можно предложить остаток
                const sumInput = document.getElementById('tolem_sum');
                if (!sumInput.value && d.remaining > 0) {
                    sumInput.placeholder = d.remaining;
                }

                infoBlock.style.display = 'block';
            }
        }

        async function savePayment() {
            const errors = Validator.validateForm({
                'bron_id': [{ type: 'required', name: 'Бронирование' }],
                'tolem_sum': [{ type: 'required', name: 'Сумма платежа' }, { type: 'positiveNumber', name: 'Сумма платежа' }],
                'tolem_kuni': [{ type: 'required', name: 'Дата оплаты' }],
                'tolem_turi': [{ type: 'required', name: 'Способ оплаты' }],
            });

            if (errors.length > 0) {
                ToastManager.show(errors[0], 'error');
                return;
            }

            const editId = document.getElementById('edit_tolem_id').value;
            const data = {
                action: editId ? 'edit' : 'add',
                bron_id: document.getElementById('bron_id').value,
                tolem_sum: document.getElementById('tolem_sum').value,
                tolem_kuni: document.getElementById('tolem_kuni').value,
                tolem_turi: document.getElementById('tolem_turi').value,
            };
            if (editId) data.tolem_id = editId;

            const saveBtn = document.getElementById('savePaymentBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner"></span> Сохранение...';

            const result = await ajaxRequest('/payments/index.php', data);
            saveBtn.disabled = false;
            saveBtn.textContent = 'Сохранить';

            if (result.success) {
                ToastManager.show(result.message, 'success');
                Modal.close('paymentModal');
                setTimeout(() => location.reload(), 500);
            } else {
                ToastManager.show(result.message, 'error');
            }
        }

        async function deletePayment(id) {
            if (!confirmDelete('Вы уверены, что хотите удалить эту оплату?')) return;
            const result = await ajaxRequest('/payments/index.php', { action: 'delete', tolem_id: id });
            if (result.success) {
                ToastManager.show(result.message, 'success');
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (row) {
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(20px)';
                    row.style.transition = 'all 0.3s ease';
                    setTimeout(() => {
                        row.remove();
                        if (paymentsPaginator) paymentsPaginator.refreshRows();
                        recalcTotalSum();
                    }, 300);
                }
            } else {
                ToastManager.show(result.message, 'error');
            }
        }

        // Фильтрация оплат по поиску, способу и дате
        function applyPaymentFilters() {
            const query = document.getElementById('searchPayments').value.toLowerCase().trim();
            const typeFilter = document.getElementById('filterType').value.toLowerCase().trim();
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;

            if (paymentsPaginator) {
                paymentsPaginator.setFilter(row => {
                    const text = row.textContent.toLowerCase();
                    const rowType = (row.dataset.type || '').toLowerCase();
                    const rowDate = row.dataset.date || '';

                    if (query && !text.includes(query)) return false;
                    if (typeFilter && !rowType.includes(typeFilter)) return false;
                    if (dateFrom && rowDate && rowDate < dateFrom) return false;
                    if (dateTo && rowDate && rowDate > dateTo) return false;

                    return true;
                });
            }
            recalcTotalSum();
        }

        function resetPaymentFilters() {
            document.getElementById('searchPayments').value = '';
            document.getElementById('filterType').value = '';
            document.getElementById('filterDateFrom').value = '';
            document.getElementById('filterDateTo').value = '';
            applyPaymentFilters();
        }

        // Экспорт оплат в CSV/Excel (с UTF-8 BOM)
        function exportPaymentsCSV() {
            const headers = ['ID', 'Бронь ID', 'Клиент', 'Тур', 'Сумма тура (₸)', 'Сумма платежа (₸)', 'Всего оплачено (₸)', 'Остаток (₸)', 'Дата оплаты', 'Способ'];
            const rows = [];
            const trs = document.querySelectorAll('#paymentsTableBody tr[data-id]');

            trs.forEach(tr => {
                if (tr.style.display !== 'none') {
                    const cells = tr.querySelectorAll('td');
                    if (cells.length >= 10) {
                        rows.push([
                            cells[0].innerText.replace('#', '').trim(),
                            cells[1].innerText.replace('#', '').trim(),
                            cells[2].innerText.trim(),
                            cells[3].innerText.trim(),
                            cells[4].innerText.replace(/[^\d]/g, '').trim(),
                            cells[5].innerText.replace(/[^\d]/g, '').trim(),
                            cells[6].innerText.replace(/[^\d]/g, '').trim(),
                            cells[7].innerText.trim(),
                            cells[8].innerText.trim(),
                            cells[9].innerText.trim()
                        ]);
                    }
                }
            });

            if (rows.length === 0) {
                ToastManager.show('Нет данных для экспорта', 'info');
                return;
            }

            exportToCSV('Оплаты_' + new Date().toISOString().split('T')[0], headers, rows);
            ToastManager.show('Файл оплат CSV успешно сформирован', 'success');
        }
    </script>
</body>
</html>
