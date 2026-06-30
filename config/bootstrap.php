<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function ensureSchema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(60) NULL UNIQUE,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS dashboard_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            owner_user_id INT NOT NULL,
            name VARCHAR(120) NOT NULL,
            view_token VARCHAR(72) NOT NULL UNIQUE,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_dashboard_profiles_user FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS user_preferences (
            user_id INT PRIMARY KEY,
            card_width_px INT NOT NULL DEFAULT 180,
            column_count INT NOT NULL DEFAULT 6,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_user_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    if (!tableHasColumn($pdo, 'user_preferences', 'created_at')) {
        $pdo->exec('ALTER TABLE user_preferences ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER column_count');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS icon_sets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(100) NOT NULL UNIQUE,
            label VARCHAR(120) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS icon_variants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            icon_set_id INT NOT NULL,
            variant_slug VARCHAR(100) NOT NULL,
            variant_label VARCHAR(120) NOT NULL,
            asset_type VARCHAR(16) NOT NULL DEFAULT "svg",
            svg_markup LONGTEXT NULL,
            asset_path VARCHAR(255) NULL,
            asset_blob LONGBLOB NULL,
            mime_type VARCHAR(120) NULL,
            source_path VARCHAR(255) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_icon_variant (icon_set_id, variant_slug),
            CONSTRAINT fk_icon_variants_set FOREIGN KEY (icon_set_id) REFERENCES icon_sets(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS link_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            owner_user_id INT NULL,
            profile_id INT NULL,
            title VARCHAR(120) NOT NULL,
            card_width_px INT NULL,
            align_mode VARCHAR(16) NOT NULL DEFAULT "center",
            show_group_title TINYINT(1) NOT NULL DEFAULT 1,
            show_link_icons TINYINT(1) NOT NULL DEFAULT 1,
            show_link_titles TINYINT(1) NOT NULL DEFAULT 1,
            show_link_urls TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_link_groups_user FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_link_groups_profile FOREIGN KEY (profile_id) REFERENCES dashboard_profiles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    if (!tableHasColumn($pdo, 'link_groups', 'profile_id')) {
        $pdo->exec('ALTER TABLE link_groups ADD COLUMN profile_id INT NULL AFTER owner_user_id');
    }

    if (!tableHasColumn($pdo, 'link_groups', 'card_width_px')) {
        $pdo->exec('ALTER TABLE link_groups ADD COLUMN card_width_px INT NULL AFTER title');
    }

    if (!tableHasColumn($pdo, 'link_groups', 'align_mode')) {
        $pdo->exec('ALTER TABLE link_groups ADD COLUMN align_mode VARCHAR(16) NOT NULL DEFAULT "center" AFTER card_width_px');
    }

    if (!tableHasColumn($pdo, 'link_groups', 'show_group_title')) {
        $pdo->exec('ALTER TABLE link_groups ADD COLUMN show_group_title TINYINT(1) NOT NULL DEFAULT 1 AFTER align_mode');
    }

    if (!tableHasColumn($pdo, 'link_groups', 'show_link_icons')) {
        $pdo->exec('ALTER TABLE link_groups ADD COLUMN show_link_icons TINYINT(1) NOT NULL DEFAULT 1 AFTER show_group_title');
    }

    if (!tableHasColumn($pdo, 'link_groups', 'show_link_titles')) {
        $pdo->exec('ALTER TABLE link_groups ADD COLUMN show_link_titles TINYINT(1) NOT NULL DEFAULT 1 AFTER show_link_icons');
    }

    if (!tableHasColumn($pdo, 'link_groups', 'show_link_urls')) {
        $pdo->exec('ALTER TABLE link_groups ADD COLUMN show_link_urls TINYINT(1) NOT NULL DEFAULT 1 AFTER show_link_titles');
    }

    if (!tableHasColumn($pdo, 'users', 'updated_at')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
    }

    if (!tableHasColumn($pdo, 'dashboard_profiles', 'updated_at')) {
        $pdo->exec('ALTER TABLE dashboard_profiles ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
    }

    if (!tableHasColumn($pdo, 'icon_sets', 'updated_at')) {
        $pdo->exec('ALTER TABLE icon_sets ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
    }

    if (!tableHasColumn($pdo, 'icon_variants', 'updated_at')) {
        $pdo->exec('ALTER TABLE icon_variants ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
    }

    if (!tableHasColumn($pdo, 'icon_variants', 'asset_type')) {
        $pdo->exec('ALTER TABLE icon_variants ADD COLUMN asset_type VARCHAR(16) NOT NULL DEFAULT "svg" AFTER variant_label');
    }

    if (!tableHasColumn($pdo, 'icon_variants', 'asset_path')) {
        $pdo->exec('ALTER TABLE icon_variants ADD COLUMN asset_path VARCHAR(255) NULL AFTER svg_markup');
    }

    if (!tableHasColumn($pdo, 'icon_variants', 'asset_blob')) {
        $pdo->exec('ALTER TABLE icon_variants ADD COLUMN asset_blob LONGBLOB NULL AFTER asset_path');
    }

    if (!tableHasColumn($pdo, 'icon_variants', 'mime_type')) {
        $pdo->exec('ALTER TABLE icon_variants ADD COLUMN mime_type VARCHAR(120) NULL AFTER asset_blob');
    }

    $pdo->exec('ALTER TABLE icon_variants MODIFY COLUMN svg_markup LONGTEXT NULL');

    if (!tableHasColumn($pdo, 'link_groups', 'updated_at')) {
        $pdo->exec('ALTER TABLE link_groups ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_id INT NOT NULL,
            title VARCHAR(120) NOT NULL,
            url VARCHAR(255) NOT NULL,
            icon_variant_id INT NULL,
            source_type ENUM("preset","manual") NOT NULL DEFAULT "manual",
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_links_group FOREIGN KEY (group_id) REFERENCES link_groups(id) ON DELETE CASCADE,
            CONSTRAINT fk_links_icon_variant FOREIGN KEY (icon_variant_id) REFERENCES icon_variants(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    if (!tableHasColumn($pdo, 'links', 'updated_at')) {
        $pdo->exec('ALTER TABLE links ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
    }

    $pdo->exec('ALTER TABLE links MODIFY COLUMN icon_variant_id INT NULL');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS dashboard_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    if (!tableHasColumn($pdo, 'dashboard_settings', 'created_at')) {
        $pdo->exec('ALTER TABLE dashboard_settings ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER setting_value');
    }
}

function tableHasColumn(PDO $pdo, string $tableName, string $columnName): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );
    $statement->execute([
        ':table_name' => $tableName,
        ':column_name' => $columnName,
    ]);

    return (int) $statement->fetchColumn() > 0;
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'item';
}

function generateOpaqueToken(int $bytes = 24): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

function iconLabelFromFilename(string $filename): string
{
    $stem = pathinfo($filename, PATHINFO_FILENAME);
    $label = str_replace(['-', '_'], ' ', $stem);
    return ucwords($label);
}

function normalizeSvgMarkup(string $svgMarkup): string
{
    $svgMarkup = preg_replace('/<\?xml[^>]*\?>/i', '', $svgMarkup) ?? $svgMarkup;
    $svgMarkup = preg_replace('/<!DOCTYPE[^>]*>/i', '', $svgMarkup) ?? $svgMarkup;
    $svgMarkup = trim($svgMarkup);

    if (str_contains($svgMarkup, '<svg') && !preg_match('/viewBox\s*=\s*"[^"]+"/i', $svgMarkup)) {
        if (preg_match('/width\s*=\s*"([0-9.]+)px?"/i', $svgMarkup, $widthMatch)
            && preg_match('/height\s*=\s*"([0-9.]+)px?"/i', $svgMarkup, $heightMatch)) {
            $viewBox = sprintf('viewBox="0 0 %s %s"', $widthMatch[1], $heightMatch[1]);
            $svgMarkup = preg_replace('/<svg\b/i', '<svg ' . $viewBox, $svgMarkup, 1) ?? $svgMarkup;
        }
    }

    return $svgMarkup;
}

function ensureIconLibrary(PDO $pdo): void
{
    $iconFiles = glob(APP_BASE_PATH . '/assets/icons/*.svg');
    if ($iconFiles === false) {
        return;
    }

    $insertSet = $pdo->prepare(
        'INSERT INTO icon_sets (slug, label)
         VALUES (:slug, :label)
         ON DUPLICATE KEY UPDATE label = VALUES(label)'
    );
    $findSet = $pdo->prepare('SELECT id FROM icon_sets WHERE slug = :slug LIMIT 1');
    $insertVariant = $pdo->prepare(
        'INSERT INTO icon_variants (icon_set_id, variant_slug, variant_label, asset_type, svg_markup, asset_path, asset_blob, mime_type, source_path, sort_order)
         VALUES (:icon_set_id, :variant_slug, :variant_label, :asset_type, :svg_markup, :asset_path, :asset_blob, :mime_type, :source_path, :sort_order)
         ON DUPLICATE KEY UPDATE
             variant_label = VALUES(variant_label),
             asset_type = VALUES(asset_type),
             svg_markup = VALUES(svg_markup),
             asset_path = VALUES(asset_path),
             asset_blob = VALUES(asset_blob),
             mime_type = VALUES(mime_type),
             source_path = VALUES(source_path)'
    );

    $sortOrder = 10;
    foreach ($iconFiles as $iconFile) {
        $filename = basename($iconFile);
        $slug = slugify(pathinfo($filename, PATHINFO_FILENAME));
        $label = iconLabelFromFilename($filename);
        $svgMarkup = file_get_contents($iconFile);
        if ($svgMarkup === false) {
            continue;
        }
        $svgMarkup = normalizeSvgMarkup($svgMarkup);

        $insertSet->execute([
            ':slug' => $slug,
            ':label' => $label,
        ]);

        $findSet->execute([':slug' => $slug]);
        $iconSetId = (int) $findSet->fetchColumn();
        if ($iconSetId <= 0) {
            continue;
        }

        $insertVariant->execute([
            ':icon_set_id' => $iconSetId,
            ':variant_slug' => 'default',
            ':variant_label' => $label,
            ':asset_type' => 'svg',
            ':svg_markup' => $svgMarkup,
            ':asset_path' => null,
            ':asset_blob' => null,
            ':mime_type' => 'image/svg+xml',
            ':source_path' => 'assets/icons/' . $filename,
            ':sort_order' => $sortOrder,
        ]);

        $sortOrder += 10;
    }
}

function iconVariantIdBySourcePath(PDO $pdo, string $sourcePath): int
{
    $statement = $pdo->prepare('SELECT id FROM icon_variants WHERE source_path = :source_path LIMIT 1');
    $statement->execute([':source_path' => $sourcePath]);
    return (int) $statement->fetchColumn();
}

function createUploadedIconVariant(PDO $pdo, string $label, array $upload, ?string &$error = null): int
{
    $tmpPath = (string) ($upload['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_file($tmpPath)) {
        $error = 'tmp_name fehlt oder ist nicht lesbar.';
        return 0;
    }

    $originalName = (string) ($upload['name'] ?? 'upload');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $mimeType = (string) ($upload['type'] ?? '');
    $fileContents = file_get_contents($tmpPath);
    if ($fileContents === false) {
        $error = 'Upload-Datei konnte nicht gelesen werden.';
        return 0;
    }

    $isSvg = $extension === 'svg' || str_contains(strtolower($fileContents), '<svg');
    $assetType = $isSvg ? 'svg' : 'file';
    $assetPath = null;
    $assetBlob = null;
    $svgMarkup = null;

    if ($isSvg) {
        $svgMarkup = normalizeSvgMarkup($fileContents);
        $mimeType = 'image/svg+xml';
        if (!str_contains(strtolower($svgMarkup), '<svg')) {
            $error = 'Datei wurde als SVG erkannt, enthält aber kein gültiges <svg>.';
            return 0;
        }
    } else {
        $allowedExtensions = ['png', 'jpg', 'jpeg', 'webp'];
        if (!in_array($extension, $allowedExtensions, true)) {
            $error = 'Dateityp nicht erlaubt: ' . ($extension !== '' ? $extension : 'unbekannt');
            return 0;
        }

        $uploadDir = APP_BASE_PATH . '/assets/uploads/icons';
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            $error = 'Upload-Ordner konnte nicht erstellt werden: ' . $uploadDir;
            return 0;
        }

        $filename = slugify($label !== '' ? $label : 'custom-icon') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
        $targetPath = $uploadDir . '/' . $filename;
        $stored = @move_uploaded_file($tmpPath, $targetPath);
        if (!$stored) {
            $stored = @copy($tmpPath, $targetPath);
        }
        if (!$stored) {
            $stored = @file_put_contents($targetPath, $fileContents) !== false;
        }
        if (!$stored) {
            $assetType = 'blob';
            $assetBlob = $fileContents;
            $assetPath = null;
        } else {
            @chmod($targetPath, 0644);
            $assetPath = 'assets/uploads/icons/' . $filename;
        }
        if ($mimeType === '') {
            $mimeType = match ($extension) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
                default => 'application/octet-stream',
            };
        }
    }

    $baseSlug = slugify($label !== '' ? $label : 'custom-icon');
    $setSlug = $baseSlug . '-' . bin2hex(random_bytes(4));
    $variantSlug = 'upload';

    $insertSet = $pdo->prepare('INSERT INTO icon_sets (slug, label) VALUES (:slug, :label)');
    $insertSet->execute([
        ':slug' => $setSlug,
        ':label' => $label !== '' ? $label : 'Custom Icon',
    ]);
    $iconSetId = (int) $pdo->lastInsertId();
    if ($iconSetId <= 0) {
        $error = 'icon_sets-Eintrag konnte nicht angelegt werden.';
        return 0;
    }

    $insertVariant = $pdo->prepare(
        'INSERT INTO icon_variants (icon_set_id, variant_slug, variant_label, asset_type, svg_markup, asset_path, asset_blob, mime_type, source_path, sort_order)
         VALUES (:icon_set_id, :variant_slug, :variant_label, :asset_type, :svg_markup, :asset_path, :asset_blob, :mime_type, NULL, 9990)'
    );
    $insertVariant->execute([
        ':icon_set_id' => $iconSetId,
        ':variant_slug' => $variantSlug,
        ':variant_label' => $label !== '' ? $label : 'Custom Icon',
        ':asset_type' => $assetType,
        ':svg_markup' => $svgMarkup,
        ':asset_path' => $assetPath,
        ':asset_blob' => $assetBlob,
        ':mime_type' => $mimeType,
    ]);
    $variantId = (int) $pdo->lastInsertId();
    if ($variantId <= 0) {
        $error = 'icon_variants-Eintrag konnte nicht angelegt werden.';
    }

    return $variantId;
}

function groupIdByTitle(PDO $pdo, string $title, ?int $ownerUserId): int
{
    if ($ownerUserId === null) {
        $statement = $pdo->prepare('SELECT id FROM link_groups WHERE owner_user_id IS NULL AND title = :title LIMIT 1');
        $statement->execute([':title' => $title]);
    } else {
        $statement = $pdo->prepare('SELECT id FROM link_groups WHERE owner_user_id = :owner_user_id AND title = :title LIMIT 1');
        $statement->execute([
            ':owner_user_id' => $ownerUserId,
            ':title' => $title,
        ]);
    }

    return (int) $statement->fetchColumn();
}

function ensureDefaultProfile(PDO $pdo, int $userId): int
{
    $statement = $pdo->prepare(
        'SELECT id
         FROM dashboard_profiles
         WHERE owner_user_id = :owner_user_id
         ORDER BY sort_order, id
         LIMIT 1'
    );
    $statement->execute([':owner_user_id' => $userId]);
    $profileId = (int) $statement->fetchColumn();
    if ($profileId > 0) {
        return $profileId;
    }

    $insert = $pdo->prepare(
        'INSERT INTO dashboard_profiles (owner_user_id, name, view_token, sort_order)
         VALUES (:owner_user_id, :name, :view_token, 10)'
    );
    $insert->execute([
        ':owner_user_id' => $userId,
        ':name' => 'Standard',
        ':view_token' => generateOpaqueToken(),
    ]);

    return (int) $pdo->lastInsertId();
}

function migrateGroupsToProfiles(PDO $pdo): void
{
    $userIds = $pdo->query(
        'SELECT DISTINCT owner_user_id
         FROM link_groups
         WHERE owner_user_id IS NOT NULL
           AND profile_id IS NULL'
    )->fetchAll(PDO::FETCH_COLUMN);

    if ($userIds === []) {
        return;
    }

    $update = $pdo->prepare(
        'UPDATE link_groups
         SET profile_id = :profile_id
         WHERE owner_user_id = :owner_user_id
           AND profile_id IS NULL'
    );

    foreach ($userIds as $userId) {
        $profileId = ensureDefaultProfile($pdo, (int) $userId);
        $update->execute([
            ':profile_id' => $profileId,
            ':owner_user_id' => (int) $userId,
        ]);
    }
}

function fetchUserProfiles(PDO $pdo, int $userId): array
{
    $statement = $pdo->prepare(
        'SELECT id, owner_user_id, name, view_token, sort_order
         FROM dashboard_profiles
         WHERE owner_user_id = :owner_user_id
         ORDER BY sort_order, id'
    );
    $statement->execute([':owner_user_id' => $userId]);
    return $statement->fetchAll();
}

function fetchProfileById(PDO $pdo, int $profileId, int $userId): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, owner_user_id, name, view_token, sort_order
         FROM dashboard_profiles
         WHERE id = :id
           AND owner_user_id = :owner_user_id
         LIMIT 1'
    );
    $statement->execute([
        ':id' => $profileId,
        ':owner_user_id' => $userId,
    ]);

    $profile = $statement->fetch();
    return $profile ?: null;
}

function fetchProfileByToken(PDO $pdo, string $token): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, owner_user_id, name, view_token, sort_order
         FROM dashboard_profiles
         WHERE view_token = :view_token
         LIMIT 1'
    );
    $statement->execute([':view_token' => $token]);
    $profile = $statement->fetch();
    return $profile ?: null;
}

function countUserProfiles(PDO $pdo, int $userId): int
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM dashboard_profiles
         WHERE owner_user_id = :owner_user_id'
    );
    $statement->execute([':owner_user_id' => $userId]);
    return (int) $statement->fetchColumn();
}

