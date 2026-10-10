<?php
/**
 * Раздел "Сотрудники" — CRUD операции с таблицей kizmetker
 */
require_once __DIR__ . '/../db_connect.php';

// ========= AJAX-ЗАПРОСЫ =========
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json; charset=utf-8');

    $action = req_val('action');

    try {
        switch ($action) {
            case 'get':
                $id = (int)get_val('id', 0);
                $stmt = $pdo->prepare("SELECT * FROM kizmetker WHERE kizmetker_id = ?");
                $stmt->execute([$id]);
                $staff = $stmt->fetch();
                echo json_encode($staff ? ['success' => true, 'data' => $staff] : ['success' => false, 'message' => 'Сотрудник не найден']);
                break;

            case 'add':
                $name = trim(post_val('kizmetker_neme'));
                $position = trim(post_val('position'));
                $phone = trim(post_val('k_phone'));

                if (empty($name) || empty($position) || empty($phone)) {
                    echo json_encode(['success' => false, 'message' => 'Заполните все поля']);
                    break;
                }

                $stmt = $pdo->prepare("INSERT INTO kizmetker (kizmetker_neme, position, k_phone) VALUES (?, ?, ?)");
                $stmt->execute([$name, $position, $phone]);
                echo json_encode(['success' => true, 'message' => 'Сотрудник добавлен']);
                break;

            case 'edit':
                $id = (int)post_val('kizmetker_id', 0);
                $name = trim(post_val('kizmetker_neme'));
                $position = trim(post_val('position'));
                $phone = trim(post_val('k_phone'));

                if ($id <= 0 || empty($name) || empty($position) || empty($phone)) {
                    echo json_encode(['success' => false, 'message' => 'Заполните все поля']);
                    break;
                }

                $stmt = $pdo->prepare("UPDATE kizmetker SET kizmetker_neme = ?, position = ?, k_phone = ? WHERE kizmetker_id = ?");
                $stmt->execute([$name, $position, $phone, $id]);
                echo json_encode(['success' => true, 'message' => 'Данные сотрудника обновлены']);
                break;

            case 'delete':
                $id = (int)post_val('kizmetker_id', 0);
                if ($id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Некорректный ID']);
                    break;
                }

                // Проверка: есть ли у сотрудника брони
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM bron WHERE kizmetker_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Невозможно удалить: сотрудник привязан к бронированиям']);
                    break;
                }

                $stmt = $pdo->prepare("DELETE FROM kizmetker WHERE kizmetker_id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Сотрудник удалён']);
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
    // Подсчёт количества броней, оформленных каждым сотрудником (для оценки нагрузки)
    $staff = $pdo->query("
        SELECT s.*, COUNT(b.bron_id) AS bookings_count
        FROM kizmetker s
        LEFT JOIN bron b ON s.kizmetker_id = b.kizmetker_id
        GROUP BY s.kizmetker_id
        ORDER BY s.kizmetker_id DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $staff = [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сотрудники — TourManager</title>
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
                <a href="/staff/index.php" class="nav-link active"><span class="icon">💼</span> Сотрудники</a>
                <div class="nav-section-title">Бизнес</div>
                <a href="/bookings/index.php" class="nav-link"><span class="icon">📋</span> Брони</a>
                <a href="/payments/index.php" class="nav-link"><span class="icon">💳</span> Оплаты</a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <button class="mobile-toggle" onclick="toggleSidebar()">☰</button>
                    <h2>💼 Сотрудники</h2>
                    <p>Управление персоналом и оценка нагрузки</p>
                </div>
                <button class="btn btn-primary" onclick="openAddModal()">+ Добавить сотрудника</button>
            </div>

            <div class="page-body">
                <div class="table-container">
                    <div class="table-toolbar">
                        <div class="search-box">
                            <input type="text" id="searchStaff" placeholder="Поиск по имени или должности..." oninput="filterStaff()">
                        </div>
                    </div>
                    <div class="table-scroll">
                        <table id="staffTable">
                            <thead>
                                <tr>
                                    <th class="sortable" data-sort-type="number">ID</th>
                                    <th class="sortable" data-sort-type="string">ФИО</th>
                                    <th class="sortable" data-sort-type="string">Должность</th>
                                    <th>Телефон</th>
                                    <th class="sortable" data-sort-type="number">Оформлено броней</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="staffTableBody">
                                <?php if (empty($staff)): ?>
                                    <tr class="empty-row">
                                        <td colspan="6">
                                            <div class="empty-state">
                                                <div class="empty-icon">💼</div>
                                                <h4>Нет сотрудников</h4>
                                                <p>Добавьте первого сотрудника</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($staff as $s): ?>
                                        <tr data-id="<?= $s['kizmetker_id'] ?>">
                                            <td>#<?= $s['kizmetker_id'] ?></td>
                                            <td style="font-weight:600; color:var(--text-primary);"><?= htmlspecialchars($s['kizmetker_neme']) ?></td>
                                            <td><span class="status-badge подтверждена"><?= htmlspecialchars($s['position']) ?></span></td>
                                            <td><?= htmlspecialchars($s['k_phone']) ?></td>
                                            <td>
                                                <span class="status-badge new" style="font-weight:700;">
                                                    📋 <?= (int)$s['bookings_count'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="actions-cell">
                                                    <button class="btn btn-secondary btn-sm" title="Редактировать" onclick="openEditModal(<?= $s['kizmetker_id'] ?>)">✏️</button>
                                                    <button class="btn btn-danger btn-sm" title="Удалить" onclick="deleteStaff(<?= $s['kizmetker_id'] ?>)">🗑️</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Пагинация -->
                    <div class="pagination-container" id="staffPagination"></div>
                </div>
            </div>
        </main>
    </div>

    <!-- Модальное окно сотрудника -->
    <div class="modal-overlay" id="staffModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="staffModalTitle">Добавить сотрудника</h3>
                <button class="modal-close">✕</button>
            </div>
            <div class="modal-body">
                <form id="staffForm" onsubmit="event.preventDefault(); saveStaff();">
                    <input type="hidden" id="edit_kizmetker_id" value="">

                    <div class="form-group">
                        <label for="kizmetker_neme">ФИО *</label>
                        <input type="text" class="form-control" id="kizmetker_neme" placeholder="Петров Пётр Петрович" maxlength="50" required>
                        <div class="form-error"></div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="position">Должность *</label>
                            <input type="text" class="form-control" id="position" placeholder="Менеджер по продажам" maxlength="50" required>
                            <div class="form-error"></div>
                        </div>
                        <div class="form-group">
                            <label for="k_phone">Телефон *</label>
                            <input type="tel" class="form-control" id="k_phone" placeholder="+7 (777) 123-45-67" maxlength="50" required>
                            <div class="form-error"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="Modal.close('staffModal')">Отмена</button>
                <button class="btn btn-primary" id="saveStaffBtn" onclick="saveStaff()">Сохранить</button>
            </div>
        </div>
    </div>

    <div id="toast-container" class="toast-container"></div>
    <script src="/assets/js/main.js"></script>
    <script>
        let staffPaginator;

        document.addEventListener('DOMContentLoaded', () => {
            staffPaginator = new TablePaginator({
                tableBodySelector: '#staffTableBody',
                paginationContainerSelector: '#staffPagination',
                pageSize: 15
            });

            setupTableSorting('#staffTable', () => {
                staffPaginator.refreshRows();
            });
        });

        function openAddModal() {
            document.getElementById('staffModalTitle').textContent = 'Добавить сотрудника';
            document.getElementById('staffForm').reset();
            document.getElementById('edit_kizmetker_id').value = '';
            Validator.clearErrors('staffForm');
            Modal.open('staffModal');
        }

        async function openEditModal(id) {
            const result = await ajaxRequest('/staff/index.php', { action: 'get', id }, 'GET');
            if (result.success) {
                document.getElementById('staffModalTitle').textContent = 'Редактировать сотрудника';
                document.getElementById('edit_kizmetker_id').value = result.data.kizmetker_id;
                document.getElementById('kizmetker_neme').value = result.data.kizmetker_neme;
                document.getElementById('position').value = result.data.position;
                document.getElementById('k_phone').value = result.data.k_phone;
                Validator.clearErrors('staffForm');
                Modal.open('staffModal');
            } else {
                ToastManager.show(result.message, 'error');
            }
        }

        async function saveStaff() {
            const errors = Validator.validateForm({
                'kizmetker_neme': [{ type: 'required', name: 'ФИО' }],
                'position': [{ type: 'required', name: 'Должность' }],
                'k_phone': [{ type: 'required', name: 'Телефон' }, { type: 'phone' }],
            });

            if (errors.length > 0) {
                ToastManager.show(errors[0], 'error');
                return;
            }

            const editId = document.getElementById('edit_kizmetker_id').value;
            const data = {
                action: editId ? 'edit' : 'add',
                kizmetker_neme: document.getElementById('kizmetker_neme').value.trim(),
                position: document.getElementById('position').value.trim(),
                k_phone: document.getElementById('k_phone').value.trim(),
            };
            if (editId) data.kizmetker_id = editId;

            const saveBtn = document.getElementById('saveStaffBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner"></span> Сохранение...';

            const result = await ajaxRequest('/staff/index.php', data);
            saveBtn.disabled = false;
            saveBtn.textContent = 'Сохранить';

            if (result.success) {
                ToastManager.show(result.message, 'success');
                Modal.close('staffModal');
                setTimeout(() => location.reload(), 500);
            } else {
                ToastManager.show(result.message, 'error');
            }
        }

        async function deleteStaff(id) {
            if (!confirmDelete('Вы уверены, что хотите удалить этого сотрудника?')) return;
            const result = await ajaxRequest('/staff/index.php', { action: 'delete', kizmetker_id: id });
            if (result.success) {
                ToastManager.show(result.message, 'success');
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (row) {
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(20px)';
                    row.style.transition = 'all 0.3s ease';
                    setTimeout(() => {
                        row.remove();
                        if (staffPaginator) staffPaginator.refreshRows();
                    }, 300);
                }
            } else {
                ToastManager.show(result.message, 'error');
            }
        }

        function filterStaff() {
            const query = document.getElementById('searchStaff').value.toLowerCase().trim();
            if (staffPaginator) {
                staffPaginator.setFilter(row => {
                    const text = row.textContent.toLowerCase();
                    return text.includes(query);
                });
            }
        }
    </script>
</body>
</html>
