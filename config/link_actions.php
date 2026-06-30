<?php
declare(strict_types=1);

function defaultLinkForm(): array
{
    return [
        'id' => 0,
        'group_id' => 0,
        'title' => '',
        'url' => '',
        'source_type' => 'manual',
        'icon_mode' => 'none',
        'icon_variant_id' => 0,
        'reuse_link_id' => 0,
    ];
}

function normalizeLinkFormInput(array $input): array
{
    return [
        'id' => (int) ($input['id'] ?? $input['link_id'] ?? 0),
        'group_id' => (int) ($input['group_id'] ?? 0),
        'title' => trim((string) ($input['title'] ?? '')),
        'url' => trim((string) ($input['url'] ?? '')),
        'source_type' => (string) ($input['source_type'] ?? 'manual'),
        'icon_mode' => (string) ($input['icon_mode'] ?? 'library'),
        'icon_variant_id' => (int) ($input['icon_variant_id'] ?? 0),
        'reuse_link_id' => (int) ($input['reuse_link_id'] ?? 0),
    ];
}

function ownedGroupExists(PDO $pdo, int $userId, int $profileId, int $groupId): bool
{
    $statement = $pdo->prepare(
        'SELECT id
         FROM link_groups
         WHERE id = :id
           AND owner_user_id = :owner_user_id
           AND profile_id = :profile_id
         LIMIT 1'
    );
    $statement->execute([
        ':id' => $groupId,
        ':owner_user_id' => $userId,
        ':profile_id' => $profileId,
    ]);

    return (int) $statement->fetchColumn() > 0;
}

function fetchOwnedLinkForEditing(PDO $pdo, int $userId, int $profileId, int $linkId): ?array
{
    $statement = $pdo->prepare(
        'SELECT
            l.id,
            l.group_id,
            l.title,
            l.url,
            l.icon_variant_id,
            l.source_type,
            g.title AS group_title,
            COALESCE(l.icon_label, v.variant_label) AS icon_label,
            COALESCE(l.icon_asset_type, v.asset_type) AS icon_asset_type,
            COALESCE(l.icon_svg_markup, v.svg_markup) AS svg_markup,
            COALESCE(l.icon_asset_path, v.asset_path) AS icon_asset_path,
            COALESCE(l.icon_asset_blob, v.asset_blob) AS icon_asset_blob,
            COALESCE(l.icon_mime_type, v.mime_type) AS icon_mime_type
         FROM links l
         INNER JOIN link_groups g ON g.id = l.group_id
         LEFT JOIN icon_variants v ON v.id = l.icon_variant_id
         WHERE l.id = :id
           AND g.owner_user_id = :owner_user_id
           AND g.profile_id = :profile_id
         LIMIT 1'
    );
    $statement->execute([
        ':id' => $linkId,
        ':owner_user_id' => $userId,
        ':profile_id' => $profileId,
    ]);
    $link = $statement->fetch();

    return $link ?: null;
}

function buildLinkFormFromLink(array $link): array
{
    $form = defaultLinkForm();
    $form['id'] = (int) ($link['id'] ?? 0);
    $form['group_id'] = (int) ($link['group_id'] ?? 0);
    $form['title'] = (string) ($link['title'] ?? '');
    $form['url'] = (string) ($link['url'] ?? '');
    $form['source_type'] = (string) ($link['source_type'] ?? 'manual');
    $form['icon_variant_id'] = (int) ($link['icon_variant_id'] ?? 0);
    $form['icon_mode'] = $form['icon_variant_id'] > 0 ? 'library' : 'none';

    return $form;
}

function resolveSubmittedIconVariantId(
    PDO $pdo,
    array $linkForm,
    array $files,
    array &$formErrors
): ?int {
    $iconMode = (string) ($linkForm['icon_mode'] ?? 'library');
    $iconVariantId = (int) ($linkForm['icon_variant_id'] ?? 0);
    $upload = $files['icon_svg'] ?? null;
    $hasUploadedFile = is_array($upload)
        && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($hasUploadedFile) {
        $iconMode = 'upload';
    }

    if ($iconMode === 'upload') {
        if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $formErrors[] = 'Bitte eine PNG-, JPG- oder WebP-Datei auswählen.';
            return null;
        }
        if ((int) ($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $uploadErrorCode = (int) ($upload['error'] ?? UPLOAD_ERR_OK);
            $uploadErrorText = match ($uploadErrorCode) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Die Datei ist zu groß.',
                UPLOAD_ERR_PARTIAL => 'Die Datei wurde nur teilweise hochgeladen.',
                UPLOAD_ERR_NO_TMP_DIR => 'Der temporäre Upload-Ordner fehlt.',
                UPLOAD_ERR_CANT_WRITE => 'Die Datei konnte nicht auf den Server geschrieben werden.',
                UPLOAD_ERR_EXTENSION => 'Eine PHP-Erweiterung hat den Upload gestoppt.',
                default => 'Fehlercode: ' . $uploadErrorCode,
            };
            $formErrors[] = 'Das Icon konnte nicht hochgeladen werden. ' . $uploadErrorText;
            return null;
        }

        $uploadError = null;
        $iconVariantId = createUploadedIconVariant(
            $pdo,
            (string) ($linkForm['title'] !== '' ? $linkForm['title'] : 'Custom Icon'),
            $upload,
            $uploadError
        );
        if ($iconVariantId <= 0) {
            $formErrors[] = 'Das hochgeladene Icon konnte nicht gespeichert werden.' . ($uploadError !== null ? ' ' . $uploadError : '');
            return null;
        }

        return $iconVariantId;
    }

    if ($iconMode === 'none') {
        return 0;
    }

    if ($iconVariantId <= 0) {
        $formErrors[] = 'Bitte ein Icon auswählen oder "Kein Icon" nutzen.';
        return null;
    }

    return $iconVariantId;
}