function renameProfile(PDO $pdo, int $userId, int $profileId, string $name): void
{
    $statement = $pdo->prepare(
        'UPDATE dashboard_profiles
         SET name = :name
         WHERE id = :id
           AND owner_user_id = :owner_user_id'
    );
    $statement->execute([
        ':id' => $profileId,
        ':owner_user_id' => $userId,
        ':name' => $name,
    ]);
}

function deleteProfile(PDO $pdo, int $userId, int $profileId): ?int
{
    if (countUserProfiles($pdo, $userId) <= 1) {
        return null;
    }

    $nextProfileStatement = $pdo->prepare(
        'SELECT id
         FROM dashboard_profiles
         WHERE owner_user_id = :owner_user_id
           AND id <> :id
         ORDER BY sort_order, id
         LIMIT 1'
    );
    $nextProfileStatement->execute([
        ':owner_user_id' => $userId,
        ':id' => $profileId,
    ]);
    $nextProfileId = (int) $nextProfileStatement->fetchColumn();

    $delete = $pdo->prepare(
        'DELETE FROM dashboard_profiles
         WHERE id = :id
           AND owner_user_id = :owner_user_id'
    );
    $delete->execute([
        ':id' => $profileId,
        ':owner_user_id' => $userId,
    ]);

    return $nextProfileId > 0 ? $nextProfileId : null;
}

