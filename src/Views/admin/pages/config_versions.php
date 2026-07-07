<?php

declare(strict_types=1);

$filters = $filters ?? [];
$configVersionRows = $configVersionRows ?? $configVersions;
$configVersionsPagination = $configVersionsPagination ?? [];
$appliedCount = count(array_filter($configVersions, static fn (array $item): bool => ($item['status'] ?? '') === 'applied'));
$draftCount = count(array_filter($configVersions, static fn (array $item): bool => ($item['status'] ?? '') === 'generated'));
?>
<section class="metrics-grid">
    <?= $view->render('admin/components/metric_card.php', ['label' => 'Lịch sử cấu hình', 'value' => count($configVersions), 'hint' => 'Theo bộ lọc hiện tại']) ?>
    <?= $view->render('admin/components/metric_card.php', ['label' => 'Đã áp dụng', 'value' => $appliedCount, 'hint' => 'Đang active']) ?>
    <?= $view->render('admin/components/metric_card.php', ['label' => 'Bản nháp', 'value' => $draftCount, 'hint' => 'Chờ validate/apply']) ?>
</section>

<?= $view->render('admin/components/filter_toolbar.php', [
    'title' => 'Bộ lọc service config',
    'action' => '/admin/config-versions',
    'summary' => 'Tìm theo service, version hoặc checksum.',
    'resultCount' => count($configVersionRows),
    'resultLabel' => 'revision',
    'resetHref' => '/admin/config-versions',
    'fields' => [
        [
            'label' => 'Tìm kiếm',
            'name' => 'search',
            'type' => 'search',
            'value' => (string) ($filters['search'] ?? ''),
            'placeholder' => 'nginx, exim, checksum...',
        ],
        [
            'label' => 'Trạng thái',
            'name' => 'status',
            'type' => 'select',
            'value' => (string) ($filters['status'] ?? ''),
            'options' => [
                ['value' => '', 'label' => 'Tất cả'],
                ['value' => 'generated', 'label' => 'Generated'],
                ['value' => 'validated', 'label' => 'Validated'],
                ['value' => 'applied', 'label' => 'Applied'],
                ['value' => 'failed', 'label' => 'Failed'],
                ['value' => 'rolled_back', 'label' => 'Rolled back'],
            ],
        ],
    ],
]) ?>

<?php if ($canAccess('config_versions.create') || $canAccess('config_versions.delete')): ?>
    <div class="page-action-seed" data-page-action-seed>
        <details class="action-menu">
            <summary class="btn btn-primary">Thao tác cấu hình</summary>
            <div class="action-menu__content">
                <div class="action-menu__header"><span>Service config</span></div>

                <?php if ($canAccess('config_versions.create')): ?>
                    <form method="post" action="/admin/config-versions" data-confirm="Tạo draft cấu hình mới?">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="generate">
                        <input name="current_password" type="password" required autocomplete="current-password" placeholder="Mật khẩu hiện tại">
                        <input name="otp" inputmode="numeric" autocomplete="one-time-code" placeholder="OTP nếu có">
                        <button class="btn btn-primary btn-sm" type="submit">Tạo bản nháp mới</button>
                    </form>
                <?php endif; ?>

                <?php if ($canAccess('config_versions.delete')): ?>
                    <form method="post" action="/admin/config-versions" class="danger-zone-form" data-confirm="Clear các revision cũ? Hệ thống vẫn giữ bản applied và các bản mới nhất.">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="clear_old">
                        <label>Giữ lại mỗi service
                            <input name="keep_per_service" type="number" min="1" max="100" value="10">
                        </label>
                        <label><input type="checkbox" name="delete_artifacts" checked> Xóa cả thư mục artifact cũ</label>
                        <input name="current_password" type="password" required autocomplete="current-password" placeholder="Mật khẩu hiện tại">
                        <input name="otp" inputmode="numeric" autocomplete="one-time-code" placeholder="OTP nếu có">
                        <button class="btn btn-danger btn-sm" type="submit">Clear cấu hình cũ</button>
                    </form>
                <?php endif; ?>
            </div>
        </details>
    </div>
<?php endif; ?>

<section class="panel">
    <div class="panel-header">
        <h2>Lịch sử service config</h2>
        <p>Theo dõi generate, validate, apply và rollback.</p>
    </div>

    <?php if ($configVersionRows === []): ?>
        <?= $view->render('admin/components/empty_state.php', [
            'title' => 'Chưa có lịch sử cấu hình',
            'description' => 'Tạo draft đầu tiên để theo dõi lịch sử thay đổi và rollback an toàn.',
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>ID</th><th>Dịch vụ</th><th>Phiên bản</th><th>Trạng thái</th><th>Checksum</th><th>Áp dụng lúc</th><th>Thao tác</th></tr>
                </thead>
                <tbody>
                <?php foreach ($configVersionRows as $item): ?>
                    <?php $rowLabel = sprintf('#%d %s', (int) $item['id'], (string) ($item['service'] ?? 'config')); ?>
                    <tr>
                        <td><strong>#<?= (int) $item['id'] ?></strong></td>
                        <td><?= htmlspecialchars((string) $item['service'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) $item['version'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $view->render('admin/components/status_badge.php', ['value' => $item['status'] ?? 'unknown']) ?></td>
                        <td><code><?= htmlspecialchars(substr((string) ($item['checksum'] ?? ''), 0, 16), ENT_QUOTES, 'UTF-8') ?></code>…</td>
                        <td><?= htmlspecialchars((string) ($item['applied_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <details class="action-menu">
                                <summary class="btn btn-secondary btn-sm">Thao tác</summary>
                                <div class="action-menu__content">
                                    <div class="action-menu__header"><span><?= htmlspecialchars($rowLabel, ENT_QUOTES, 'UTF-8') ?></span></div>

                                    <?php if ($canAccess('config_versions.update')): ?>
                                        <form method="post" action="/admin/config-versions" data-confirm="Áp dụng revision này?">
                                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="apply">
                                            <input type="hidden" name="version_id" value="<?= (int) $item['id'] ?>">
                                            <input name="current_password" type="password" required autocomplete="current-password" placeholder="Mật khẩu hiện tại">
                                            <input name="otp" inputmode="numeric" autocomplete="one-time-code" placeholder="OTP nếu có">
                                            <button class="btn btn-secondary btn-sm" type="submit">Áp dụng</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (!empty($item['previous_version_id']) && $canAccess('config_versions.restore')): ?>
                                        <form method="post" action="/admin/config-versions" data-confirm="Rollback về revision trước đó?">
                                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="rollback">
                                            <input type="hidden" name="version_id" value="<?= (int) $item['id'] ?>">
                                            <input name="current_password" type="password" required autocomplete="current-password" placeholder="Mật khẩu hiện tại">
                                            <input name="otp" inputmode="numeric" autocomplete="one-time-code" placeholder="OTP nếu có">
                                            <button class="btn btn-secondary btn-sm" type="submit">Rollback</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= $view->render('admin/components/pagination.php', ['pagination' => $configVersionsPagination]) ?>
    <?php endif; ?>
</section>
