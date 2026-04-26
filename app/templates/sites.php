<?php $title = 'Sites'; ?>

<div class="panel">
    <div class="panel-header">
        <h2 class="panel-title">Sites</h2>
        <a href="/sites/new" class="btn btn-primary btn-sm">Add Site</a>
    </div>

    <?php if (empty($sites)): ?>
        <div class="empty-state">
            <p>No sites configured.</p>
            <a href="/sites/new" class="btn btn-primary">Create a Site</a>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Description</th>
                    <th>Devices</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sites as $site): ?>
                <tr>
                    <td>
                        <?= h($site['name']) ?>
                        <?php if ((int) $site['id'] === $currentSiteId): ?>
                            <span class="badge badge-active">active</span>
                        <?php endif; ?>
                    </td>
                    <td><code><?= h($site['slug']) ?></code></td>
                    <td><?= h($site['description']) ?></td>
                    <td><?= h($site['device_count']) ?></td>
                    <td class="table-actions">
                        <?php if ((int) $site['id'] !== $currentSiteId): ?>
                        <form method="POST" action="/sites/<?= h($site['id']) ?>/switch" style="display:inline">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="redirect" value="/">
                            <button type="submit" class="btn btn-primary btn-xs">Switch To</button>
                        </form>
                        <?php endif; ?>
                        <a href="/sites/<?= h($site['id']) ?>/edit" class="btn btn-secondary btn-xs">Edit</a>
                        <form method="POST" action="/sites/<?= h($site['id']) ?>/delete" style="display:inline"
                              data-confirm="Delete site &ldquo;<?= h($site['name']) ?>&rdquo;? This cannot be undone.">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-danger btn-xs">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