function duplicateProfile(PDO $pdo, int $userId, int $profileId, ?string $newName = null): ?int
{
    $profile = fetchProfileById($pdo, $profileId, $userId);
    if ($profile === null) {
        return null;
    }

    $insertProfile = $pdo->prepare(
        'INSERT INTO dashboard_profiles (owner_user_id, name, view_token, sort_order)
         VALUES (
            :owner_user_id,
            :name,
            :view_token,
            COALESCE((SELECT MAX(sort_order) + 10 FROM dashboard_profiles p WHERE p.owner_user_id = :owner_user_id_for_sort), 10)
         )'
    );
    $insertProfile->execute([
        ':owner_user_id' => $userId,
        ':name' => $newName !== null && trim($newName) !== '' ? trim($newName) : ($profile['name'] . ' Kopie'),
        ':view_token' => generateOpaqueToken(),
        ':owner_user_id_for_sort' => $userId,
    ]);
    $newProfileId = (int) $pdo->lastInsertId();

    $groupStatement = $pdo->prepare(
        'SELECT id, title, card_width_px, align_mode, show_group_title, show_link_icons, show_link_titles, show_link_urls, sort_order
         FROM link_groups
         WHERE owner_user_id = :owner_user_id
           AND profile_id = :profile_id
         ORDER BY sort_order, id'
    );
    $groupStatement->execute([
        ':owner_user_id' => $userId,
        ':profile_id' => $profileId,
    ]);
    $groups = $groupStatement->fetchAll();

    $insertGroup = $pdo->prepare(
        'INSERT INTO link_groups (
            owner_user_id,
            profile_id,
            title,
            card_width_px,
            align_mode,
            show_group_title,
            show_link_icons,
            show_link_titles,
            show_link_urls,
            sort_order
         )
         VALUES (
            :owner_user_id,
            :profile_id,
            :title,
            :card_width_px,
            :align_mode,
            :show_group_title,
            :show_link_icons,
            :show_link_titles,
            :show_link_urls,
            :sort_order
         )'
    );
    $insertLink = $pdo->prepare(
        'INSERT INTO links (group_id, title, url, icon_variant_id, source_type, sort_order)
         VALUES (:group_id, :title, :url, :icon_variant_id, :source_type, :sort_order)'
    );
    $linkStatement = $pdo->prepare(
        'SELECT title, url, icon_variant_id, source_type, sort_order
         FROM links
         WHERE group_id = :group_id
         ORDER BY sort_order, id'
    );

    foreach ($groups as $group) {
        $insertGroup->execute([
            ':owner_user_id' => $userId,
            ':profile_id' => $newProfileId,
            ':title' => $group['title'],
            ':card_width_px' => $group['card_width_px'],
            ':align_mode' => $group['align_mode'],
            ':show_group_title' => (int) ($group['show_group_title'] ?? 1),
            ':show_link_icons' => (int) ($group['show_link_icons'] ?? 1),
            ':show_link_titles' => (int) ($group['show_link_titles'] ?? 1),
            ':show_link_urls' => (int) ($group['show_link_urls'] ?? 1),
            ':sort_order' => $group['sort_order'],
        ]);
        $newGroupId = (int) $pdo->lastInsertId();

        $linkStatement->execute([':group_id' => (int) $group['id']]);
        foreach ($linkStatement->fetchAll() as $link) {
            $insertLink->execute([
                ':group_id' => $newGroupId,
                ':title' => $link['title'],
                ':url' => $link['url'],
                ':icon_variant_id' => $link['icon_variant_id'],
                ':source_type' => $link['source_type'],
                ':sort_order' => $link['sort_order'],
            ]);
        }
    }

    return $newProfileId;
}

