<?php
$isEdit  = $site !== null;
$action  = $isEdit ? "/sites/{$site['id']}/edit" : '/sites';
$flash   = Session::getFlashInput();
$oldName = $flash['name']        ?? ($isEdit ? $site['name']        : '');
$oldSlug = $flash['slug']        ?? ($isEdit ? $site['slug']        : '');
$oldDesc = $flash['description'] ?? ($isEdit ? $site['description'] : '');
?>

<div class="panel">
    <div class="panel-header">
        <h2 class="panel-title"><?= h($title) ?></h2>
    </div>

    <form method="POST" action="<?= h($action) ?>" class="form-body">
        <?= Csrf::field() ?>

        <div class="field-group">
            <label class="field-label" for="site-name">Name <span class="required">*</span></label>
            <input id="site-name" type="text" name="name" class="field-input"
                   maxlength="128" required value="<?= h($oldName) ?>"
                   placeholder="e.g. Main Office">
        </div>

        <div class="field-group">
            <label class="field-label" for="site-slug">Slug</label>
            <input id="site-slug" type="text" name="slug" class="field-input"
                   maxlength="64" value="<?= h($oldSlug) ?>"
                   placeholder="auto-generated from name">
            <p class="field-hint">Lowercase letters, numbers, and hyphens only. Leave blank to auto-generate.</p>
        </div>

        <div class="field-group">
            <label class="field-label" for="site-desc">Description</label>
            <textarea id="site-desc" name="description" class="field-input"
                      rows="3" placeholder="Optional description"><?= h($oldDesc) ?></textarea>
        </div>

        <div class="form-actions">
            <a href="/sites" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <?= $isEdit ? 'Save Changes' : 'Create Site' ?>
            </button>
        </div>
    </form>
</div>
