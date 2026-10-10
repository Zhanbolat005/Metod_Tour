<?php
/**
 * Раздел "Брони" — центральная бизнес-логика
 * CRUD с JOIN-ами, AJAX-обновление статуса, подстановка цены тура
 */
require_once __DIR__ . '/../db_connect.php';

// ========= AJAX-ЗАПРОСЫ =========
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json; charset=utf-8');

    $action = req_val('action');

    try {
        switch ($action) {
            // Получить данные брони для редактирования
            case 'get':
                $id = (int)get_val('id', 0);
                $stmt = $pdo->prepare("SELECT * FROM bron WHERE bron_id = ?");
                $stmt->execute([$id]);
                $booking = $stmt->fetch();
                echo json_encode($booking ? ['success' => true, 'data' => $booking] : ['success' => false, 'message' => 'Бронь не найдена']);
                break;

            // Получить цену тура (для AJAX-подстановки)
            case 'get_tour_price':
                $tourId = (int)get_val('tour_id', 0);
                $stmt = $pdo->prepare("SELECT price, tour_time FROM tour WHERE tour_id = ?");
                $stmt->execute([$tourId]);
                $tour = $stmt->fetch();
                if ($tour) {
                    echo json_encode(['success' => true, 'price' => $tour['price'], 'tour_time' => $tour['tour_time']]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Тур не найден']);
                }
                break;

            // Создать бронь
            case 'add':
                $clientId = (int)post_val('client_id', 0);
                $tourId = (int)post_val('tour_id', 0);
                $staffId = (int)post_val('kizmetker_id', 0);
                $date = trim(post_val('bron_kun'));
                $people = (int)post_val('adam_san', 0);
                $status = trim(post_val('status', 'новая'));

                if ($clientId <= 0 || $tourId <= 0 || $staffId <= 0 || empty($date) || $people <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Заполните все обязательные поля']);
                    break;
                }

                $stmt = $pdo->prepare("INSERT INTO bron (client_id, tour_id, kizmetker_id, bron_kun, adam_san, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$clientId, $tourId, $staffId, $date, $people, $status]);
                echo json_encode(['success' => true, 'message' => 'Бронь успешно создана']);
                break;

            // Обновить бронь
            case 'edit':
                $id = (int)post_val('bron_id', 0);
                $clientId = (int)post_val('client_id', 0);
                $tourId = (int)post_val('tour_id', 0);
                $staffId = (int)post_val('kizmetker_id', 0);
                $date = trim(post_val('bron_kun'));
                $people = (int)post_val('adam_san', 0);
                $status = trim(post_val('status'));

                if ($id <= 0 || $clientId <= 0 || $tourId <= 0 || $staffId <= 0 || empty($date) || $people <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Заполните все поля']);
                    break;
                }

                $stmt = $pdo->prepare("UPDATE bron SET client_id = ?, tour_id = ?, kizmetker_id = ?, bron_kun = ?, adam_san = ?, status = ? WHERE bron_id = ?");
                $stmt->execute([$clientId, $tourId, $staffId, $date, $people, $status, $id]);
                echo json_encode(['success' => true, 'message' => 'Бронь обновлена']);
                break;

            // Обновить только статус (AJAX)
            case 'update_status':
                $id = (int)post_val('bron_id', 0);
                $status = trim(post_val('status'));
                $validStatuses = [
                    'расталды', 'күтуде', 'бас тартылды', 'аяқталды',
                    'Расталды', 'Күтуде', 'Бас тартылды', 'Аяқталды',
                    'новая', 'подтверждена', 'отменена', 'завершена',
                    'Новая', 'Подтверждена', 'Отменена', 'Завершена'
                ];

                if ($id <= 0 || !in_array($status, $validStatuses)) {
                    echo json_encode(['success' => false, 'message' => 'Некорректный статус']);
                    break;
                }

                $stmt = $pdo->prepare("UPDATE bron SET status = ? WHERE bron_id = ?");
                $stmt->execute([$status, $id]);
                echo json_encode(['success' => true, 'message' => 'Статус обновлён на "' . htmlspecialchars($status) . '"']);
                break;

            // Удалить бронь
            case 'delete':
                $id = (int)post_val('bron_id', 0);
                if ($id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Некорректный ID']);
                    break;
                }

                // Проверка: есть ли оплаты
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM tolem WHERE bron_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Невозможно удалить: у этой брони есть оплаты. Сначала удалите оплаты.']);
                    break;
                }

                $stmt = $pdo->prepare("DELETE FROM bron WHERE bron_id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Бронь удалена']);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Неизвестное действие']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Ошибка БД: ' . $e->getMessage()]);
    }
    exit;
}

// Хелпер для нормализации CSS-класса статуса
function getBookingStatusClass($status) {
    $s = mb_strtolower(trim(!empty($status) ? $status : ''), 'UTF-8');
    if (in_array($s, ['новая', 'new', 'күтуде', 'күтілуде'])) return 'күтуде';
    if (in_array($s, ['подтверждена', 'confirmed', 'расталды'])) return 'расталды';
    if (in_array($s, ['отменена', 'cancelled', 'бас тартылды', 'жойылды'])) return 'бас-тартылды';
    if (in_array($s, ['завершена', 'completed', 'аяқталды'])) return 'аяқталды';
    return 'күтуде';
}

// ========= ОБЫЧНАЯ ЗАГРУЗКА =========
try {
    // Все брони с JOIN-ами и подсчётом суммы оплат
    $bookings = $pdo->query("
        SELECT b.*, 
               k.client_full_name, 
               t.tour_name, t.price AS tour_price, (t.price * b.adam_san) AS total_sum,
               s.kizmetker_neme,
               COALESCE((SELECT SUM(tolem_sum) FROM tolem WHERE bron_id = b.bron_id), 0) AS paid_sum
        FROM bron b
        LEFT JOIN klient k ON b.client_id = k.client_id
        LEFT JOIN tour t ON b.tour_id = t.tour_id
        LEFT JOIN kizmetker s ON b.kizmetker_id = s.kizmetker_id
        ORDER BY b.bron_id DESC
    ")->fetchAll();

    // Списки для выпадающих меню
    $clients = $pdo->query("SELECT client_id, client_full_name FROM klient ORDER BY client_full_name")->fetchAll();
    $tours = $pdo->query("SELECT tour_id, tour_name, price, tour_time FROM tour ORDER BY tour_name")->fetchAll();
    $staffList = $pdo->query("SELECT kizmetker_id, kizmetker_neme FROM kizmetker ORDER BY kizmetker_neme")->fetchAll();
} catch (PDOException $e) {
    $bookings = $clients = $tours = $staffList = [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Брони — TourManager</title>
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
                <a href="/bookings/index.php" class="nav-link active"><span class="icon">📋</span> Брони</a>
                <a href="/payments/index.php" class="nav-link"><span class="icon">💳</span> Оплаты</a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <button class="mobile-toggle" onclick="toggleSidebar()">☰</button>
                    <h2>📋 Бронирования</h2>
                    <p>Управление бронями клиентов, статусами и оплатами</p>
                </div>
                <div style="display:flex; gap:10px;">
                    <button class="btn btn-secondary" onclick="exportBookingsCSV()">📥 Экспорт в CSV</button>
                    <button class="btn btn-primary" onclick="openAddModal()">+ Новая бронь</button>
                </div>
            </div>

            <div class="page-body">
                <div class="table-container">
                    <!-- Расширенная панель фильтрации -->
                    <div class="table-toolbar">
                        <div class="filter-bar">
                            <div class="search-box" style="flex: 1.5; min-width: 220px;">
                                <input type="text" id="searchBookings" placeholder="Поиск по клиенту или туру..." oninput="applyBookingFilters()">
                            </div>

                            <div class="filter-group">
                                <label for="filterStatus">Статус:</label>
                                <select id="filterStatus" class="filter-control" onchange="applyBookingFilters()">
                                    <option value="">Все статусы</option>
                                    <option value="күтуде">🟣 Күтуде (Новая)</option>
                                    <option value="расталды">🟢 Расталды (Подтверждена)</option>
                                    <option value="бас тартылды">🔴 Бас тартылды (Отменена)</option>
                                    <option value="аяқталды">🟠 Аяқталды (Завершена)</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="filterStaff">Сотрудник:</label>
                                <select id="filterStaff" class="filter-control" onchange="applyBookingFilters()">
                                    <option value="">Все сотрудники</option>
                                    <?php foreach ($staffList as $s): ?>
                                        <option value="<?= htmlspecialchars($s['kizmetker_neme']) ?>"><?= htmlspecialchars($s['kizmetker_neme']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label>Дата с:</label>
                                <input type="date" id="filterDateFrom" class="filter-control" onchange="applyBookingFilters()">
                            </div>

                            <div class="filter-group">
                                <label>по:</label>
                                <input type="date" id="filterDateTo" class="filter-control" onchange="applyBookingFilters()">
                            </div>

                            <button class="btn btn-secondary btn-sm" onclick="resetBookingFilters()" title="Сбросить фильтры">Сброс</button>
                        </div>
                    </div>

                    <div class="table-scroll">
                        <table id="bookingsTable">
                            <thead>
                                <tr>
                                    <th class="sortable" data-sort-type="number">ID</th>
                                    <th class="sortable" data-sort-type="string">Клиент</th>
                                    <th class="sortable" data-sort-type="string">Тур</th>
                                    <th class="sortable" data-sort-type="string">Сотрудник</th>
                                    <th class="sortable" data-sort-type="date">Дата</th>
                                    <th class="sortable" data-sort-type="number">Чел.</th>
                                    <th class="sortable" data-sort-type="number">Стоимость</th>
                                    <th>Оплата</th>
                                    <th>Статус</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="bookingsTableBody">
                                <?php if (empty($bookings)): ?>
                                    <tr class="empty-row">
                                        <td colspan="10">
                                            <div class="empty-state">
                                                <div class="empty-icon">📋</div>
                                                <h4>Нет бронирований</h4>
                                                <p>Создайте первую бронь</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($bookings as $b): 
                                        $curStatus = mb_strtolower(trim(!empty($b['status']) ? $b['status'] : ''), 'UTF-8');
                                        $tot = (float)$b['total_sum'];
                                        $pd = (float)$b['paid_sum'];
                                        $isPaid = ($pd >= $tot && $tot > 0);
                                        $isPartial = ($pd > 0 && !$isPaid);
                                    ?>
                                        <tr data-id="<?= $b['bron_id'] ?>" 
                                            data-date="<?= $b['bron_kun'] ?>" 
                                            data-status="<?= htmlspecialchars($curStatus) ?>"
                                            data-staff="<?= htmlspecialchars(!empty($b['kizmetker_neme']) ? $b['kizmetker_neme'] : '') ?>">
                                            <td>#<?= $b['bron_id'] ?></td>
                                            <td style="font-weight:600; color:var(--text-primary);"><?= htmlspecialchars(!empty($b['client_full_name']) ? $b['client_full_name'] : '—') ?></td>
                                            <td><?= htmlspecialchars(!empty($b['tour_name']) ? $b['tour_name'] : '—') ?></td>
                                            <td><?= htmlspecialchars(!empty($b['kizmetker_neme']) ? $b['kizmetker_neme'] : '—') ?></td>
                                            <td><?= $b['bron_kun'] ? date('d.m.Y', strtotime($b['bron_kun'])) : '—' ?></td>
                                            <td><?= $b['adam_san'] ?> чел.</td>
                                            <td style="font-weight:600; color:var(--text-primary);"><?= number_format($tot, 0, '.', ' ') ?> ₸</td>
                                            <td>
                                                <!-- Индикатор оплаты -->
                                                <?php if ($isPaid): ?>
                                                    <span class="payment-badge paid" title="Оплачено полностью: <?= number_format($pd, 0, '.', ' ') ?> ₸">✓ Оплачено</span>
                                                <?php elseif ($isPartial): ?>
                                                    <span class="payment-badge partial" title="Оплачено <?= number_format($pd, 0, '.', ' ') ?> из <?= number_format($tot, 0, '.', ' ') ?> ₸">⏱ <?= number_format($pd, 0, '.', ' ') ?> ₸</span>
                                                <?php else: ?>
                                                    <span class="payment-badge unpaid" title="К оплате: <?= number_format($tot, 0, '.', ' ') ?> ₸">✕ Не оплачено</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <!-- Выпадающий список статуса с мгновенным AJAX-сохранением -->
                                                <select class="form-control" 
                                                        style="width:auto; min-width:145px; padding:6px 28px 6px 10px; font-size:12px; font-weight:600;" 
                                                        onchange="updateStatus(<?= $b['bron_id'] ?>, this.value, this)" 
                                                        data-current="<?= htmlspecialchars($b['status']) ?>">
                                                    <option value="күтуде" <?= in_array($curStatus, ['күтуде', 'күтілуде', 'новая', 'new']) ? 'selected' : '' ?>>🟣 Күтуде (Новая)</option>
                                                    <option value="расталды" <?= in_array($curStatus, ['расталды', 'подтверждена', 'confirmed']) ? 'selected' : '' ?>>🟢 Расталды (Подтверждена)</option>
                                                    <option value="бас тартылды" <?= in_array($curStatus, ['бас тартылды', 'отменена', 'cancelled', 'жойылды']) ? 'selected' : '' ?>>🔴 Бас тартылды (Отменена)</option>
                                                    <option value="аяқталды" <?= in_array($curStatus, ['аяқталды', 'завершена', 'completed']) ? 'selected' : '' ?>>🟠 Аяқталды (Завершена)</option>
                                                </select>
                                            </td>
                                            <td>
                                                <div class="actions-cell">
                                                    <button class="btn btn-secondary btn-sm" title="Редактировать" onclick="openEditModal(<?= $b['bron_id'] ?>)">✏️</button>
                                                    <button class="btn btn-danger btn-sm" title="Удалить" onclick="deleteBooking(<?= $b['bron_id'] ?>)">🗑️</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Пагинация -->
                    <div class="pagination-container" id="bookingsPagination"></div>
                </div>
            </div>
        </main>
    </div>

    <!-- Модальное окно создания/редактирования брони -->
    <div class="modal-overlay" id="bookingModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="bookingModalTitle">Новая бронь</h3>
                <button class="modal-close">✕</button>
            </div>
            <div class="modal-body">
                <form id="bookingForm" onsubmit="event.preventDefault(); saveBooking();">
                    <input type="hidden" id="edit_bron_id" value="">

                    <div class="form-group">
                        <label for="client_id">Клиент *</label>
                        <select class="form-control" id="client_id" required>
                            <option value="">— Выберите клиента —</option>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['client_id'] ?>"><?= htmlspecialchars($c['client_full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-error"></div>
                    </div>

                    <div class="form-group">
                        <label for="tour_id">Тур *</label>
                        <select class="form-control" id="tour_id" required onchange="onTourOrPeopleChange()">
                            <option value="">— Выберите тур —</option>
                            <?php foreach ($tours as $t): ?>
                                <option value="<?= $t['tour_id'] ?>" data-price="<?= $t['price'] ?>" data-days="<?= $t['tour_time'] ?>">
                                    <?= htmlspecialchars($t['tour_name']) ?> (<?= $t['tour_time'] ?> дн.) — <?= number_format($t['price'], 0, '.', ' ') ?> ₸
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-error"></div>
                    </div>

                    <!-- Блок с ценой тура и итоговой стоимостью -->
                    <div class="form-group" id="priceBlock" style="display:none;">
                        <label>Стоимость тура</label>
                        <div class="price-display" id="tourPriceDisplay">—</div>
                    </div>

                    <div class="form-group">
                        <label for="kizmetker_id">Сотрудник *</label>
                        <select class="form-control" id="kizmetker_id" required>
                            <option value="">— Выберите сотрудника —</option>
                            <?php foreach ($staffList as $s): ?>
                                <option value="<?= $s['kizmetker_id'] ?>"><?= htmlspecialchars($s['kizmetker_neme']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-error"></div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="bron_kun">Дата бронирования *</label>
                            <input type="date" class="form-control" id="bron_kun" required>
                            <div class="form-error"></div>
                        </div>
                        <div class="form-group">
                            <label for="adam_san">Количество человек *</label>
                            <input type="number" class="form-control" id="adam_san" min="1" value="1" required oninput="onTourOrPeopleChange()">
                            <div class="form-error"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="status">Статус брони *</label>
                        <select class="form-control" id="status" required>
                            <option value="күтуде">🟣 Күтуде (Новая)</option>
                            <option value="расталды">🟢 Расталды (Подтверждена)</option>
                            <option value="бас тартылды">🔴 Бас тартылды (Отменена)</option>
                            <option value="аяқталды">🟠 Аяқталды (Завершена)</option>
                        </select>
                        <div class="form-error"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="Modal.close('bookingModal')">Отмена</button>
                <button class="btn btn-primary" id="saveBookingBtn" onclick="saveBooking()">Сохранить</button>
            </div>
        </div>
    </div>

    <div id="toast-container" class="toast-container"></div>
    <script src="/assets/js/main.js"></script>
    <script>
        const toursData = <?= json_encode($tours) ?>;
        let bookingsPaginator;

        document.addEventListener('DOMContentLoaded', () => {
            bookingsPaginator = new TablePaginator({
                tableBodySelector: '#bookingsTableBody',
                paginationContainerSelector: '#bookingsPagination',
                pageSize: 15
            });

            setupTableSorting('#bookingsTable', () => {
                bookingsPaginator.refreshRows();
            });
        });

        function openAddModal() {
            document.getElementById('bookingModalTitle').textContent = 'Новая бронь';
            document.getElementById('bookingForm').reset();
            document.getElementById('edit_bron_id').value = '';
            document.getElementById('priceBlock').style.display = 'none';
            document.getElementById('bron_kun').value = new Date().toISOString().split('T')[0];
            document.getElementById('status').value = 'күтуде';
            Validator.clearErrors('bookingForm');
            Modal.open('bookingModal');
        }

        async function openEditModal(id) {
            const result = await ajaxRequest('/bookings/index.php', { action: 'get', id }, 'GET');
            if (result.success) {
                document.getElementById('bookingModalTitle').textContent = 'Редактировать бронь';
                document.getElementById('edit_bron_id').value = result.data.bron_id;
                document.getElementById('client_id').value = result.data.client_id;
                document.getElementById('tour_id').value = result.data.tour_id;
                document.getElementById('kizmetker_id').value = result.data.kizmetker_id;
                document.getElementById('bron_kun').value = result.data.bron_kun;
                document.getElementById('adam_san').value = result.data.adam_san;

                // Нормализация статуса для формы
                const cur = (result.data.status || '').toLowerCase().trim();
                let selVal = 'күтуде';
                if (['расталды', 'подтверждена', 'confirmed'].includes(cur)) selVal = 'расталды';
                else if (['бас тартылды', 'отменена', 'cancelled', 'жойылды'].includes(cur)) selVal = 'бас тартылды';
                else if (['аяқталды', 'завершена', 'completed'].includes(cur)) selVal = 'аяқталды';
                document.getElementById('status').value = selVal;

                onTourOrPeopleChange();
                Validator.clearErrors('bookingForm');
                Modal.open('bookingModal');
            } else {
                ToastManager.show(result.message, 'error');
            }
        }

        // Автоматический пересчёт стоимости при смене тура или количества человек (без перезагрузки)
        function onTourOrPeopleChange() {
            const tourSelect = document.getElementById('tour_id');
            const selected = tourSelect.options[tourSelect.selectedIndex];
            const priceBlock = document.getElementById('priceBlock');
            const priceDisplay = document.getElementById('tourPriceDisplay');
            const people = Math.max(1, parseInt(document.getElementById('adam_san').value) || 1);

            if (tourSelect.value && selected && selected.dataset.price) {
                const unitPrice = parseFloat(selected.dataset.price);
                const total = unitPrice * people;
                const days = selected.dataset.days;
                priceDisplay.innerHTML = `
                    <div><strong>${formatMoney(total)}</strong> (${people} чел. × ${formatMoney(unitPrice)})</div>
                    <div style="font-size:12px; font-weight:normal; opacity:0.8; margin-top:2px;">Длительность: ${days} дней</div>
                `;
                priceBlock.style.display = 'block';
            } else {
                priceBlock.style.display = 'none';
            }
        }

        async function saveBooking() {
            const errors = Validator.validateForm({
                'client_id': [{ type: 'required', name: 'Клиент' }],
                'tour_id': [{ type: 'required', name: 'Тур' }],
                'kizmetker_id': [{ type: 'required', name: 'Сотрудник' }],
                'bron_kun': [{ type: 'required', name: 'Дата' }],
                'adam_san': [{ type: 'required', name: 'Количество человек' }, { type: 'positiveNumber', name: 'Количество человек' }],
                'status': [{ type: 'required', name: 'Статус' }],
            });

            if (errors.length > 0) {
                ToastManager.show(errors[0], 'error');
                return;
            }

            const editId = document.getElementById('edit_bron_id').value;
            const data = {
                action: editId ? 'edit' : 'add',
                client_id: document.getElementById('client_id').value,
                tour_id: document.getElementById('tour_id').value,
                kizmetker_id: document.getElementById('kizmetker_id').value,
                bron_kun: document.getElementById('bron_kun').value,
                adam_san: document.getElementById('adam_san').value,
                status: document.getElementById('status').value,
            };
            if (editId) data.bron_id = editId;

            const saveBtn = document.getElementById('saveBookingBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner"></span> Сохранение...';

            const result = await ajaxRequest('/bookings/index.php', data);
            saveBtn.disabled = false;
            saveBtn.textContent = 'Сохранить';

            if (result.success) {
                ToastManager.show(result.message, 'success');
                Modal.close('bookingModal');
                setTimeout(() => location.reload(), 500);
            } else {
                ToastManager.show(result.message, 'error');
            }
        }

        // AJAX-обновление статуса прямо в таблице без перезагрузки
        async function updateStatus(bronId, newStatus, selectEl) {
            const result = await ajaxRequest('/bookings/index.php', {
                action: 'update_status',
                bron_id: bronId,
                status: newStatus
            });

            if (result.success) {
                ToastManager.show(result.message || 'Статус успешно обновлён', 'success');
                if (selectEl) {
                    selectEl.dataset.current = newStatus;
                    const tr = selectEl.closest('tr');
                    if (tr) tr.dataset.status = newStatus.toLowerCase();
                }
            } else {
                ToastManager.show(result.message, 'error');
                if (selectEl) selectEl.value = selectEl.dataset.current;
            }
        }

        async function deleteBooking(id) {
            if (!confirmDelete('Вы уверены, что хотите удалить эту бронь?')) return;
            const result = await ajaxRequest('/bookings/index.php', { action: 'delete', bron_id: id });
            if (result.success) {
                ToastManager.show(result.message, 'success');
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (row) {
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(20px)';
                    row.style.transition = 'all 0.3s ease';
                    setTimeout(() => {
                        row.remove();
                        if (bookingsPaginator) bookingsPaginator.refreshRows();
                    }, 300);
                }
            } else {
                ToastManager.show(result.message, 'error');
            }
        }

        // Комплексная фильтрация по тексту, статусу, датам и сотруднику
        function applyBookingFilters() {
            const query = document.getElementById('searchBookings').value.toLowerCase().trim();
            const statusFilter = document.getElementById('filterStatus').value.toLowerCase().trim();
            const staffFilter = document.getElementById('filterStaff').value.toLowerCase().trim();
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;

            if (bookingsPaginator) {
                bookingsPaginator.setFilter(row => {
                    const text = row.textContent.toLowerCase();
                    const rowStatus = (row.dataset.status || '').toLowerCase();
                    const rowStaff = (row.dataset.staff || '').toLowerCase();
                    const rowDate = row.dataset.date || '';

                    // Поиск
                    if (query && !text.includes(query)) return false;

                    // Статус
                    if (statusFilter) {
                        const sMap = {
                            'күтуде': ['күтуде', 'күтілуде', 'новая', 'new'],
                            'расталды': ['расталды', 'подтверждена', 'confirmed'],
                            'бас тартылды': ['бас тартылды', 'отменена', 'cancelled', 'жойылды'],
                            'аяқталды': ['аяқталды', 'завершена', 'completed']
                        };
                        const allowed = sMap[statusFilter] || [statusFilter];
                        if (!allowed.some(s => rowStatus.includes(s))) return false;
                    }

                    // Сотрудник
                    if (staffFilter && !rowStaff.includes(staffFilter)) return false;

                    // Дата от
                    if (dateFrom && rowDate && rowDate < dateFrom) return false;

                    // Дата до
                    if (dateTo && rowDate && rowDate > dateTo) return false;

                    return true;
                });
            }
        }

        function resetBookingFilters() {
            document.getElementById('searchBookings').value = '';
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterStaff').value = '';
            document.getElementById('filterDateFrom').value = '';
            document.getElementById('filterDateTo').value = '';
            applyBookingFilters();
        }

        // Экспорт бронирований в CSV/Excel (с UTF-8 BOM)
        function exportBookingsCSV() {
            const headers = ['ID', 'Клиент', 'Тур', 'Сотрудник', 'Дата', 'Количество человек', 'Стоимость (₸)', 'Оплата', 'Статус'];
            const rows = [];
            const trs = document.querySelectorAll('#bookingsTableBody tr[data-id]');

            trs.forEach(tr => {
                if (tr.style.display !== 'none') {
                    const cells = tr.querySelectorAll('td');
                    if (cells.length >= 9) {
                        const statusSelect = cells[8].querySelector('select');
                        const statusText = statusSelect ? statusSelect.options[statusSelect.selectedIndex].text.replace(/^[^\s]+\s*/, '') : cells[8].innerText.trim();
                        rows.push([
                            cells[0].innerText.replace('#', '').trim(),
                            cells[1].innerText.trim(),
                            cells[2].innerText.trim(),
                            cells[3].innerText.trim(),
                            cells[4].innerText.trim(),
                            cells[5].innerText.replace(/[^\d]/g, '').trim(),
                            cells[6].innerText.replace(/[^\d]/g, '').trim(),
                            cells[7].innerText.trim(),
                            statusText
                        ]);
                    }
                }
            });

            if (rows.length === 0) {
                ToastManager.show('Нет данных для экспорта', 'info');
                return;
            }

            exportToCSV('Бронирования_' + new Date().toISOString().split('T')[0], headers, rows);
            ToastManager.show('Файл CSV успешно сформирован', 'success');
        }
    </script>
</body>
</html>