function validateLinkForm(array $linkForm, array &$formErrors): void
{
    if ((string) ($linkForm['title'] ?? '') === '') {
        $formErrors[] = 'Bitte einen Titel angeben.';
    }
    if (!filter_var((string) ($linkForm['url'] ?? ''), FILTER_VALIDATE_URL)) {
        $formErrors[] = 'Bitte eine gültige URL eingeben.';
    }
}

function updateOwnedLink(
    PDO $pdo,
    int $userId,
    int $profileId,
    int $linkId,
    int $groupId,
    string $title,
    string $url,
    ?int $iconVariantId,
    string $sourceType
): bool {
    $statement = $pdo->prepare(
        'UPDATE links l
         INNER JOIN link_groups g ON g.id = l.group_id
         SET
            l.group_id = :group_id,
            l.title = :title,
            l.url = :url,
            l.icon_variant_id = :icon_variant_id,
            l.source_type = :source_type
         WHERE l.id = :id
           AND g.owner_user_id = :owner_user_id
           AND g.profile_id = :profile_id'
    );

    $updated = $statement->execute([
        ':group_id' => $groupId,
        ':title' => $title,
        ':url' => $url,
        ':icon_variant_id' => $iconVariantId,
        ':source_type' => in_array($sourceType, ['preset', 'manual'], true) ? $sourceType : 'manual',
        ':id' => $linkId,
        ':owner_user_id' => $userId,
        ':profile_id' => $profileId,
    ]);

    if ($updated && $iconVariantId !== null && $iconVariantId > 0) {
        copyVariantIconToLink($pdo, $linkId);
    }

    if ($updated && ($iconVariantId === null || $iconVariantId <= 0)) {
        $clearIcon = $pdo->prepare(
            'UPDATE links
             SET icon_label = NULL,
                 icon_asset_type = NULL,
                 icon_svg_markup = NULL,
                 icon_asset_path = NULL,
                 icon_asset_blob = NULL,
                 icon_mime_type = NULL
             WHERE id = :id'
        );
        $clearIcon->execute([':id' => $linkId]);
    }

    return $updated;
}

function deleteOwnedLink(PDO $pdo, int $userId, int $profileId, int $linkId): bool
{
    $statement = $pdo->prepare(
        'DELETE l
         FROM links l
         INNER JOIN link_groups g ON g.id = l.group_id
         WHERE l.id = :id
           AND g.owner_user_id = :owner_user_id
           AND g.profile_id = :profile_id'
    );

    return $statement->execute([
        ':id' => $linkId,
        ':owner_user_id' => $userId,
        ':profile_id' => $profileId,
    ]);
}

function buildLinkModalIconOptions(array $iconOptions, array $linkForm, ?array $editingLink = null): array
{
    $selectedVariantId = (int) ($linkForm['icon_variant_id'] ?? 0);
    if ($selectedVariantId <= 0 || $editingLink === null) {
        return $iconOptions;
    }

    foreach ($iconOptions as $iconOption) {
        if ((int) ($iconOption['id'] ?? 0) === $selectedVariantId) {
            return [$iconOption];
        }
    }

    $customOption = [
        'id' => $selectedVariantId,
        'icon_set_label' => 'Aktuelles Icon',
        'variant_label' => (string) ($editingLink['icon_label'] ?? $editingLink['title'] ?? 'Icon'),
        'asset_type' => (string) ($editingLink['icon_asset_type'] ?? 'file'),
        'svg_markup' => (string) ($editingLink['svg_markup'] ?? ''),
        'asset_path' => $editingLink['icon_asset_path'] ?? null,
        'asset_blob' => $editingLink['icon_asset_blob'] ?? null,
        'mime_type' => $editingLink['icon_mime_type'] ?? null,
    ];

    return [$customOption];
}
