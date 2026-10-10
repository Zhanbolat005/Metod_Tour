<?php
/**
 * Раздел "Туры" — CRUD операции с таблицей tour
 */
require_once __DIR__ . '/../db_connect.php';

$message = '';
$messageType = '';

// ========= ОБРАБОТКА AJAX-ЗАПРОСОВ =========
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json; charset=utf-8');

    $action = req_val('action');

    try {
        switch ($action) {
            // Получить данные тура для редактирования
            case 'get':
                $id = (int)get_val('id', 0);
                $stmt = $pdo->prepare("SELECT * FROM tour WHERE tour_id = ?");
                $stmt->execute([$id]);
                $tour = $stmt->fetch();
                if ($tour) {
                    echo json_encode(['success' => true, 'data' => $tour]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Тур не найден']);
                }
                break;

            // Добавить тур
            case 'add':
                $name = trim(post_val('tour_name'));
                $time = (int)post_val('tour_time', 0);
                $bagit = trim(post_val('tour_bagit'));
                $price = (int)post_val('price', 0);

                // Серверная валидация
                if (empty($name) || $time <= 0 || empty($bagit) || $price <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Заполните все поля корректно']);
                    break;
                }

                $stmt = $pdo->prepare("INSERT INTO tour (tour_name, tour_time, tour_bagit, price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $time, $bagit, $price]);
                echo json_encode(['success' => true, 'message' => 'Тур успешно добавлен']);
                break;

            // Обновить тур
            case 'edit':
                $id = (int)post_val('tour_id', 0);
                $name = trim(post_val('tour_name'));
                $time = (int)post_val('tour_time', 0);
                $bagit = trim(post_val('tour_bagit'));
                $price = (int)post_val('price', 0);

                if ($id <= 0 || empty($name) || $time <= 0 || empty($bagit) || $price <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Заполните все поля корректно']);
                    break;
                }

                $stmt = $pdo->prepare("UPDATE tour SET tour_name = ?, tour_time = ?, tour_bagit = ?, price = ? WHERE tour_id = ?");
                $stmt->execute([$name, $time, $bagit, $price, $id]);
                echo json_encode(['success' => true, 'message' => 'Тур успешно обновлён']);
                break;

            // Удалить тур
            case 'delete':
                $id = (int)post_val('tour_id', 0);
                if ($id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Некорректный ID тура']);
                    break;
                }

                // Проверка: есть ли брони на этот тур
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM bron WHERE tour_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Невозможно удалить: на этот тур есть бронирования']);
                    break;
                }

                $stmt = $pdo->prepare("DELETE FROM tour WHERE tour_id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Тур удалён']);
                break;

            // Получить все туры (JSON для AJAX)
            case 'list':
                $tours = $pdo->query("SELECT * FROM tour ORDER BY tour_id DESC")->fetchAll();
                echo json_encode(['success' => true, 'data' => $tours]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Неизвестное действие']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Ошибка БД: ' . $e->getMessage()]);
    }
    exit;
}

// ========= ОБЫЧНАЯ ЗАГРУЗКА СТРАНИЦЫ =========
try {
    $tours = $pdo->query("SELECT * FROM tour ORDER BY tour_id DESC")->fetchAll();
} catch (PDOException $e) {
    $tours = [];
    $message = 'Ошибка загрузки туров: ' . $e->getMessage();
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Туры — TourManager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="app-wrapper">
        <!-- Боковая навигация -->
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
                <a href="/tours/index.php" class="nav-link active"><span class="icon">🌍</span> Туры</a>
                <a href="/clients/index.php" class="nav-link"><span class="icon">👤</span> Клиенты</a>
                <a href="/staff/index.php" class="nav-link"><span class="icon">💼</span> Сотрудники</a>
                <div class="nav-section-title">Бизнес</div>
                <a href="/bookings/index.php" class="nav-link"><span class="icon">📋</span> Брони</a>
                <a href="/payments/index.php" class="nav-link"><span class="icon">💳</span> Оплаты</a>
            </nav>
        </aside>

        <!-- Основной контент -->
        <main class="main-content">
            <div class="page-header">
                <div>
                    <button class="mobile-toggle" onclick="toggleSidebar()">☰</button>
                    <h2>🌍 Туры</h2>
                    <p>Управление турами и направлениями</p>
                </div>
                <button class="btn btn-primary" onclick="openAddModal()">+ Добавить тур</button>
            </div>

            <div class="page-body">
                <div class="table-container">
                    <div class="table-toolbar">
                        <div class="search-box">
                            <input type="text" id="searchTours" placeholder="Поиск по названию или направлению..." oninput="filterTours()">
                        </div>
                    </div>
                    <div class="table-scroll">
                        <table id="toursTable">
                            <thead>
                                <tr>
                                    <th class="sortable" data-sort-type="number">ID</th>
                                    <th class="sortable" data-sort-type="string">Название</th>
                                    <th class="sortable" data-sort-type="number">Длительность</th>
                                    <th class="sortable" data-sort-type="string">Направление</th>
                                    <th class="sortable" data-sort-type="number">Цена</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="toursTableBody">
                                <?php if (empty($tours)): ?>
                                    <tr class="empty-row">
                                        <td colspan="6">
                                            <div class="empty-state">
                                                <div class="empty-icon">🌍</div>
                                                <h4>Нет туров</h4>
                                                <p>Добавьте первый тур</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tours as $tour): ?>
                                        <tr data-id="<?= $tour['tour_id'] ?>">
                                            <td>#<?= $tour['tour_id'] ?></td>
                                            <td style="font-weight:600; color:var(--text-primary);"><?= htmlspecialchars($tour['tour_name']) ?></td>
                                            <td><?= $tour['tour_time'] ?> дн.</td>
                                            <td><?= htmlspecialchars($tour['tour_bagit']) ?></td>
                                            <td style="color:var(--secondary); font-weight:700;"><?= number_format($tour['price'], 0, '.', ' ') ?> ₸</td>
                                            <td>
                                                <div class="actions-cell">
                                                    <button class="btn btn-secondary btn-sm" title="Редактировать" onclick="openEditModal(<?= $tour['tour_id'] ?>)">✏️</button>
                                                    <button class="btn btn-danger btn-sm" title="Удалить" onclick="deleteTour(<?= $tour['tour_id'] ?>)">🗑️</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Пагинация -->
                    <div class="pagination-container" id="toursPagination"></div>
                </div>
            </div>
        </main>
    </div>

    <!-- Модальное окно добавления/редактирования тура -->
    <div class="modal-overlay" id="tourModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="tourModalTitle">Добавить тур</h3>
                <button class="modal-close">✕</button>
            </div>
            <div class="modal-body">
                <form id="tourForm" onsubmit="event.preventDefault(); saveTour();">
                    <input type="hidden" id="edit_tour_id" value="">

                    <div class="form-group">
                        <label for="tour_name">Название тура *</label>
                        <input type="text" class="form-control" id="tour_name" placeholder="Например: Золотое кольцо" maxlength="50" required>
                        <div class="form-error"></div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="tour_time">Длительность (дни) *</label>
                            <input type="number" class="form-control" id="tour_time" placeholder="7" min="1" required>
                            <div class="form-error"></div>
                        </div>
                        <div class="form-group">
                            <label for="price">Цена (₸) *</label>
                            <input type="number" class="form-control" id="price" placeholder="150000" min="1" required>
                            <div class="form-error"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="tour_bagit">Направление *</label>
                        <input type="text" class="form-control" id="tour_bagit" placeholder="Например: Турция" maxlength="50" required>
                        <div class="form-error"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="Modal.close('tourModal')">Отмена</button>
                <button class="btn btn-primary" id="saveTourBtn" onclick="saveTour()">Сохранить</button>
            </div>
        </div>
    </div>

    <div id="toast-container" class="toast-container"></div>
    <script src="/assets/js/main.js"></script>
    <script>
        let toursPaginator;

        document.addEventListener('DOMContentLoaded', () => {
            toursPaginator = new TablePaginator({
                tableBodySelector: '#toursTableBody',
                paginationContainerSelector: '#toursPagination',
                pageSize: 15
            });

            setupTableSorting('#toursTable', () => {
                toursPaginator.refreshRows();
            });
        });

        // Открыть модалку для добавления
        function openAddModal() {
            document.getElementById('tourModalTitle').textContent = 'Добавить тур';
            document.getElementById('tourForm').reset();
            document.getElementById('edit_tour_id').value = '';
            Validator.clearErrors('tourForm');
            Modal.open('tourModal');
        }

        // Открыть модалку для редактирования (загрузка данных через AJAX)
        async function openEditModal(id) {
            const result = await ajaxRequest('/tours/index.php', { id }, 'GET');
            if (result.success) {
                document.getElementById('tourModalTitle').textContent = 'Редактировать тур';
                document.getElementById('edit_tour_id').value = result.data.tour_id;
                document.getElementById('tour_name').value = result.data.tour_name;
                document.getElementById('tour_time').value = result.data.tour_time;
                document.getElementById('tour_bagit').value = result.data.tour_bagit;
                document.getElementById('price').value = result.data.price;
                Validator.clearErrors('tourForm');
                Modal.open('tourModal');
            } else {
                ToastManager.show(result.message || 'Ошибка загрузки данных', 'error');
            }
        }

        // Сохранить тур (добавление или редактирование)
        async function saveTour() {
            // Клиентская валидация с подсветкой и выводом текста ошибки под полем
            const errors = Validator.validateForm({
                'tour_name': [{ type: 'required', name: 'Название' }],
                'tour_time': [{ type: 'required', name: 'Длительность' }, { type: 'positiveNumber', name: 'Длительность' }],
                'tour_bagit': [{ type: 'required', name: 'Направление' }],
                'price': [{ type: 'required', name: 'Цена' }, { type: 'positiveNumber', name: 'Цена' }],
            });

            if (errors.length > 0) {
                ToastManager.show(errors[0], 'error');
                return;
            }

            const editId = document.getElementById('edit_tour_id').value;
            const action = editId ? 'edit' : 'add';

            const data = {
                action,
                tour_name: document.getElementById('tour_name').value,
                tour_time: document.getElementById('tour_time').value,
                tour_bagit: document.getElementById('tour_bagit').value,
                price: document.getElementById('price').value,
            };
            if (editId) data.tour_id = editId;

            const saveBtn = document.getElementById('saveTourBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner"></span> Сохранение...';

            const result = await ajaxRequest('/tours/index.php', data);
            saveBtn.disabled = false;
            saveBtn.textContent = 'Сохранить';

            if (result.success) {
                ToastManager.show(result.message, 'success');
                Modal.close('tourModal');
                setTimeout(() => location.reload(), 500);
            } else {
                ToastManager.show(result.message || 'Ошибка сохранения', 'error');
            }
        }

        // Удалить тур с подтверждением
        async function deleteTour(id) {
            if (!confirmDelete('Вы уверены, что хотите удалить этот тур?')) return;

            const result = await ajaxRequest('/tours/index.php', { action: 'delete', tour_id: id });
            if (result.success) {
                ToastManager.show(result.message, 'success');
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (row) {
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(20px)';
                    row.style.transition = 'all 0.3s ease';
                    setTimeout(() => {
                        row.remove();
                        if (toursPaginator) toursPaginator.refreshRows();
                    }, 300);
                }
            } else {
                ToastManager.show(result.message || 'Ошибка удаления', 'error');
            }
        }

        // Фильтрация таблицы по поиску (название и направление) через пагинатор
        function filterTours() {
            const query = document.getElementById('searchTours').value.toLowerCase().trim();
            if (toursPaginator) {
                toursPaginator.setFilter(row => {
                    const text = row.textContent.toLowerCase();
                    return text.includes(query);
                });
            }
        }
    </script>
</body>
</html>
