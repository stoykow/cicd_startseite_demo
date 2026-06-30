<?php
declare(strict_types=1);

function renderIconMarkup(array $link): string
{
    $label = (string) ($link['icon_label'] ?? $link['title'] ?? 'Icon');
    $assetType = (string) ($link['icon_asset_type'] ?? 'file');
    $cardIconClass = 'card-icon';

    if ($assetType === 'file' && !empty($link['icon_asset_path'])) {
        return sprintf(
            '<div class="%s" aria-label="%s"><img src="%s" alt="%s" loading="lazy" /></div>',
            htmlspecialchars($cardIconClass, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars(appUrl('/' . ltrim((string) $link['icon_asset_path'], '/'), [], false), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        );
    }

    if ($assetType === 'blob' && !empty($link['icon_asset_blob']) && !empty($link['icon_mime_type'])) {
        return sprintf(
            '<div class="%s" aria-label="%s"><img src="data:%s;base64,%s" alt="%s" loading="lazy" /></div>',
            htmlspecialchars($cardIconClass, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) $link['icon_mime_type'], ENT_QUOTES, 'UTF-8'),
            base64_encode((string) $link['icon_asset_blob']),
            htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        );
    }

    $svgMarkup = (string) ($link['svg_markup'] ?? '');
    if ($svgMarkup === '') {
        return '';
    }

    return sprintf(
        '<div class="%s" aria-label="%s">%s</div>',
        htmlspecialchars($cardIconClass, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
        $svgMarkup
    );
}

function renderEditLinkButton(int $linkId): string
{
    $icon = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 17.25V21h3.75L17.8 9.94l-3.75-3.75L3 17.25zm14.71-9.04c.39-.39.39-1.02 0-1.41l-2.5-2.5a.996.996 0 0 0-1.41 0l-1.96 1.96 3.75 3.75 2.12-2.1z" fill="currentColor"/></svg>';

    return sprintf(
        '<a class="card-tool-link" href="%s" aria-label="Seite bearbeiten">%s</a>',
        htmlspecialchars(appUrl('/index.php', ['edit' => 1, 'edit_link' => $linkId]), ENT_QUOTES, 'UTF-8'),
        $icon
    );
}

function renderMoveIcon(): string
{
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2l3.2 3.2-2.1 2.1-.1-.1v3.8h3.8l-.1-.1 2.1-2.1L22 12l-3.2 3.2-2.1-2.1.1-.1H13v3.8l.1-.1 2.1 2.1L12 22l-3.2-3.2 2.1-2.1.1.1V13H7.2l.1.1-2.1 2.1L2 12l3.2-3.2 2.1 2.1-.1.1H11V7.2l-.1.1-2.1-2.1L12 2z" fill="currentColor"/></svg>';
}

function renderLinkCard(
    array $link,
    bool $editMode,
    ?int $userId,
    ?int $groupOwnerUserId,
    bool $showLinkIcons,
    bool $showLinkTitles,
    bool $showLinkUrls
): string {
    $isEditable = $editMode && $userId !== null && $groupOwnerUserId === $userId;
    $dragHandle = '';
    $editButton = '';
    $shellClasses = 'grid-item card-shell';
    $shellAttributes = '';

    if ($isEditable) {
        $shellClasses .= ' is-draggable';
        $dragHandle = '<div class="drag-handle" aria-label="Seite verschieben" title="Verschieben" draggable="true">' . renderMoveIcon() . '</div>';
        $editButton = renderEditLinkButton((int) $link['id']);
        $shellAttributes = sprintf(' data-link-id="%d"', (int) $link['id']);
    }

    $hasIcon = (
        (isset($link['svg_markup']) && trim((string) $link['svg_markup']) !== '')
        || !empty($link['icon_asset_path'])
        || !empty($link['icon_asset_blob'])
    );
    $iconHtml = ($showLinkIcons && $hasIcon) ? renderIconMarkup($link) : '';

    $contentParts = [];
    if ($showLinkTitles) {
        $contentParts[] = sprintf('<div class="title">%s</div>', htmlspecialchars((string) $link['title'], ENT_QUOTES, 'UTF-8'));
    }
    if ($showLinkUrls) {
        $contentParts[] = sprintf('<div class="url">%s</div>', htmlspecialchars((string) $link['url'], ENT_QUOTES, 'UTF-8'));
    }
    $contentHtml = $contentParts === [] ? '' : '<div class="card-content">' . implode('', $contentParts) . '</div>';

    return sprintf(
        '<div class="%s"%s>%s%s<a class="card" href="%s" target="_blank" rel="noopener noreferrer" draggable="false">%s%s</a></div>',
        $shellClasses,
        $shellAttributes,
        $dragHandle,
        $editButton,
        htmlspecialchars((string) $link['url'], ENT_QUOTES, 'UTF-8'),
        $iconHtml,
        $contentHtml
    );
}

function renderAddCard(int $groupId): string
{
    return sprintf(
        '<a class="grid-item add-card" href="%s" aria-label="Neue Seite anlegen">' .
        '<span class="add-card-ring"><span class="add-card-plus">+</span></span>' .
        '<span class="add-card-text">Neue Seite</span>' .
        '</a>',
        htmlspecialchars(appUrl('/index.php', ['edit' => 1, 'add_link' => 1, 'group_id' => $groupId]), ENT_QUOTES, 'UTF-8')
    );
}

function renderIconChoiceMarkup(array $icon): string
{
    if (($icon['asset_type'] ?? 'file') === 'file' && !empty($icon['asset_path'])) {
        return sprintf(
            '<img src="%s" alt="%s" loading="lazy" />',
            htmlspecialchars(appUrl('/' . ltrim((string) $icon['asset_path'], '/'), [], false), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) ($icon['icon_set_label'] . ' - ' . $icon['variant_label']), ENT_QUOTES, 'UTF-8')
        );
    }

    if (($icon['asset_type'] ?? 'file') === 'blob' && !empty($icon['asset_blob'])) {
        return sprintf(
            '<img src="data:%s;base64,%s" alt="%s" loading="lazy" />',
            htmlspecialchars((string) ($icon['mime_type'] ?? 'application/octet-stream'), ENT_QUOTES, 'UTF-8'),
            base64_encode((string) $icon['asset_blob']),
            htmlspecialchars((string) ($icon['icon_set_label'] . ' - ' . $icon['variant_label']), ENT_QUOTES, 'UTF-8')
        );
    }

    return (string) ($icon['svg_markup'] ?? '');
}

function renderLinkModal(
    string $mode,
    array $linkForm,
    array $formErrors,
    array $iconOptions,
    array $reusableLinks,
    int $activeGroupId
): string {
    $isEditMode = $mode === 'edit';
    $title = $isEditMode ? 'Seite bearbeiten' : 'Seite hinzufügen';
    $actionName = $isEditMode ? 'update_link' : 'create_link';
    $cancelParams = ['edit' => 1];
    $actionParams = $isEditMode
        ? ['edit' => 1, 'edit_link' => (int) ($linkForm['id'] ?? 0)]
        : ['edit' => 1, 'add_link' => 1, 'group_id' => $activeGroupId];

    ob_start();
    ?>
    <div class="modal-backdrop">
      <div class="modal-card">
        <h2 class="section-title" style="margin-bottom:16px;"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
        <?php if ($formErrors !== []): ?>
          <div class="form-errors"><?= htmlspecialchars(implode(' ', $formErrors), ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if (!$isEditMode && $reusableLinks !== []): ?>
          <form method="post" action="<?= htmlspecialchars(appUrl('/index.php', $actionParams), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="reuse_link" />
            <input type="hidden" name="group_id" value="<?= (int) $activeGroupId ?>" />
            <div class="builder-field">
              <label for="reuse_link_id">Vorhandene Seite aus einem deiner anderen Profile hinzufügen</label>
              <select id="reuse_link_id" class="reuse-select" name="reuse_link_id" size="<?= min(4, max(1, count($reusableLinks))) ?>">
                <?php $currentReuseProfile = null; ?>
                <?php foreach ($reusableLinks as $reusableLink): ?>
                  <?php if ($currentReuseProfile !== (string) $reusableLink['profile_name']): ?>
                    <?php if ($currentReuseProfile !== null): ?>
                      </optgroup>
                    <?php endif; ?>
                    <?php $currentReuseProfile = (string) $reusableLink['profile_name']; ?>
                    <optgroup label="<?= htmlspecialchars($currentReuseProfile, ENT_QUOTES, 'UTF-8') ?>">
                  <?php endif; ?>
                  <option value="<?= (int) $reusableLink['id'] ?>" <?= (int) ($linkForm['reuse_link_id'] ?? 0) === (int) $reusableLink['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($reusableLink['group_title'] . ' - ' . $reusableLink['title'] . ' (' . $reusableLink['url'] . ')', ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
                <?php if ($currentReuseProfile !== null): ?>
                  </optgroup>
                <?php endif; ?>
              </select>
            </div>
            <div class="modal-actions">
              <button class="action-button" type="submit">Vorhandene Seite hinzufügen</button>
            </div>
          </form>
          <div class="modal-divider"><span>Oder neu anlegen</span></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" action="<?= htmlspecialchars(appUrl('/index.php', $actionParams), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="<?= htmlspecialchars($actionName, ENT_QUOTES, 'UTF-8') ?>" />
          <input type="hidden" name="group_id" value="<?= (int) ($linkForm['group_id'] ?? $activeGroupId) ?>" />
          <?php if ($isEditMode): ?>
            <input type="hidden" name="link_id" value="<?= (int) ($linkForm['id'] ?? 0) ?>" />
          <?php endif; ?>
          <div class="builder-field">
            <label for="link_title"><?= $isEditMode ? 'Name' : 'Titel' ?></label>
            <input id="link_title" name="title" type="text" placeholder="Mein Link" value="<?= htmlspecialchars((string) ($linkForm['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required />
          </div>
          <div class="builder-field">
            <label for="link_url">Adresse</label>
            <input id="link_url" name="url" type="url" placeholder="https://example.com" value="<?= htmlspecialchars((string) ($linkForm['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required />
          </div>
          <!--<div class="builder-field">
            <label for="source_type">Link-Typ</label>
            <select id="source_type" name="source_type">
              <option value="manual" <?= (($linkForm['source_type'] ?? 'manual') === 'manual') ? 'selected' : '' ?>>Manuell</option>
              <option value="preset" <?= (($linkForm['source_type'] ?? 'manual') === 'preset') ? 'selected' : '' ?>>Vordefiniert</option>
            </select>
          </div> -->
          <div class="builder-field">
            <label>Icon</label>
            <div class="icon-choice-grid">
              <div class="icon-choice-stack">
                <?php if ($isEditMode && $iconOptions !== []): ?>
                  <?php foreach ($iconOptions as $icon): ?>
                    <label class="icon-choice">
                      <input type="radio" name="icon_mode" value="library" <?= (($linkForm['icon_mode'] ?? 'library') === 'library') ? 'checked' : '' ?> />
                      <input type="hidden" name="icon_variant_id" value="<?= (int) $icon['id'] ?>" />
                      <span class="icon-choice-preview"><?= renderIconChoiceMarkup($icon) ?></span>
                    </label>
                  <?php endforeach; ?>
                <?php endif; ?>
                <label class="icon-choice">
                  <input type="radio" name="icon_mode" value="none" <?= (($linkForm['icon_mode'] ?? 'none') === 'none') ? 'checked' : '' ?> />
                  <span class="icon-choice-text">Ohne</span>
                </label>
              </div>
              <label class="icon-choice icon-choice-upload">
                <span class="icon-choice-upload-head">
                  <input type="radio" name="icon_mode" value="upload" <?= (($linkForm['icon_mode'] ?? ($isEditMode ? 'library' : 'none')) === 'upload') ? 'checked' : '' ?> />
                  <span>Icon hochladen</span>
                </span>
                <input id="icon_svg" name="icon_svg" type="file" accept=".png,.jpg,.jpeg,.webp,.svg,image/png,image/jpeg,image/webp,image/svg" />
              </label>
            </div>
          </div>
          <div class="modal-actions">
            <button class="action-button primary" type="submit"><?= $isEditMode ? 'Änderungen speichern' : 'Speichern' ?></button>
            <a class="user-panel-link" href="<?= htmlspecialchars(appUrl('/index.php', $cancelParams), ENT_QUOTES, 'UTF-8') ?>">Abbrechen</a>
          </div>
        </form>
        <?php if ($isEditMode): ?>
          <form method="post" action="<?= htmlspecialchars(appUrl('/index.php', $actionParams), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Seite wirklich löschen?');">
            <input type="hidden" name="action" value="delete_link" />
            <input type="hidden" name="link_id" value="<?= (int) ($linkForm['id'] ?? 0) ?>" />
            <div class="modal-actions modal-actions-secondary">
              <button class="action-button danger" type="submit">Löschen</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <?php

    return (string) ob_get_clean();
}