function defaultSeedGroups(): array
{
    return [
        ['Interne Seiten', 10],
        ['Externe Seiten', 20],
    ];
}

function defaultSeedLinks(): array
{
    return [
        ['Interne Seiten', 'GitLab', 'https://gitlab.nik0.internal', 'assets/icons/gitlab.svg', 'preset', 10],
        ['Interne Seiten', 'Proxmox', 'https://vm.nik0.internal', 'assets/icons/proxmox.svg', 'preset', 20],
        ['Interne Seiten', 'OpenClaw', 'https://openclaw.nik0.internal', 'assets/icons/openclaw.svg', 'preset', 30],
        ['Interne Seiten', 'ChromeOpenClaw', 'https://chrome.nik0.internal', 'assets/icons/chrome.svg', 'preset', 40],
        ['Interne Seiten', 'Home Assistant', 'https://ha.nik0.internal/', 'assets/icons/homeassistant.svg', 'preset', 50],
        ['Interne Seiten', 'Synology DS920', 'https://synology.nik0.internal/', 'assets/icons/synology.svg', 'preset', 60],
        ['Interne Seiten', 'FRITZ!Box', 'http://192.168.112.1', 'assets/icons/fritzbox.svg', 'preset', 70],
        ['Interne Seiten', 'Portainer', 'https://portainer.nik0.internal', 'assets/icons/docker.svg', 'preset', 80],
        ['Interne Seiten', 'Buha', 'https://buha.nik0.internal', 'assets/icons/buha.svg', 'preset', 90],
        ['Interne Seiten', 'BuhaDB', 'https://buhadb.nik0.internal', 'assets/icons/mariadb.svg', 'preset', 100],
        ['Externe Seiten', 'Amazon', 'https://www.amazon.de', 'assets/icons/amazon-light.svg', 'preset', 10],
        ['Externe Seiten', 'Thingiverse', 'https://www.thingiverse.com', 'assets/icons/thingiverse.svg', 'preset', 20],
        ['Externe Seiten', 'GitHub', 'https://github.com', 'assets/icons/github-light.svg', 'preset', 30],
        ['Externe Seiten', 'ALL-INKL', 'https://kas.all-inkl.com/login', 'assets/icons/all-inkl.svg', 'preset', 40],
        ['Externe Seiten', 'Kindle', 'https://lesen.amazon.de/', 'assets/icons/kindle.svg', 'preset', 50],
    ];
}

function ensureSeedGroupsAndLinksForProfile(PDO $pdo, int $userId, int $profileId): void
{
    $existingGroups = $pdo->prepare(
        'SELECT COUNT(*)
         FROM link_groups
         WHERE profile_id = :profile_id'
    );
    $existingGroups->execute([':profile_id' => $profileId]);
    if ((int) $existingGroups->fetchColumn() > 0) {
        return;
    }

    $insertGroup = $pdo->prepare(
        'INSERT INTO link_groups (owner_user_id, profile_id, title, sort_order)
         VALUES (:owner_user_id, :profile_id, :title, :sort_order)'
    );
    $insertLink = $pdo->prepare(
        'INSERT INTO links (group_id, title, url, icon_variant_id, source_type, sort_order)
         VALUES (:group_id, :title, :url, :icon_variant_id, :source_type, :sort_order)'
    );
    $linkExists = $pdo->prepare(
        'SELECT COUNT(*)
         FROM links l
         INNER JOIN link_groups g ON g.id = l.group_id
         WHERE g.profile_id = :profile_id
           AND g.title = :group_title
           AND l.title = :title
           AND l.url = :url'
    );

    foreach (defaultSeedGroups() as [$title, $sortOrder]) {
        $groupStatement = $pdo->prepare(
            'SELECT id FROM link_groups WHERE profile_id = :profile_id AND title = :title LIMIT 1'
        );
        $groupStatement->execute([
            ':profile_id' => $profileId,
            ':title' => $title,
        ]);
        if ((int) $groupStatement->fetchColumn() <= 0) {
            $insertGroup->execute([
                ':owner_user_id' => $userId,
                ':profile_id' => $profileId,
                ':title' => $title,
                ':sort_order' => $sortOrder,
            ]);
        }
    }

    foreach (defaultSeedLinks() as [$groupTitle, $title, $url, $sourcePath, $sourceType, $sortOrder]) {
        $groupStatement = $pdo->prepare(
            'SELECT id FROM link_groups WHERE profile_id = :profile_id AND title = :title LIMIT 1'
        );
        $groupStatement->execute([
            ':profile_id' => $profileId,
            ':title' => $groupTitle,
        ]);
        $groupId = (int) $groupStatement->fetchColumn();
        $iconVariantId = iconVariantIdBySourcePath($pdo, $sourcePath);
        if ($groupId <= 0) {
            continue;
        }

        $linkExists->execute([
            ':profile_id' => $profileId,
            ':group_title' => $groupTitle,
            ':title' => $title,
            ':url' => $url,
        ]);
        if ((int) $linkExists->fetchColumn() > 0) {
            continue;
        }

        $insertLink->execute([
            ':group_id' => $groupId,
            ':title' => $title,
            ':url' => $url,
            ':icon_variant_id' => $iconVariantId > 0 ? $iconVariantId : null,
            ':source_type' => $sourceType,
            ':sort_order' => $sortOrder,
        ]);
    }
}

