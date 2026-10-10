<?php
/**
 * Главная страница / Dashboard
 * Отображает статистику и навигацию по разделам
 */
require_once __DIR__ . '/db_connect.php';

// Хелпер для нормализации CSS-класса статуса (поддержка казахских и русских статусов)
function getStatusBadgeClass($status) {
    $s = mb_strtolower(trim(!empty($status) ? $status : ''), 'UTF-8');
    if (in_array($s, ['новая', 'new', 'күтуде', 'күтілуде'])) return 'күтуде';
    if (in_array($s, ['подтверждена', 'confirmed', 'расталды'])) return 'расталды';
    if (in_array($s, ['отменена', 'cancelled', 'бас тартылды', 'жойылды'])) return 'бас-тартылды';
    if (in_array($s, ['завершена', 'completed', 'аяқталды'])) return 'аяқталды';
    return htmlspecialchars($s);
}

// Получение статистики
try {
    $tourCount = $pdo->query("SELECT COUNT(*) FROM tour")->fetchColumn();
    $clientCount = $pdo->query("SELECT COUNT(*) FROM klient")->fetchColumn();
    
    // Исправление бага: учёт реальных статусов в БД ('расталды', 'күтуде' на казахском и русских эквивалентов)
    $activeBookings = $pdo->query("
        SELECT COUNT(*) FROM bron 
        WHERE LOWER(TRIM(status)) IN ('расталды', 'күтуде', 'күтілуде', 'новая', 'подтверждена')
           OR status IN ('Расталды', 'Күтуде', 'расталды', 'күтуде', 'новая', 'подтверждена', 'Новая', 'Подтверждена')
    ")->fetchColumn();
    
    $totalPayments = $pdo->query("SELECT COALESCE(SUM(tolem_sum), 0) FROM tolem")->fetchColumn();

    // Последние 5 бронирований для виджета
    $recentBookings = $pdo->query("
        SELECT b.bron_id, k.client_full_name, t.tour_name, b.bron_kun, b.status
        FROM bron b
        LEFT JOIN klient k ON b.client_id = k.client_id
        LEFT JOIN tour t ON b.tour_id = t.tour_id
        ORDER BY b.bron_id DESC
        LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {
    $tourCount = $clientCount = $activeBookings = $totalPayments = 0;
    $recentBookings = array();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Система бронирования туров — управление турами, клиентами, бронями и оплатами">
    <title>TourManager — Система бронирования туров</title>
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
                <a href="/index.php" class="nav-link active">
                    <span class="icon">📊</span> Дашборд
                </a>
                <a href="/tours/index.php" class="nav-link">
                    <span class="icon">🌍</span> Туры
                </a>
                <a href="/clients/index.php" class="nav-link">
                    <span class="icon">👤</span> Клиенты
                </a>
                <a href="/staff/index.php" class="nav-link">
                    <span class="icon">💼</span> Сотрудники
                </a>
                <div class="nav-section-title">Бизнес</div>
                <a href="/bookings/index.php" class="nav-link">
                    <span class="icon">📋</span> Брони
                </a>
                <a href="/payments/index.php" class="nav-link">
                    <span class="icon">💳</span> Оплаты
                </a>
            </nav>
        </aside>

        <!-- Основной контент -->
        <main class="main-content">
            <div class="page-header">
                <div>
                    <button class="mobile-toggle" onclick="toggleSidebar()">☰</button>
                    <h2>Дашборд</h2>
                    <p>Обзор системы бронирования туров</p>
                </div>
            </div>

            <div class="page-body">
                <!-- Карточки статистики -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon tours">🌍</div>
                        <div class="stat-value"><?php echo $tourCount; ?></div>
                        <div class="stat-label">Всего туров</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon clients">👤</div>
                        <div class="stat-value"><?php echo $clientCount; ?></div>
                        <div class="stat-label">Клиентов</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon bookings">📋</div>
                        <div class="stat-value"><?php echo $activeBookings; ?></div>
                        <div class="stat-label">Активных броней</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon payments">💳</div>
                        <div class="stat-value"><?php echo number_format($totalPayments, 0, '.', ' '); ?> ₸</div>
                        <div class="stat-label">Общая сумма оплат</div>
                    </div>
                </div>

                <!-- Последние бронирования -->
                <div class="table-container">
                    <div class="table-toolbar">
                        <h3 style="font-size:16px;">Последние бронирования</h3>
                        <a href="/bookings/index.php" class="btn btn-secondary btn-sm">Все брони →</a>
                    </div>
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Клиент</th>
                                    <th>Тур</th>
                                    <th>Дата</th>
                                    <th>Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentBookings)): ?>
                                    <tr>
                                        <td colspan="5">
                                            <div class="empty-state">
                                                <div class="empty-icon">📋</div>
                                                <h4>Нет бронирований</h4>
                                                <p>Создайте первую бронь в разделе "Брони"</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentBookings as $b): ?>
                                        <tr>
                                            <td>#<?php echo $b['bron_id']; ?></td>
                                            <td><?php echo htmlspecialchars(isset($b['client_full_name']) ? $b['client_full_name'] : '—'); ?></td>
                                            <td><?php echo htmlspecialchars(isset($b['tour_name']) ? $b['tour_name'] : '—'); ?></td>
                                            <td><?php echo $b['bron_kun'] ? date('d.m.Y', strtotime($b['bron_kun'])) : '—'; ?></td>
                                            <td><span class="status-badge <?php echo getStatusBadgeClass($b['status']); ?>"><?php echo htmlspecialchars(isset($b['status']) ? $b['status'] : '—'); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="toast-container" class="toast-container"></div>
    <script src="/assets/js/main.js"></script>
</body>
</html>