function migrateLegacyLinksSchema(PDO $pdo): void
{
    if (tableHasColumn($pdo, 'links', 'group_id')) {
        return;
    }

    if (!tableHasColumn($pdo, 'links', 'section_name')) {
        return;
    }

    $legacyRows = $pdo->query(
        'SELECT user_id, section_name, title, url, icon_path, sort_order, is_placeholder
         FROM links
         ORDER BY user_id, section_name, sort_order, id'
    )->fetchAll();

    $pdo->exec('RENAME TABLE links TO links_legacy');
    ensureSchema($pdo);
    ensureIconLibrary($pdo);

    $groupMap = [];
    $insertGroup = $pdo->prepare(
        'INSERT INTO link_groups (owner_user_id, title, sort_order)
         VALUES (:owner_user_id, :title, :sort_order)'
    );
    $insertLink = $pdo->prepare(
        'INSERT INTO links (group_id, title, url, icon_variant_id, source_type, sort_order)
         VALUES (:group_id, :title, :url, :icon_variant_id, :source_type, :sort_order)'
    );

    foreach ($legacyRows as $row) {
        if ((int) ($row['is_placeholder'] ?? 0) === 1) {
            continue;
        }

        $ownerUserId = $row['user_id'] === null ? null : (int) $row['user_id'];
        $groupKey = ($ownerUserId ?? 'global') . '|' . $row['section_name'];

        if (!isset($groupMap[$groupKey])) {
            $insertGroup->execute([
                ':owner_user_id' => $ownerUserId,
                ':title' => $row['section_name'],
                ':sort_order' => (int) $row['sort_order'],
            ]);
            $groupMap[$groupKey] = (int) $pdo->lastInsertId();
        }

        $iconVariantId = iconVariantIdBySourcePath($pdo, (string) $row['icon_path']);
        if ($iconVariantId <= 0) {
            $iconVariantId = (int) $pdo->query('SELECT id FROM icon_variants ORDER BY id LIMIT 1')->fetchColumn();
        }

        $insertLink->execute([
            ':group_id' => $groupMap[$groupKey],
            ':title' => $row['title'],
            ':url' => $row['url'],
            ':icon_variant_id' => $iconVariantId > 0 ? $iconVariantId : null,
            ':source_type' => $ownerUserId === null ? 'preset' : 'manual',
            ':sort_order' => (int) $row['sort_order'],
        ]);
    }
}

function migrateLegacyGlobalGroupsToOwner(PDO $pdo): void
{
    $ownerStatement = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $ownerStatement->execute([':email' => APP_DEFAULT_OWNER_EMAIL]);
    $ownerUserId = (int) $ownerStatement->fetchColumn();
    if ($ownerUserId <= 0) {
        return;
    }
    $ownerProfileId = ensureDefaultProfile($pdo, $ownerUserId);

    $statement = $pdo->query(
        'SELECT
            g.id AS group_id,
            g.title AS group_title,
            g.sort_order AS group_sort_order,
            l.id AS link_id,
            l.title AS link_title,
            l.url,
            l.source_type,
            l.sort_order AS link_sort_order,
            l.icon_variant_id
         FROM link_groups g
         LEFT JOIN links l ON l.group_id = g.id
         WHERE g.owner_user_id IS NULL
         ORDER BY g.sort_order, g.id, l.sort_order, l.id'
    );
    $rows = $statement->fetchAll();
    if ($rows === []) {
        return;
    }

    $globalGroups = [];
    foreach ($rows as $row) {
        $groupId = (int) $row['group_id'];
        if (!isset($globalGroups[$groupId])) {
            $globalGroups[$groupId] = [
                'title' => $row['group_title'],
                'sort_order' => (int) $row['group_sort_order'],
                'links' => [],
            ];
        }

        if ($row['link_id'] !== null) {
            $globalGroups[$groupId]['links'][] = [
                'title' => $row['link_title'],
                'url' => $row['url'],
                'icon_variant_id' => (int) $row['icon_variant_id'],
                'source_type' => $row['source_type'],
                'sort_order' => (int) $row['link_sort_order'],
            ];
        }
    }

    $insertGroup = $pdo->prepare(
        'INSERT INTO link_groups (owner_user_id, profile_id, title, sort_order)
         VALUES (:owner_user_id, :profile_id, :title, :sort_order)'
    );
    $insertLink = $pdo->prepare(
        'INSERT INTO links (group_id, title, url, icon_variant_id, source_type, sort_order)
         VALUES (:group_id, :title, :url, :icon_variant_id, :source_type, :sort_order)'
    );

    foreach ($globalGroups as $group) {
        $insertGroup->execute([
            ':owner_user_id' => $ownerUserId,
            ':profile_id' => $ownerProfileId,
            ':title' => $group['title'],
            ':sort_order' => $group['sort_order'],
        ]);
        $newGroupId = (int) $pdo->lastInsertId();

        foreach ($group['links'] as $link) {
            $insertLink->execute([
                ':group_id' => $newGroupId,
                ':title' => $link['title'],
                ':url' => $link['url'],
                ':icon_variant_id' => $link['icon_variant_id'],
                ':source_type' => $link['source_type'],
                ':sort_order' => $link['sort_order'],
            ]);
        }
    }

    $deleteLinks = $pdo->prepare(
        'DELETE l FROM links l
         INNER JOIN link_groups g ON g.id = l.group_id
         WHERE g.owner_user_id IS NULL'
    );
    $deleteLinks->execute();
    $pdo->exec('DELETE FROM link_groups WHERE owner_user_id IS NULL');
}

function currentUser(): ?array
{
    static $debugUser = false;

    if ($debugUser === false) {
        $debugUser = resolveDebugUser();
    }

    if (is_array($debugUser)) {
        return $debugUser;
    }

    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool
{
    return currentUser() !== null;
}

function debugToken(): ?string
{
    if (!APP_ALLOW_DEBUG_IMPERSONATION) {
        return null;
    }

    $token = trim((string) ($_GET['dbug'] ?? ''));
    return $token === '' ? null : $token;
}

function viewToken(): ?string
{
    $token = trim((string) ($_GET['token'] ?? ''));
    return $token === '' ? null : $token;
}

function debugEmailForToken(?string $token): ?string
{
    if ($token === null) {
        return null;
    }

    return match ($token) {
        'niko' => APP_DEFAULT_OWNER_EMAIL,
        default => null,
    };
}

function resolveDebugUser(): ?array
{
    $email = debugEmailForToken(debugToken());
    if ($email === null) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT id, email
         FROM users
         WHERE email = :email
         LIMIT 1'
    );
    $statement->execute([':email' => $email]);
    $user = $statement->fetch();

    if (!$user) {
        return null;
    }

    return [
        'id' => (int) $user['id'],
        'email' => $user['email'],
        '_debug' => true,
    ];
}

function isDebugImpersonationActive(): bool
{
    return resolveDebugUser() !== null;
}

function appUrl(string $path, array $params = [], bool $preserveDebug = true): string
{
    $query = [];

    if ($preserveDebug) {
        $token = debugToken();
        if ($token !== null) {
            $query['dbug'] = $token;
        }
    }

    $viewToken = viewToken();
    if ($viewToken !== null) {
        $query['token'] = $viewToken;
    }

    foreach ($params as $key => $value) {
        if ($value === null) {
            continue;
        }
        $query[$key] = (string) $value;
    }

    if ($query === []) {
        return $path;
    }

    return $path . '?' . http_build_query($query);
}

function redirectTo(string $path, bool $preserveDebug = true): never
{
    $parts = parse_url($path);
    $basePath = $parts['path'] ?? $path;
    $params = [];

    if (isset($parts['query'])) {
        parse_str($parts['query'], $params);
    }

    header('Location: ' . appUrl($basePath, $params, $preserveDebug));
    exit;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirectTo('/login.php');
    }
}

function loginUser(array $user): void
{
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'email' => $user['email'],
    ];
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function currentAuthenticatedUser(): ?array
{
    return currentUser();
}

function activeDashboardProfile(PDO $pdo, ?array $authUser): ?array
{
    $token = viewToken();
    if ($token !== null) {
        $profile = fetchProfileByToken($pdo, $token);
        if ($profile !== null) {
            $profile['is_token_view'] = true;
            return $profile;
        }
    }

    if ($authUser === null) {
        return null;
    }

    $defaultProfileId = ensureDefaultProfile($pdo, (int) $authUser['id']);
    $requestedProfileId = (int) ($_GET['profile'] ?? ($_SESSION['active_profile_id'] ?? $defaultProfileId));
    $profile = fetchProfileById($pdo, $requestedProfileId, (int) $authUser['id']);
    if ($profile === null) {
        $profile = fetchProfileById($pdo, $defaultProfileId, (int) $authUser['id']);
    }
    if ($profile !== null) {
        $_SESSION['active_profile_id'] = (int) $profile['id'];
        $profile['is_token_view'] = false;
    }

    return $profile;
}

function currentProfileOwnerIsAuthenticated(?array $authUser, ?array $profile): bool
{
    return $authUser !== null
        && $profile !== null
        && (int) $profile['owner_user_id'] === (int) $authUser['id'];
}

function ensureUserPreferences(PDO $pdo, int $userId): void
{
    $statement = $pdo->prepare(
        'INSERT INTO user_preferences (user_id, card_width_px, column_count)
         VALUES (:user_id, 180, 6)
         ON DUPLICATE KEY UPDATE user_id = user_id'
    );
    $statement->execute([':user_id' => $userId]);
}

function ensureUserStarterData(PDO $pdo, int $userId): void
{
    ensureUserPreferences($pdo, $userId);
    $profileId = ensureDefaultProfile($pdo, $userId);

    $userStatement = $pdo->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
    $userStatement->execute([':id' => $userId]);
    $email = (string) $userStatement->fetchColumn();

    if ($email === APP_DEFAULT_OWNER_EMAIL) {
        ensureSeedGroupsAndLinksForProfile($pdo, $userId, $profileId);
    }
}

function fetchDashboardData(PDO $pdo, ?int $profileId): array
{
    if ($profileId === null) {
        return [];
    } else {
        $statement = $pdo->prepare(
            'SELECT
                g.id AS group_id,
                g.owner_user_id,
                g.profile_id,
                g.title AS group_title,
                g.card_width_px AS group_card_width_px,
                g.align_mode AS group_align_mode,
                g.show_group_title,
                g.show_link_icons,
                g.show_link_titles,
                g.show_link_urls,
                g.sort_order AS group_sort_order,
                l.id AS link_id,
                l.title AS link_title,
                l.url,
                l.source_type,
                l.sort_order AS link_sort_order,
                v.id AS icon_variant_id,
                v.variant_label,
                v.asset_type,
                v.svg_markup,
                v.asset_path,
                v.asset_blob,
                v.mime_type
             FROM link_groups g
             LEFT JOIN links l ON l.group_id = g.id
             LEFT JOIN icon_variants v ON v.id = l.icon_variant_id
             WHERE g.profile_id = :profile_id
             ORDER BY g.sort_order, g.id, l.sort_order, l.id'
        );
        $statement->execute([':profile_id' => $profileId]);
    }

    $groups = [];
    foreach ($statement->fetchAll() as $row) {
        $groupId = (int) $row['group_id'];
        if (!isset($groups[$groupId])) {
            $groups[$groupId] = [
                'id' => $groupId,
                'owner_user_id' => $row['owner_user_id'] === null ? null : (int) $row['owner_user_id'],
                'profile_id' => $row['profile_id'] === null ? null : (int) $row['profile_id'],
                'title' => $row['group_title'],
                'card_width_px' => $row['group_card_width_px'] === null ? null : max(72, min(320, (int) $row['group_card_width_px'])),
                'align_mode' => in_array($row['group_align_mode'], ['left', 'center', 'right'], true) ? $row['group_align_mode'] : 'center',
                'show_group_title' => (int) ($row['show_group_title'] ?? 1) === 1,
                'show_link_icons' => (int) ($row['show_link_icons'] ?? 1) === 1,
                'show_link_titles' => (int) ($row['show_link_titles'] ?? 1) === 1,
                'show_link_urls' => (int) ($row['show_link_urls'] ?? 1) === 1,
                'sort_order' => (int) $row['group_sort_order'],
                'links' => [],
            ];
        }

        if ($row['link_id'] !== null) {
            $groups[$groupId]['links'][] = [
                'id' => (int) $row['link_id'],
                'title' => $row['link_title'],
                'url' => $row['url'],
                'source_type' => $row['source_type'],
                'sort_order' => (int) $row['link_sort_order'],
                'icon_variant_id' => (int) $row['icon_variant_id'],
                'icon_label' => $row['variant_label'],
                'icon_asset_type' => $row['asset_type'],
                'svg_markup' => $row['svg_markup'],
                'icon_asset_path' => $row['asset_path'],
                'icon_asset_blob' => $row['asset_blob'],
                'icon_mime_type' => $row['mime_type'],
            ];
        }
    }

    return array_values($groups);
}

function fetchUserPreferences(PDO $pdo, ?int $userId): array
{
    if ($userId === null) {
        return [
            'card_width_px' => 180,
        ];
    }

    ensureUserPreferences($pdo, $userId);

    $statement = $pdo->prepare('SELECT card_width_px FROM user_preferences WHERE user_id = :user_id');
    $statement->execute([':user_id' => $userId]);
    $preferences = $statement->fetch();

    return [
        'card_width_px' => max(72, min(320, (int) ($preferences['card_width_px'] ?? 180))),
    ];
}

function updateUserPreferences(PDO $pdo, int $userId, int $cardWidthPx): void
{
    ensureUserPreferences($pdo, $userId);

    $statement = $pdo->prepare(
        'UPDATE user_preferences
         SET card_width_px = :card_width_px
         WHERE user_id = :user_id'
    );
    $statement->execute([
        ':user_id' => $userId,
        ':card_width_px' => max(72, min(320, $cardWidthPx)),
    ]);
}

function updateOwnedGroupLayout(
    PDO $pdo,
    int $userId,
    int $profileId,
    int $groupId,
    int $cardWidthPx,
    string $alignMode,
    bool $showGroupTitle,
    bool $showLinkIcons,
    bool $showLinkTitles,
    bool $showLinkUrls
): void
{
    $statement = $pdo->prepare(
        'UPDATE link_groups
         SET card_width_px = :card_width_px,
             align_mode = :align_mode,
             show_group_title = :show_group_title,
             show_link_icons = :show_link_icons,
             show_link_titles = :show_link_titles,
             show_link_urls = :show_link_urls
         WHERE id = :id
           AND owner_user_id = :owner_user_id
           AND profile_id = :profile_id'
    );
    $statement->execute([
        ':id' => $groupId,
        ':owner_user_id' => $userId,
        ':profile_id' => $profileId,
        ':card_width_px' => max(72, min(320, $cardWidthPx)),
        ':align_mode' => in_array($alignMode, ['left', 'center', 'right'], true) ? $alignMode : 'center',
        ':show_group_title' => $showGroupTitle ? 1 : 0,
        ':show_link_icons' => $showLinkIcons ? 1 : 0,
        ':show_link_titles' => $showLinkTitles ? 1 : 0,
        ':show_link_urls' => $showLinkUrls ? 1 : 0,
    ]);
}

function latestGroupLayoutDefaults(PDO $pdo, int $profileId): array
{
    $statement = $pdo->prepare(
        'SELECT card_width_px, align_mode, show_group_title, show_link_icons, show_link_titles, show_link_urls
         FROM link_groups
         WHERE profile_id = :profile_id
         ORDER BY updated_at DESC, id DESC
         LIMIT 1'
    );
    $statement->execute([':profile_id' => $profileId]);
    $row = $statement->fetch();

    return [
        'card_width_px' => max(72, min(320, (int) ($row['card_width_px'] ?? 180))),
        'align_mode' => in_array($row['align_mode'] ?? null, ['left', 'center', 'right'], true) ? $row['align_mode'] : 'center',
        'show_group_title' => (int) ($row['show_group_title'] ?? 1) === 1,
        'show_link_icons' => (int) ($row['show_link_icons'] ?? 1) === 1,
        'show_link_titles' => (int) ($row['show_link_titles'] ?? 1) === 1,
        'show_link_urls' => (int) ($row['show_link_urls'] ?? 1) === 1,
    ];
}

function insertLinkIntoGroup(
    PDO $pdo,
    int $groupId,
    string $title,
    string $url,
    ?int $iconVariantId,
    string $sourceType
): void {
    $insert = $pdo->prepare(
        'INSERT INTO links (group_id, title, url, icon_variant_id, source_type, sort_order)
         VALUES (
            :group_id,
            :title,
            :url,
            :icon_variant_id,
            :source_type,
            COALESCE((SELECT MAX(sort_order) + 10 FROM links l WHERE l.group_id = :group_id_for_sort), 10)
         )'
    );
    $insert->execute([
        ':group_id' => $groupId,
        ':title' => $title,
        ':url' => $url,
        ':icon_variant_id' => $iconVariantId,
        ':source_type' => in_array($sourceType, ['preset', 'manual'], true) ? $sourceType : 'manual',
        ':group_id_for_sort' => $groupId,
    ]);
}

function fetchIconOptions(PDO $pdo): array
{
    $statement = $pdo->query(
        'SELECT
            v.id,
            s.label AS icon_set_label,
            v.variant_label,
            v.asset_type,
            v.svg_markup,
            v.asset_path,
            v.asset_blob,
            v.mime_type
         FROM icon_variants v
         INNER JOIN icon_sets s ON s.id = v.icon_set_id
         WHERE v.source_path IS NOT NULL
         ORDER BY s.label, v.sort_order, v.id'
    );

    return $statement->fetchAll();
}

function fetchReusableLinks(PDO $pdo, int $userId, ?int $excludeGroupId = null): array
{
    $sql = 'SELECT
                l.id,
                l.title,
                l.url,
                l.icon_variant_id,
                l.source_type,
                g.id AS group_id,
                g.title AS group_title,
                p.id AS profile_id,
                p.name AS profile_name
            FROM links l
            INNER JOIN link_groups g ON g.id = l.group_id
            INNER JOIN dashboard_profiles p ON p.id = g.profile_id
            WHERE g.owner_user_id = :owner_user_id';
    $params = [
        ':owner_user_id' => $userId,
    ];

    if ($excludeGroupId !== null && $excludeGroupId > 0) {
        $sql .= ' AND g.id <> :exclude_group_id';
        $params[':exclude_group_id'] = $excludeGroupId;
    }

    $sql .= '
            ORDER BY p.sort_order, p.id, g.sort_order, g.id, l.sort_order, l.id';

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}

function fetchReusableLinkById(PDO $pdo, int $userId, int $linkId): ?array
{
    $statement = $pdo->prepare(
        'SELECT
            l.id,
            l.title,
            l.url,
            l.icon_variant_id,
            l.source_type
         FROM links l
         INNER JOIN link_groups g ON g.id = l.group_id
         WHERE l.id = :id
           AND g.owner_user_id = :owner_user_id
         LIMIT 1'
    );
    $statement->execute([
        ':id' => $linkId,
        ':owner_user_id' => $userId,
    ]);
    $link = $statement->fetch();

    return $link ?: null;
}

function reuseOwnedLink(PDO $pdo, int $userId, int $profileId, int $sourceLinkId, int $targetGroupId): bool
{
    $groupStatement = $pdo->prepare(
        'SELECT id
         FROM link_groups
         WHERE id = :id
           AND owner_user_id = :owner_user_id
           AND profile_id = :profile_id
         LIMIT 1'
    );
    $groupStatement->execute([
        ':id' => $targetGroupId,
        ':owner_user_id' => $userId,
        ':profile_id' => $profileId,
    ]);
    if ((int) $groupStatement->fetchColumn() <= 0) {
        return false;
    }

    $link = fetchReusableLinkById($pdo, $userId, $sourceLinkId);
    if ($link === null) {
        return false;
    }

    insertLinkIntoGroup(
        $pdo,
        $targetGroupId,
        (string) $link['title'],
        (string) $link['url'],
        $link['icon_variant_id'] === null ? null : (int) $link['icon_variant_id'],
        (string) $link['source_type']
    );

    return true;
}

function reorderOwnedLinks(PDO $pdo, int $userId, int $profileId, array $groupOrders): bool
{
    $groupStatement = $pdo->prepare(
        'SELECT id
         FROM link_groups
         WHERE owner_user_id = :owner_user_id
           AND profile_id = :profile_id
         ORDER BY sort_order, id'
    );
    $groupStatement->execute([
        ':owner_user_id' => $userId,
        ':profile_id' => $profileId,
    ]);
    $groupIds = array_map('intval', $groupStatement->fetchAll(PDO::FETCH_COLUMN));
    if ($groupIds === []) {
        return false;
    }

    $groupLookup = array_fill_keys($groupIds, true);

    $linkStatement = $pdo->prepare(
        'SELECT l.id
         FROM links l
         INNER JOIN link_groups g ON g.id = l.group_id
         WHERE g.owner_user_id = :owner_user_id
           AND g.profile_id = :profile_id'
    );
    $linkStatement->execute([
        ':owner_user_id' => $userId,
        ':profile_id' => $profileId,
    ]);
    $existingLinkIds = array_map('intval', $linkStatement->fetchAll(PDO::FETCH_COLUMN));
    $existingLinkLookup = array_fill_keys($existingLinkIds, true);

    $normalizedOrders = [];
    $seenLinkIds = [];

    foreach ($groupOrders as $groupOrder) {
        if (!is_array($groupOrder)) {
            return false;
        }

        $groupId = (int) ($groupOrder['group_id'] ?? 0);
        $linkIds = $groupOrder['link_ids'] ?? null;

        if ($groupId <= 0 || !isset($groupLookup[$groupId]) || isset($normalizedOrders[$groupId]) || !is_array($linkIds)) {
            return false;
        }

        $normalizedOrders[$groupId] = [];
        foreach ($linkIds as $linkIdValue) {
            $linkId = (int) $linkIdValue;
            if ($linkId <= 0 || isset($seenLinkIds[$linkId]) || !isset($existingLinkLookup[$linkId])) {
                return false;
            }

            $seenLinkIds[$linkId] = true;
            $normalizedOrders[$groupId][] = $linkId;
        }
    }

    if (count($seenLinkIds) !== count($existingLinkIds)) {
        return false;
    }

    foreach ($groupIds as $groupId) {
        if (!isset($normalizedOrders[$groupId])) {
            $normalizedOrders[$groupId] = [];
        }
    }

    $update = $pdo->prepare(
        'UPDATE links
         SET group_id = :group_id,
             sort_order = :sort_order
         WHERE id = :id'
    );

    $pdo->beginTransaction();
    try {
        foreach ($groupIds as $groupId) {
            $sortOrder = 10;
            foreach ($normalizedOrders[$groupId] as $linkId) {
                $update->execute([
                    ':group_id' => $groupId,
                    ':sort_order' => $sortOrder,
                    ':id' => $linkId,
                ]);
                $sortOrder += 10;
            }
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    return true;
}

function moveOwnedGroup(PDO $pdo, int $userId, int $profileId, int $groupId, string $direction): void
{
    $statement = $pdo->prepare(
        'SELECT id, sort_order
         FROM link_groups
         WHERE owner_user_id = :owner_user_id
           AND profile_id = :profile_id
         ORDER BY sort_order, id'
    );
    $statement->execute([
        ':owner_user_id' => $userId,
        ':profile_id' => $profileId,
    ]);
    $groups = $statement->fetchAll();
    if ($groups === []) {
        return;
    }

    $index = null;
    foreach ($groups as $position => $group) {
        if ((int) $group['id'] === $groupId) {
            $index = $position;
            break;
        }
    }

    if ($index === null) {
        return;
    }

    if ($direction === 'up' && $index === 0) {
        return;
    }
    if ($direction === 'down' && $index === count($groups) - 1) {
        return;
    }

    $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;
    $temp = $groups[$index];
    $groups[$index] = $groups[$swapIndex];
    $groups[$swapIndex] = $temp;

    $update = $pdo->prepare('UPDATE link_groups SET sort_order = :sort_order WHERE id = :id');
    $sortOrder = 10;
    foreach ($groups as $group) {
        $update->execute([
            ':sort_order' => $sortOrder,
            ':id' => $group['id'],
        ]);
        $sortOrder += 10;
    }
}

function reorderOwnedGroups(PDO $pdo, int $userId, int $profileId, array $groupIds): bool
{
    $statement = $pdo->prepare(
        'SELECT id
         FROM link_groups
         WHERE owner_user_id = :owner_user_id
           AND profile_id = :profile_id
         ORDER BY sort_order, id'
    );
    $statement->execute([
        ':owner_user_id' => $userId,
        ':profile_id' => $profileId,
    ]);
    $existingGroupIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    if ($existingGroupIds === []) {
        return false;
    }

    $normalized = [];
    foreach ($groupIds as $groupId) {
        if (!is_scalar($groupId)) {
            return false;
        }
        $normalized[] = (int) $groupId;
    }

    sort($existingGroupIds);
    $compare = $normalized;
    sort($compare);
    if ($compare !== $existingGroupIds) {
        return false;
    }

    $update = $pdo->prepare('UPDATE link_groups SET sort_order = :sort_order WHERE id = :id');
    foreach (array_values($normalized) as $index => $groupId) {
        $update->execute([
            ':sort_order' => ($index + 1) * 10,
            ':id' => $groupId,
        ]);
    }

    return true;
}

function deleteOwnedGroup(PDO $pdo, int $userId, int $profileId, int $groupId): void
{
    $statement = $pdo->prepare(
        'DELETE FROM link_groups
         WHERE id = :id
           AND owner_user_id = :owner_user_id
           AND profile_id = :profile_id'
    );
    $statement->execute([
        ':id' => $groupId,
        ':owner_user_id' => $userId,
        ':profile_id' => $profileId,
    ]);
}

$pdo = db();
ensureSchema($pdo);
migrateLegacyLinksSchema($pdo);
ensureIconLibrary($pdo);
migrateGroupsToProfiles($pdo);
migrateLegacyGlobalGroupsToOwner($pdo);
