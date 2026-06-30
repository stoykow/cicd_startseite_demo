<?php
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/link_actions.php';
require_once __DIR__ . '/config/link_ui.php';

if (isset($_GET['logout'])) {
    logoutUser();
    redirectTo('/index.php', false);
}

$authUser = currentAuthenticatedUser();
$activeProfile = activeDashboardProfile($pdo, $authUser);
$user = $authUser;
$debugMode = isDebugImpersonationActive();
$userId = $authUser['id'] ?? null;
$activeProfileId = $activeProfile['id'] ?? null;
$canEdit = currentProfileOwnerIsAuthenticated($authUser, $activeProfile);
$tokenViewMode = ($activeProfile['is_token_view'] ?? false) === true;
$editMode = $canEdit && (($_GET['edit'] ?? '0') === '1');
$showAddGroupModal = $canEdit && (($_GET['add_group'] ?? '0') === '1');
$showAddProfileModal = $canEdit && (($_GET['add_profile'] ?? '0') === '1');
$showProfileSettingsModal = $canEdit && (($_GET['profile_settings'] ?? '0') === '1');
$activeGroupId = (int) ($_GET['group_id'] ?? 0);
$editingLinkId = $editMode ? (int) ($_GET['edit_link'] ?? 0) : 0;
$linkModalMode = null;
$editingLink = null;
$editGroupId = $editMode ? (int) ($_GET['edit_group'] ?? 0) : 0;
$formErrors = [];
$linkForm = defaultLinkForm();

if ($editMode && $canEdit && (($_GET['add_link'] ?? '0') === '1')) {
    $linkModalMode = 'create';
} elseif ($editingLinkId > 0) {
    $linkModalMode = 'edit';
}

if ($authUser !== null) {
    ensureUserStarterData($pdo, $userId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_group') {
        requireLogin();
        if (!$canEdit || $activeProfileId === null) {
            redirectTo('/index.php');
        }
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            $formErrors[] = 'Bitte einen Gruppennamen angeben.';
        } else {
            $layoutDefaults = latestGroupLayoutDefaults($pdo, $activeProfileId);
            $insert = $pdo->prepare(
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
                    COALESCE((SELECT MAX(sort_order) + 10 FROM link_groups g WHERE g.profile_id = :profile_id_for_sort), 10)
                 )'
            );
            $insert->execute([
                ':owner_user_id' => $userId,
                ':profile_id' => $activeProfileId,
                ':title' => $title,
                ':card_width_px' => $layoutDefaults['card_width_px'],
                ':align_mode' => $layoutDefaults['align_mode'],
                ':show_group_title' => $layoutDefaults['show_group_title'] ? 1 : 0,
                ':show_link_icons' => $layoutDefaults['show_link_icons'] ? 1 : 0,
                ':show_link_titles' => $layoutDefaults['show_link_titles'] ? 1 : 0,
                ':show_link_urls' => $layoutDefaults['show_link_urls'] ? 1 : 0,
                ':profile_id_for_sort' => $activeProfileId,
            ]);
            redirectTo('/index.php');
        }
    }

    if ($action === 'create_profile') {
        requireLogin();
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $formErrors[] = 'Bitte einen Profilnamen angeben.';
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO dashboard_profiles (owner_user_id, name, view_token, sort_order)
                 VALUES (
                    :owner_user_id,
                    :name,
                    :view_token,
                    COALESCE((SELECT MAX(sort_order) + 10 FROM dashboard_profiles p WHERE p.owner_user_id = :owner_user_id_for_sort), 10)
                 )'
            );
            $insert->execute([
                ':owner_user_id' => $userId,
                ':name' => $name,
                ':view_token' => generateOpaqueToken(),
                ':owner_user_id_for_sort' => $userId,
            ]);
            $_SESSION['active_profile_id'] = (int) $pdo->lastInsertId();
            redirectTo('/index.php?edit=1');
        }
    }

    if ($action === 'duplicate_profile') {
        requireLogin();
        if ($activeProfileId === null || !$canEdit) {
            redirectTo('/index.php');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $newProfileId = duplicateProfile($pdo, $userId, $activeProfileId, $name !== '' ? $name : null);
        if ($newProfileId === null) {
            $formErrors[] = 'Profil konnte nicht dupliziert werden.';
        } else {
            $_SESSION['active_profile_id'] = $newProfileId;
            redirectTo('/index.php?edit=1&profile=' . $newProfileId);
        }
    }

    if ($action === 'rename_profile') {
        requireLogin();
        if ($activeProfileId === null || !$canEdit) {
            redirectTo('/index.php');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $formErrors[] = 'Bitte einen Profilnamen angeben.';
        } else {
            renameProfile($pdo, $userId, $activeProfileId, $name);
            redirectTo('/index.php?edit=1');
        }
    }

    if ($action === 'delete_profile') {
        requireLogin();
        if ($activeProfileId === null || !$canEdit) {
            redirectTo('/index.php');
        }
        $nextProfileId = deleteProfile($pdo, $userId, $activeProfileId);
        if ($nextProfileId === null) {
            $formErrors[] = 'Mindestens ein Profil muss erhalten bleiben.';
        } else {
            $_SESSION['active_profile_id'] = $nextProfileId;
            redirectTo('/index.php?edit=1&profile=' . $nextProfileId);
        }
    }

    if ($action === 'regenerate_profile_token') {
        requireLogin();
        if ($activeProfileId === null || !$canEdit) {
            redirectTo('/index.php');
        }
        $update = $pdo->prepare(
            'UPDATE dashboard_profiles
             SET view_token = :view_token
             WHERE id = :id
               AND owner_user_id = :owner_user_id'
        );
        $update->execute([
            ':view_token' => generateOpaqueToken(),
            ':id' => $activeProfileId,
            ':owner_user_id' => $userId,
        ]);
        redirectTo('/index.php?edit=1');
    }

    if ($action === 'move_group') {
        requireLogin();
        if (!$canEdit || $activeProfileId === null) {
            redirectTo('/index.php');
        }
        moveOwnedGroup($pdo, $userId, $activeProfileId, (int) ($_POST['group_id'] ?? 0), (string) ($_POST['direction'] ?? 'up'));
        redirectTo('/index.php?edit=1');
    }

    if ($action === 'reorder_groups') {
        requireLogin();
        if (!$canEdit || $activeProfileId === null) {
            redirectTo('/index.php');
        }

        $groupOrder = json_decode((string) ($_POST['group_order_payload'] ?? ''), true);
        if (is_array($groupOrder)) {
            reorderOwnedGroups($pdo, (int) $userId, $activeProfileId, $groupOrder);
        }

        redirectTo('/index.php?edit=1');
    }

    if ($action === 'reorder_links') {
        requireLogin();
        if (!$canEdit || $activeProfileId === null) {
            redirectTo('/index.php');
        }

        $orderPayload = json_decode((string) ($_POST['order_payload'] ?? ''), true);
        if (is_array($orderPayload)) {
            reorderOwnedLinks($pdo, $userId, $activeProfileId, $orderPayload);
        }

        redirectTo('/index.php?edit=1');
    }

    if ($action === 'save_group_settings') {
        requireLogin();
        if (!$canEdit || $activeProfileId === null) {
            redirectTo('/index.php');
        }
        $groupId = (int) ($_POST['group_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $cardWidthPx = (int) ($_POST['card_width_px'] ?? 180);
        $alignMode = (string) ($_POST['align_mode'] ?? 'center');
        $showGroupTitle = isset($_POST['show_group_title']);
        $showLinkIcons = isset($_POST['show_link_icons']);
        $showLinkTitles = isset($_POST['show_link_titles']);
        $showLinkUrls = isset($_POST['show_link_urls']);

        if ($title === '') {
            $formErrors[] = 'Bitte einen neuen Gruppennamen angeben.';
        } else {
            $update = $pdo->prepare(
                'UPDATE link_groups
                 SET title = :title
                 WHERE id = :id AND owner_user_id = :owner_user_id AND profile_id = :profile_id'
            );
            $update->execute([
                ':title' => $title,
                ':id' => $groupId,
                ':owner_user_id' => $userId,
                ':profile_id' => $activeProfileId,
            ]);
            updateOwnedGroupLayout(
                $pdo,
                $userId,
                $activeProfileId,
                $groupId,
                $cardWidthPx,
                $alignMode,
                $showGroupTitle,
                $showLinkIcons,
                $showLinkTitles,
                $showLinkUrls
            );
            redirectTo('/index.php?edit=1');
        }
    }

    if ($action === 'delete_group') {
        requireLogin();
        if (!$canEdit || $activeProfileId === null) {
            redirectTo('/index.php');
        }
        deleteOwnedGroup($pdo, $userId, $activeProfileId, (int) ($_POST['group_id'] ?? 0));
        redirectTo('/index.php?edit=1');
    }

    if ($action === 'reuse_link') {
        requireLogin();
        if (!$canEdit || $activeProfileId === null) {
            redirectTo('/index.php');
        }

        $groupId = (int) ($_POST['group_id'] ?? 0);
        $reuseLinkId = (int) ($_POST['reuse_link_id'] ?? 0);
        $linkModalMode = 'create';
        $activeGroupId = $groupId;
        $linkForm['reuse_link_id'] = $reuseLinkId;

        if ($reuseLinkId <= 0) {
            $formErrors[] = 'Bitte eine vorhandene Seite auswaehlen.';
        }

        if ($formErrors === [] && !reuseOwnedLink($pdo, $userId, $activeProfileId, $reuseLinkId, $groupId)) {
            $formErrors[] = 'Die ausgewaehlte Seite konnte nicht uebernommen werden.';
        }

        if ($formErrors === []) {
            redirectTo('/index.php?edit=1');
        }
    }

    if ($action === 'create_link') {
        requireLogin();
        if (!$canEdit || $activeProfileId === null) {
            redirectTo('/index.php');
        }

        $linkModalMode = 'create';
        $linkForm = array_merge($linkForm, normalizeLinkFormInput($_POST));
        $linkForm['reuse_link_id'] = 0;
        $activeGroupId = (int) ($linkForm['group_id'] ?? 0);

        if (!ownedGroupExists($pdo, (int) $userId, $activeProfileId, (int) $linkForm['group_id'])) {
            $formErrors[] = 'Bitte eine eigene Gruppe waehlen.';
        }
        validateLinkForm($linkForm, $formErrors);
        $iconVariantId = $formErrors === [] ? resolveSubmittedIconVariantId($pdo, $linkForm, $_FILES, $formErrors) : null;

        if ($formErrors === []) {
            insertLinkIntoGroup(
                $pdo,
                (int) $linkForm['group_id'],
                (string) $linkForm['title'],
                (string) $linkForm['url'],
                $iconVariantId !== null && $iconVariantId > 0 ? $iconVariantId : null,
                (string) $linkForm['source_type']
            );
            redirectTo('/index.php?edit=1');
        }
    }

    if ($action === 'update_link') {
        requireLogin();
        if (!$canEdit || $activeProfileId === null) {
            redirectTo('/index.php');
        }

        $editingLinkId = (int) ($_POST['link_id'] ?? 0);
        $linkModalMode = 'edit';
        $editingLink = $editingLinkId > 0 ? fetchOwnedLinkForEditing($pdo, (int) $userId, $activeProfileId, $editingLinkId) : null;

        if ($editingLink === null) {
            redirectTo('/index.php?edit=1');
        }

        $linkForm = array_merge(buildLinkFormFromLink($editingLink), normalizeLinkFormInput($_POST));
        $activeGroupId = (int) ($linkForm['group_id'] ?? 0);

        if (!ownedGroupExists($pdo, (int) $userId, $activeProfileId, (int) $linkForm['group_id'])) {
            $formErrors[] = 'Bitte eine eigene Gruppe waehlen.';
        }
        validateLinkForm($linkForm, $formErrors);
        $iconVariantId = $formErrors === [] ? resolveSubmittedIconVariantId($pdo, $linkForm, $_FILES, $formErrors) : null;

        if ($formErrors === []) {
            updateOwnedLink(
                $pdo,
                (int) $userId,
                $activeProfileId,
                $editingLinkId,
                (int) $linkForm['group_id'],
                (string) $linkForm['title'],
                (string) $linkForm['url'],
                $iconVariantId !== null && $iconVariantId > 0 ? $iconVariantId : null,
                (string) $linkForm['source_type']
            );
            redirectTo('/index.php?edit=1');
        }
    }

    if ($action === 'delete_link') {
        requireLogin();
        if (!$canEdit || $activeProfileId === null) {
            redirectTo('/index.php');
        }
        deleteOwnedLink($pdo, (int) $userId, $activeProfileId, (int) ($_POST['link_id'] ?? 0));
        redirectTo('/index.php?edit=1');
    }
}

$preferences = fetchUserPreferences($pdo, $userId);
$cardWidthPx = $preferences['card_width_px'];
$groups = fetchDashboardData($pdo, $activeProfileId);
$iconOptions = [];
$reusableLinks = $authUser !== null ? fetchReusableLinks($pdo, $userId, $activeGroupId > 0 ? $activeGroupId : null) : [];
$profiles = $authUser !== null ? fetchUserProfiles($pdo, $userId) : [];
$profileCount = $authUser !== null ? countUserProfiles($pdo, $userId) : 0;
$currentProfileTokenUrl = $activeProfile !== null
    ? 'https://start.nik0.de' . appUrl('/index.php', ['token' => $activeProfile['view_token']], false)
    : null;

$statement = $pdo->prepare(
    'INSERT INTO dashboard_settings (setting_key, setting_value)
     VALUES (:setting_key, :setting_value)
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
);
$statement->execute([
    ':setting_key' => 'app_last_seen',
    ':setting_value' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
]);

if ($linkModalMode === 'edit' && $authUser !== null && $activeProfileId !== null && $editingLinkId > 0 && $editingLink === null) {
    $editingLink = fetchOwnedLinkForEditing($pdo, (int) $userId, $activeProfileId, $editingLinkId);
    if ($editingLink === null) {
        redirectTo('/index.php?edit=1');
    }
    if ((int) ($linkForm['id'] ?? 0) <= 0) {
        $linkForm = buildLinkFormFromLink($editingLink);
    }
    $activeGroupId = (int) ($linkForm['group_id'] ?? $editingLink['group_id'] ?? 0);
}

if ($linkModalMode === 'create' && (int) ($linkForm['group_id'] ?? 0) <= 0) {
    $linkForm['group_id'] = $activeGroupId;
}

$showLinkModal = $editMode && $canEdit && $linkModalMode !== null;
$modalIconOptions = buildLinkModalIconOptions($iconOptions, $linkForm, $editingLink);

function justifyKeyword(string $alignMode): string
{
    return match ($alignMode) {
        'left' => 'start',
        'right' => 'end',
        default => 'center',
    };
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    :root {
      color-scheme: dark;
      --bg: #08111f;
      --bg-accent: radial-gradient(circle at top, rgba(56, 189, 248, 0.22), transparent 32%),
        linear-gradient(160deg, #08111f 0%, #101f35 55%, #071018 100%);
      --card: rgba(14, 24, 39, 0.86);
      --card-hover: rgba(25, 40, 61, 0.96);
      --text: #f8fafc;
      --muted: #9fb0c8;
      --border: rgba(148, 163, 184, 0.18);
      --shadow: 0 20px 50px rgba(0, 0, 0, 0.32);
      --field: rgba(7, 15, 27, 0.85);
      --success: #22c55e;
      --guest: #64748b;
      --card-width: <?= (int) $cardWidthPx ?>px;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: "Segoe UI", Arial, sans-serif;
      background: var(--bg);
      background-image: var(--bg-accent);
      color: var(--text);
      min-height: 100vh;
      padding: 32px;
      overflow-x: hidden;
    }

    .wrapper {
      width: 100%;
      max-width: min(1680px, calc(100vw - 28px));
      margin: 0 auto;
    }

    .top-actions {
      position: fixed;
      top: 22px;
      right: 22px;
      z-index: 10000;
      display: flex;
      align-items: center;
    }

    .user-fab {
      position: relative;
    }

    .edit-fab-link {
      position: absolute;
      top: -8px;
      right: 44px;
      z-index: 2;
      width: 34px;
      height: 34px;
      border-radius: 10px;
      border: 1px solid var(--border);
      background: rgba(8, 15, 28, 0.94);
      color: var(--text);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      box-shadow: var(--shadow);
      transition: transform 0.15s ease, filter 0.15s ease, background 0.15s ease, color 0.15s ease;
    }

    .edit-fab-link.is-active {
      background: linear-gradient(135deg, #34d399 0%, #059669 100%);
      color: #04111f;
      border-color: rgba(255, 255, 255, 0.1);
    }

    .edit-fab-link:hover {
      transform: translateY(-2px);
      filter: brightness(1.05);
    }

    .edit-fab-link svg {
      width: 17px;
      height: 17px;
      display: block;
    }

    .user-fab-toggle {
      width: 58px;
      height: 58px;
      border-radius: 999px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 1px solid rgba(255, 255, 255, 0.08);
      background: <?= $user ? 'linear-gradient(135deg, #34d399 0%, #059669 100%)' : 'linear-gradient(135deg, #64748b 0%, #334155 100%)' ?>;
      color: #04111f;
      box-shadow: var(--shadow);
      cursor: pointer;
      list-style: none;
      font-size: 1.35rem;
      font-weight: 900;
    }

    .user-fab-toggle::-webkit-details-marker { display: none; }

    .user-fab-panel {
      position: absolute;
      top: 70px;
      right: 0;
      width: min(320px, calc(100vw - 32px));
      padding: 14px;
      border-radius: 20px;
      border: 1px solid var(--border);
      background: rgba(8, 15, 28, 0.96);
      box-shadow: var(--shadow);
      display: none;
    }

    .user-fab[open] .user-fab-panel { display: block; }

    .user-panel-head {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 14px;
    }

    .user-panel-avatar {
      width: 46px;
      height: 46px;
      border-radius: 999px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: <?= $user ? 'linear-gradient(135deg, #34d399 0%, #059669 100%)' : 'linear-gradient(135deg, #64748b 0%, #334155 100%)' ?>;
      color: #04111f;
      font-weight: 900;
    }

    .user-panel-meta strong {
      display: block;
      margin-bottom: 3px;
      font-size: 1rem;
    }

    .user-panel-meta span {
      color: var(--muted);
      font-size: 0.9rem;
      line-height: 1.4;
    }

    .user-panel-actions {
      display: grid;
      gap: 8px;
    }

    .profile-list {
      display: grid;
      gap: 8px;
    }

    .profile-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr);
      align-items: center;
      min-height: 44px;
      border-radius: 14px;
      border: 1px solid var(--border);
      background: rgba(12, 22, 36, 0.86);
      overflow: hidden;
    }

    .profile-row.is-active {
      border-color: rgba(86, 208, 255, 0.52);
      background: linear-gradient(135deg, rgba(35, 200, 255, 0.95) 0%, rgba(23, 136, 255, 0.95) 100%);
      color: #04111f;
    }

    .profile-row.has-edit {
      grid-template-columns: minmax(0, 1fr) 44px;
    }

    .profile-row-link,
    .profile-row-edit {
      min-height: 44px;
      display: inline-flex;
      align-items: center;
      color: inherit;
      text-decoration: none;
    }

    .profile-row-link {
      min-width: 0;
      justify-content: flex-start;
      padding: 0 14px;
      font-weight: 800;
    }

    .profile-row-name {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .profile-row-edit {
      justify-content: center;
      border-left: 1px solid rgba(4, 17, 31, 0.18);
    }

    .profile-row-edit svg {
      width: 17px;
      height: 17px;
      display: block;
    }

    .user-panel-link,
    .action-button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 42px;
      padding: 0 12px;
      border-radius: 14px;
      border: 1px solid var(--border);
      background: rgba(12, 22, 36, 0.86);
      color: var(--text);
      text-decoration: none;
      font-weight: 700;
      font: inherit;
      cursor: pointer;
    }

    .user-panel-link.primary,
    .action-button.primary {
      background: linear-gradient(135deg, #23c8ff 0%, #1788ff 100%);
      color: #04111f;
      border: 0;
    }

    .profile-add-link {
      min-height: 58px;
      gap: 10px;
      border: 1px dashed rgba(86, 208, 255, 0.38);
      background: rgba(9, 17, 31, 0.42);
      color: var(--text);
      font-weight: 800;
    }

    .profile-add-link:hover {
      border-color: rgba(86, 208, 255, 0.75);
      background: rgba(12, 22, 36, 0.72);
    }

    .profile-add-ring {
      width: 30px;
      height: 30px;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 2px solid rgba(86, 208, 255, 0.68);
      color: #56d0ff;
      line-height: 1;
      flex: 0 0 auto;
      box-shadow: inset 0 0 0 4px rgba(86, 208, 255, 0.08);
    }

    .user-panel-legal {
      display: flex;
      justify-content: center;
      gap: 14px;
      padding-top: 4px;
      font-size: 0.78rem;
    }

    .user-panel-legal a {
      color: var(--muted);
      text-decoration: none;
    }

    .user-panel-legal a:hover {
      color: var(--text);
    }

    .section {
      margin-bottom: 26px;
    }

    .hero {
      padding: 0;
      margin: 0 auto 24px;
    }

    .section-title {
      margin: 0 0 12px;
      color: var(--muted);
      font-size: 0.95rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .section-shell {
      padding: 18px;
      border: 1px solid var(--border);
      border-radius: 24px;
      background: rgba(6, 13, 23, 0.5);
      box-shadow: var(--shadow);
      backdrop-filter: blur(12px);
    }

    .hero-shell {
      width: min(620px, 100%);
      margin: 0 auto;
      padding: 14px;
      border: 1px solid var(--border);
      border-radius: 20px;
      background: rgba(6, 13, 23, 0.68);
      box-shadow: var(--shadow);
    }

    .search-form {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 10px;
      width: 100%;
      margin: 0;
      align-items: center;
    }

    .search-input,
    .search-button,
    .builder-field input,
    .builder-field select {
      border-radius: 16px;
      border: 1px solid var(--border);
      font: inherit;
    }

    .search-input,
    .builder-field input,
    .builder-field select {
      width: 100%;
      min-height: 48px;
      padding: 10px 14px;
      color: var(--text);
      background: var(--field);
      outline: none;
    }

    .search-button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 96px;
      min-height: 48px;
      padding: 10px 14px;
      background: linear-gradient(135deg, #23c8ff 0%, #1788ff 100%);
      color: #04111f;
      font-weight: 800;
      cursor: pointer;
    }

    .search-shell-caption {
      display: block;
      margin: 0 0 10px;
      color: var(--muted);
      font-size: 0.78rem;
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .edit-toolbar,
    .group-toolbar,
    .group-create {
      display: grid;
      gap: 12px;
      margin-bottom: 16px;
    }

    .edit-toolbar {
      grid-template-columns: minmax(0, 1fr) auto;
      align-items: end;
    }

    .group-create {
      grid-template-columns: minmax(0, 1fr) auto;
      align-items: end;
    }

    .hero-inline-actions {
      width: min(620px, 100%);
      margin: 12px auto 0;
      display: flex;
      justify-content: center;
    }

    .hero-inline-action {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      min-height: 48px;
      padding: 0 16px;
      border-radius: 16px;
      border: 1px dashed rgba(86, 208, 255, 0.34);
      background: rgba(9, 17, 31, 0.42);
      color: var(--text);
      text-decoration: none;
      font-weight: 800;
      box-shadow: var(--shadow);
      transition: transform 0.15s ease, border-color 0.15s ease, background 0.15s ease;
    }

    .hero-inline-action:hover {
      transform: translateY(-2px);
      border-color: rgba(86, 208, 255, 0.7);
      background: rgba(12, 22, 36, 0.72);
    }

    .group-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
      margin-bottom: 12px;
    }

    .group-title-wrap {
      display: grid;
      gap: 8px;
      min-width: 0;
      flex: 1;
    }

    .group-title-row {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .group-drag-handle {
      min-height: 34px;
      min-width: 34px;
      padding: 0;
      border-radius: 10px;
      border: 1px solid rgba(148, 163, 184, 0.28);
      background: rgba(8, 15, 28, 0.92);
      color: var(--muted);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: grab;
      box-shadow: 0 12px 24px rgba(0, 0, 0, 0.18);
    }

    .group-drag-handle svg {
      width: 18px;
      height: 18px;
      display: block;
    }

    .draggable-group-shell.dragging .section-shell {
      opacity: 0.5;
    }

    .group-actions {
      display: inline-flex;
      gap: 8px;
      flex-wrap: wrap;
      align-items: flex-start;
    }

    .mini-button {
      min-height: 34px;
      min-width: 34px;
      padding: 0 10px;
      border-radius: 10px;
      border: 1px solid var(--border);
      background: rgba(12, 22, 36, 0.86);
      color: var(--text);
      cursor: pointer;
      font: inherit;
      font-weight: 700;
    }

    .rename-form {
      display: flex;
      gap: 8px;
      align-items: center;
      flex-wrap: wrap;
    }

    .group-layout-form {
      display: grid;
      grid-template-columns: minmax(180px, 1.15fr) 72px 160px minmax(300px, 1fr) auto auto;
      gap: 10px;
      align-items: end;
      max-width: 100%;
    }

    .group-layout-form .builder-field {
      margin-bottom: 0;
      min-width: 0;
    }

    .group-layout-form .builder-field label,
    .group-display-field > span {
      display: block;
      margin-bottom: 6px;
      color: var(--muted);
      font-size: 0.78rem;
      font-weight: 800;
    }

    .group-layout-form input,
    .group-layout-form select {
      min-height: 34px;
      padding: 6px 10px;
      border-radius: 12px;
      font-size: 0.86rem;
    }

    .group-layout-form .rename-input {
      min-width: 0;
      min-height: 34px;
    }

    .group-display-options {
      min-height: 34px;
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }

    .group-check {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: var(--muted);
      font-size: 0.82rem;
      font-weight: 800;
      white-space: nowrap;
      cursor: pointer;
    }

    .group-check input {
      width: 16px;
      height: 16px;
      min-height: 0;
      margin: 0;
      padding: 0;
      accent-color: #3b82f6;
    }

    .group-form-action {
      min-height: 34px;
      min-width: 34px;
      padding: 0;
      border-radius: 10px;
      border: 1px solid var(--border);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      font: inherit;
      font-weight: 900;
      cursor: pointer;
    }

    .group-form-action.save {
      background: rgba(22, 101, 52, 0.92);
      color: #dcfce7;
      border-color: rgba(34, 197, 94, 0.35);
    }

    .group-form-action.cancel {
      background: rgba(12, 22, 36, 0.86);
      color: var(--text);
      border-color: var(--border);
    }

    .mini-button.danger {
      background: rgba(127, 29, 29, 0.96);
      color: #fff;
      border-color: rgba(248, 113, 113, 0.35);
    }

    .rename-input {
      min-width: 240px;
      min-height: 38px;
      padding: 8px 12px;
      border-radius: 12px;
      border: 1px solid var(--border);
      background: var(--field);
      color: var(--text);
      font: inherit;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, var(--group-card-width, var(--card-width)));
      gap: 12px;
      justify-content: var(--group-justify, center);
    }

    .grid-item {
      width: var(--group-card-width, var(--card-width));
    }

    .card-shell {
      position: relative;
    }

    .card-shell.is-draggable {
      user-select: none;
    }

    .card-shell.dragging {
      opacity: 0.48;
    }

    .drag-handle {
      position: absolute;
      top: 8px;
      left: 8px;
      z-index: 3;
      width: 32px;
      height: 32px;
      border-radius: 999px;
      border: 1px solid rgba(148, 163, 184, 0.28);
      background: rgba(8, 15, 28, 0.92);
      color: var(--muted);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: grab;
      box-shadow: 0 12px 24px rgba(0, 0, 0, 0.18);
    }

    .drag-handle svg {
      width: 17px;
      height: 17px;
      display: block;
    }

    body.is-sorting .drag-handle {
      cursor: grabbing;
    }

    body.is-sorting .card {
      pointer-events: none;
    }

    .card {
      display: flex;
      flex-direction: column;
      text-decoration: none;
      color: inherit;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 18px;
      overflow: hidden;
      min-height: 116px;
      box-shadow: var(--shadow);
      transition: background 0.15s ease, transform 0.15s ease, border-color 0.15s ease;
    }

    .card:hover {
      background: var(--card-hover);
      border-color: rgba(86, 208, 255, 0.34);
      transform: translateY(-4px);
    }

    .card-icon {
      width: 100%;
      aspect-ratio: 16 / 7.4;
      display: flex;
      align-items: center;
      justify-content: center;
      border-bottom: 1px solid var(--border);
      background:
        radial-gradient(circle at top, rgba(86, 208, 255, 0.16), transparent 44%),
        linear-gradient(180deg, rgba(15, 23, 42, 0.96), rgba(8, 15, 28, 0.98));
      padding: 14px;
    }

    .card-icon svg {
      width: auto;
      height: auto;
      max-width: 110px;
      max-height: 48px;
    }

    .card-content {
      padding: 10px 12px;
      display: flex;
      flex-direction: column;
      gap: 4px;
      flex: 1;
    }

    .title {
      font-size: 0.95rem;
      font-weight: 700;
    }

    .url {
      font-size: 0.82rem;
      color: var(--muted);
      word-break: break-all;
    }

    .card-tool-link {
      position: absolute;
      top: 8px;
      right: 8px;
      z-index: 3;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 34px;
      height: 34px;
      border-radius: 999px;
      border: 1px solid rgba(148, 163, 184, 0.28);
      background: rgba(8, 15, 28, 0.92);
      color: #fff;
      text-decoration: none;
      box-shadow: 0 12px 24px rgba(0, 0, 0, 0.22);
      transition: transform 0.15s ease, border-color 0.15s ease, background 0.15s ease;
    }

    .card-tool-link:hover {
      transform: translateY(-1px);
      border-color: rgba(86, 208, 255, 0.42);
      background: rgba(15, 27, 46, 0.96);
    }

    .card-tool-link svg {
      width: 18px;
      height: 18px;
      display: block;
    }

    .sortable-grid {
      min-height: 128px;
      padding: 4px;
      border-radius: 22px;
      transition: background 0.15s ease, box-shadow 0.15s ease;
    }

    .sortable-grid.is-drop-target {
      background: rgba(14, 165, 233, 0.08);
      box-shadow: inset 0 0 0 1px rgba(86, 208, 255, 0.32);
    }

    .add-card {
      min-height: 116px;
      border-radius: 18px;
      box-shadow: var(--shadow);
    }

    .add-card {
      border: 1px dashed rgba(86, 208, 255, 0.38);
      background: rgba(9, 17, 31, 0.42);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 12px;
      color: var(--text);
      text-decoration: none;
      transition: transform 0.15s ease, border-color 0.15s ease, background 0.15s ease;
    }

    .add-card:hover {
      transform: translateY(-4px);
      border-color: rgba(86, 208, 255, 0.75);
      background: rgba(12, 22, 36, 0.72);
    }

    .add-card-ring {
      width: 54px;
      height: 54px;
      border-radius: 999px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 2px solid rgba(86, 208, 255, 0.72);
      box-shadow: inset 0 0 0 6px rgba(86, 208, 255, 0.08);
    }

    .add-card-plus {
      font-size: 2rem;
      line-height: 1;
      color: #56d0ff;
    }

    .add-card-text {
      font-weight: 700;
      color: var(--muted);
    }

    .status-panel {
      position: fixed;
      right: 20px;
      bottom: 20px;
      max-width: 420px;
      padding: 14px 16px;
      border-radius: 16px;
      border: 1px solid rgba(148, 163, 184, 0.18);
      background: rgba(8, 17, 31, 0.94);
      box-shadow: var(--shadow);
      z-index: 9999;
    }

    .status-head {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 6px;
    }

    .status-dot {
      width: 12px;
      height: 12px;
      border-radius: 999px;
      background: var(--success);
      display: inline-block;
    }

    .status-copy {
      font-size: 14px;
      line-height: 1.5;
      color: #cbd5e1;
    }

    .form-errors {
      margin-bottom: 14px;
      padding: 12px 14px;
      border-radius: 14px;
      border: 1px solid rgba(248, 113, 113, 0.35);
      background: rgba(127, 29, 29, 0.18);
      color: #fecaca;
    }

    .modal-backdrop {
      position: fixed;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      background: rgba(3, 7, 18, 0.72);
      backdrop-filter: blur(8px);
      z-index: 10001;
    }

    .modal-card {
      width: min(640px, 100%);
      max-height: min(calc(100vh - 48px), 920px);
      overflow-y: auto;
      padding: 22px;
      border-radius: 24px;
      border: 1px solid var(--border);
      background: rgba(8, 15, 28, 0.98);
      box-shadow: var(--shadow);
    }

    .builder-field {
      margin-bottom: 12px;
    }

    .builder-field label {
      display: block;
      margin-bottom: 8px;
      color: var(--muted);
      font-size: 0.9rem;
      font-weight: 700;
    }

    .field-hint {
      margin-top: 8px;
      color: var(--muted);
      font-size: 0.82rem;
      line-height: 1.45;
    }

    .modal-divider {
      display: flex;
      align-items: center;
      gap: 14px;
      margin: 18px 0;
      color: var(--muted);
      font-size: 0.82rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .modal-divider::before,
    .modal-divider::after {
      content: "";
      flex: 1;
      height: 1px;
      background: var(--border);
    }

    .reuse-select {
      min-height: 128px;
      max-height: 148px;
    }

    .icon-choice-grid {
      display: grid;
      grid-template-columns: minmax(180px, 1fr) minmax(0, 3fr);
      gap: 10px;
      margin-top: 8px;
    }

    .icon-choice-stack {
      display: grid;
      gap: 10px;
      align-content: start;
    }

    .builder-field label.icon-choice {
      min-height: 48px;
      padding: 10px 12px;
      border: 1px solid var(--border);
      border-radius: 12px;
      background: rgba(12, 22, 36, 0.82);
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
      font-weight: 700;
      color: var(--text);
      min-width: 0;
      margin: 0;
    }

    .icon-choice input[type="radio"] {
      flex: 0 0 auto;
      width: 16px;
      height: 16px;
      margin: 0;
      accent-color: #3b82f6;
    }

    .icon-choice-preview {
      flex: 0 0 52px;
      width: 52px;
      height: 28px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .icon-choice-preview svg {
      width: 52px;
      height: 28px;
      max-width: 52px;
      max-height: 28px;
    }

    .icon-choice-preview img {
      width: 52px;
      height: 28px;
      max-width: 52px;
      max-height: 28px;
      object-fit: contain;
    }

    .icon-choice-text {
      min-width: 0;
      white-space: nowrap;
    }

    .builder-field label.icon-choice-upload {
      align-items: flex-start;
      flex-direction: column;
      min-height: 100%;
    }

    .icon-choice-upload-head {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      align-self: flex-start;
    }

    .icon-choice-upload input[type="file"] {
      width: 100%;
      font: inherit;
      color: var(--muted);
      min-height: 42px;
      padding: 8px 10px;
      border-radius: 12px;
      border: 1px solid var(--border);
      background: rgba(5, 12, 22, 0.62);
    }

    .card-icon img {
      width: auto;
      height: auto;
      max-width: 110px;
      max-height: 48px;
      object-fit: contain;
    }

    .modal-actions {
      display: flex;
      gap: 12px;
      margin-top: 16px;
    }

    .modal-actions > * {
      flex: 1;
    }

    .modal-actions-secondary {
      margin-top: 12px;
    }

    .action-button.danger {
      background: rgba(127, 29, 29, 0.96);
      color: #fff;
    }

    .legal-footer {
      display: flex;
      justify-content: center;
      gap: 16px;
      margin: 28px 0 4px;
      color: var(--muted);
      font-size: 0.82rem;
    }

    .legal-footer a {
      color: var(--muted);
      text-decoration: none;
    }

    .legal-footer a:hover {
      color: var(--text);
    }

    @media (max-width: 860px) {
      body {
        padding: 16px;
      }

      .wrapper {
        max-width: 100%;
      }

      .icon-choice-grid {
        grid-template-columns: 1fr;
      }

      .top-actions {
        top: 14px;
        right: 14px;
      }

      .search-form,
      .edit-toolbar,
      .group-create,
      .group-layout-form {
        grid-template-columns: 1fr;
      }

      .grid {
        grid-template-columns: repeat(auto-fit, minmax(min(var(--group-card-width, var(--card-width)), 100%), var(--group-card-width, var(--card-width))));
      }

      .grid-item {
        width: min(100%, var(--group-card-width, var(--card-width)));
      }

      .group-header {
        flex-direction: column;
      }

      .modal-backdrop {
        align-items: flex-start;
        padding: 12px;
        overflow-y: auto;
      }

      .modal-card {
        margin-top: 68px;
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <div class="top-actions">
    <?php if ($user): ?>
      <a class="edit-fab-link<?= $editMode ? ' is-active' : '' ?>" href="<?= htmlspecialchars($editMode ? appUrl('/index.php') : appUrl('/index.php', ['edit' => 1]), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= $editMode ? 'Bearbeiten beenden' : 'Bearbeiten' ?>" title="<?= $editMode ? 'Bearbeiten beenden' : 'Bearbeiten' ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17.25V21h3.75L17.8 9.94l-3.75-3.75L3 17.25zm14.71-9.04c.39-.39.39-1.02 0-1.41l-2.5-2.5a.996.996 0 0 0-1.41 0l-1.96 1.96 3.75 3.75 2.12-2.1z" fill="currentColor"/></svg>
      </a>
    <?php endif; ?>
    <details class="user-fab">
      <summary class="user-fab-toggle" aria-label="Benutzer-Menue">
        <?= $user ? htmlspecialchars(strtoupper(substr($user['email'], 0, 1)), ENT_QUOTES, 'UTF-8') : '?' ?>
      </summary>
      <div class="user-fab-panel">
        <div class="user-panel-head">
          <div class="user-panel-avatar">
            <?= $user ? htmlspecialchars(strtoupper(substr($user['email'], 0, 1)), ENT_QUOTES, 'UTF-8') : '?' ?>
          </div>
          <div class="user-panel-meta">
            <strong><?= $user ? ($debugMode ? 'Debug aktiv' : 'Eingeloggt') : ($tokenViewMode ? 'Ansichtslink' : 'Nicht eingeloggt') ?></strong>
            <span>
              <?=
                $user
                  ? htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8')
                  : ($activeProfile !== null
                    ? 'Profil: ' . htmlspecialchars($activeProfile['name'], ENT_QUOTES, 'UTF-8')
                    : 'Mit Login bekommst du deine persoenliche Startseite.')
              ?>
            </span>
          </div>
        </div>
        <div class="user-panel-actions">
          <?php if ($user): ?>
            <?php if ($profiles !== []): ?>
              <div class="profile-list" aria-label="Profile">
                <?php foreach ($profiles as $profile): ?>
                  <?php
                  $profileId = (int) $profile['id'];
                  $profileIsActive = $activeProfileId !== null && $profileId === (int) $activeProfileId;
                  $profileParams = ['profile' => $profileId];
                  if ($editMode) {
                      $profileParams['edit'] = 1;
                  }
                  ?>
                  <div class="profile-row<?= $profileIsActive ? ' is-active' : '' ?><?= $editMode ? ' has-edit' : '' ?>">
                    <a class="profile-row-link" href="<?= htmlspecialchars(appUrl('/index.php', $profileParams), ENT_QUOTES, 'UTF-8') ?>">
                      <span class="profile-row-name"><?= htmlspecialchars((string) $profile['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                    <?php if ($editMode): ?>
                      <a class="profile-row-edit" href="<?= htmlspecialchars(appUrl('/index.php', ['edit' => 1, 'profile' => $profileId, 'profile_settings' => 1]), ENT_QUOTES, 'UTF-8') ?>" aria-label="Profil bearbeiten" title="Profil bearbeiten">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 17.25V21h3.75L17.8 9.94l-3.75-3.75L3 17.25zm14.71-9.04c.39-.39.39-1.02 0-1.41l-2.5-2.5a.996.996 0 0 0-1.41 0l-1.96 1.96 3.75 3.75 2.12-2.1z" fill="currentColor"/></svg>
                      </a>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php if ($editMode): ?>
              <a class="user-panel-link profile-add-link" href="<?= htmlspecialchars(appUrl('/index.php', ['edit' => 1, 'add_profile' => 1]), ENT_QUOTES, 'UTF-8') ?>">
                <span class="profile-add-ring">+</span>
                <span>Neues Profil</span>
              </a>
            <?php endif; ?>
            <a class="user-panel-link" href="<?= htmlspecialchars(appUrl('/index.php', ['logout' => 1], false), ENT_QUOTES, 'UTF-8') ?>">Logout</a>
          <?php else: ?>
            <a class="user-panel-link primary" href="<?= htmlspecialchars(appUrl('/login.php'), ENT_QUOTES, 'UTF-8') ?>">Einloggen</a>
            <?php if (APP_ALLOW_REGISTRATION): ?>
              <a class="user-panel-link" href="<?= htmlspecialchars(appUrl('/login.php', ['mode' => 'register']), ENT_QUOTES, 'UTF-8') ?>">Registrieren</a>
            <?php endif; ?>
          <?php endif; ?>
          <div class="user-panel-legal">
            <a href="<?= htmlspecialchars(appUrl('/impressum.php'), ENT_QUOTES, 'UTF-8') ?>">Impressum</a>
            <a href="<?= htmlspecialchars(appUrl('/datenschutz.php'), ENT_QUOTES, 'UTF-8') ?>">Datenschutz</a>
          </div>
        </div>
      </div>
    </details>
  </div>

  <div class="wrapper">
    <section class="section hero">
      <div class="hero-shell">
        <span class="search-shell-caption">Qwant Suche</span>
        <form class="search-form" action="https://www.qwant.com/" method="get">
          <input class="search-input" type="search" name="q" placeholder="Im Web suchen..." aria-label="Qwant Suche" />
          <button class="search-button" type="submit" aria-label="Mit Qwant suchen" title="Mit Qwant suchen">Qwant</button>
        </form>
      </div>
      <?php if ($user && $editMode): ?>
        <div class="hero-inline-actions">
          <a class="hero-inline-action" href="<?= htmlspecialchars(appUrl('/index.php', ['edit' => 1, 'add_group' => 1]), ENT_QUOTES, 'UTF-8') ?>">+ Neue Gruppe</a>
        </div>
      <?php endif; ?>
    </section>

    <?php if ($user && $editMode): ?>
      <?php if ($formErrors !== []): ?>
        <section class="section">
          <div class="section-shell">
            <div class="form-errors"><?= htmlspecialchars(implode(' ', $formErrors), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </section>
      <?php endif; ?>
    <?php endif; ?>

    <div id="group-stack">
    <?php foreach ($groups as $group): ?>
      <?php
      $gridHtml = '';
      $groupIsEditable = $user && $editMode && $group['owner_user_id'] === $userId;
      foreach ($group['links'] as $link) {
          $gridHtml .= renderLinkCard(
              $link,
              $editMode,
              $userId,
              $group['owner_user_id'],
              (bool) ($group['show_link_icons'] ?? true),
              (bool) ($group['show_link_titles'] ?? true),
              (bool) ($group['show_link_urls'] ?? true)
          );
      }
      if ($groupIsEditable) {
          $gridHtml .= renderAddCard((int) $group['id']);
      }
      $gridClasses = 'grid' . ($groupIsEditable ? ' sortable-grid' : '');
      $gridAttributes = $groupIsEditable
          ? sprintf(' data-sortable-group="1" data-group-id="%d"', (int) $group['id'])
          : '';
      ?>
      <section class="section<?= $groupIsEditable ? ' draggable-group-shell' : '' ?>"<?= $groupIsEditable ? sprintf(' data-group-sort-id="%d"', (int) $group['id']) : '' ?>>
        <div class="section-shell">
          <div class="group-header">
            <div class="group-title-wrap">
              <div class="group-title-row">
                <?php if (($group['show_group_title'] ?? true) || $groupIsEditable): ?>
                  <h2 class="section-title" style="margin:0;<?= ($group['show_group_title'] ?? true) ? '' : ' visibility:hidden; width:0; overflow:hidden;' ?>"><?= htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                <?php endif; ?>
                <?php if ($groupIsEditable): ?>
                  <div class="group-drag-handle" draggable="true" aria-label="Gruppe verschieben" title="Verschieben"><?= renderMoveIcon() ?></div>
                  <a class="mini-button" href="<?= htmlspecialchars(appUrl('/index.php', ['edit' => 1, 'edit_group' => (int) $group['id']]), ENT_QUOTES, 'UTF-8') ?>" aria-label="Gruppe bearbeiten">✎</a>
                <?php endif; ?>
              </div>
              <?php if ($groupIsEditable && $editGroupId === (int) $group['id']): ?>
                <form class="group-layout-form" method="post" action="<?= htmlspecialchars(appUrl('/index.php', ['edit' => 1]), ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="save_group_settings" />
                  <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>" />
                  <div class="builder-field">
                    <label for="group_name_<?= (int) $group['id'] ?>">Name</label>
                    <input id="group_name_<?= (int) $group['id'] ?>" class="rename-input" name="title" type="text" value="<?= htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8') ?>" required />
                  </div>
                  <div class="builder-field">
                    <label for="group_width_<?= (int) $group['id'] ?>">Breite</label>
                    <input id="group_width_<?= (int) $group['id'] ?>" name="card_width_px" type="number" min="72" max="320" value="<?= (int) ($group['card_width_px'] ?? $cardWidthPx) ?>" />
                  </div>
                  <div class="builder-field">
                    <label for="group_align_<?= (int) $group['id'] ?>">Ausrichtung</label>
                    <select id="group_align_<?= (int) $group['id'] ?>" name="align_mode">
                      <option value="left" <?= ($group['align_mode'] ?? 'center') === 'left' ? 'selected' : '' ?>>Linksbuendig</option>
                      <option value="center" <?= ($group['align_mode'] ?? 'center') === 'center' ? 'selected' : '' ?>>Zentriert</option>
                      <option value="right" <?= ($group['align_mode'] ?? 'center') === 'right' ? 'selected' : '' ?>>Rechtsbuendig</option>
                    </select>
                  </div>
                  <div class="group-display-field">
                    <span>Anzeigen</span>
                    <div class="group-display-options">
                      <label class="group-check"><input type="checkbox" name="show_group_title" <?= ($group['show_group_title'] ?? true) ? 'checked' : '' ?> /> Gruppentitel</label>
                      <label class="group-check"><input type="checkbox" name="show_link_icons" <?= ($group['show_link_icons'] ?? true) ? 'checked' : '' ?> /> Icons</label>
                      <label class="group-check"><input type="checkbox" name="show_link_titles" <?= ($group['show_link_titles'] ?? true) ? 'checked' : '' ?> /> Titel</label>
                      <label class="group-check"><input type="checkbox" name="show_link_urls" <?= ($group['show_link_urls'] ?? true) ? 'checked' : '' ?> /> URL</label>
                    </div>
                  </div>
                  <button class="group-form-action save" type="submit" aria-label="Speichern" title="Speichern">&#10003;</button>
                  <a class="group-form-action cancel" href="<?= htmlspecialchars(appUrl('/index.php', ['edit' => 1]), ENT_QUOTES, 'UTF-8') ?>" aria-label="Abbrechen" title="Abbrechen">&#8634;</a>
                </form>
              <?php endif; ?>
            </div>
            <?php if ($groupIsEditable): ?>
              <div class="group-actions">
                <form method="post" action="<?= htmlspecialchars(appUrl('/index.php', ['edit' => 1]), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Gruppe wirklich loeschen?');">
                  <input type="hidden" name="action" value="delete_group" />
                  <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>" />
                  <button class="mini-button danger" type="submit" aria-label="Gruppe loeschen" title="Gruppe loeschen">X</button>
                </form>
              </div>
            <?php endif; ?>
          </div>
          <div class="<?= $gridClasses ?>"<?= $gridAttributes ?> style="--group-card-width: <?= (int) ($group['card_width_px'] ?? $cardWidthPx) ?>px; --group-justify: <?= htmlspecialchars(justifyKeyword((string) ($group['align_mode'] ?? 'center')), ENT_QUOTES, 'UTF-8') ?>;"><?= $gridHtml ?></div>
        </div>
      </section>
    <?php endforeach; ?>
    </div>
    <footer class="legal-footer">
      <a href="<?= htmlspecialchars(appUrl('/impressum.php'), ENT_QUOTES, 'UTF-8') ?>">Impressum</a>
      <a href="<?= htmlspecialchars(appUrl('/datenschutz.php'), ENT_QUOTES, 'UTF-8') ?>">Datenschutz</a>
    </footer>
  </div>

  <?php if ($user && $editMode): ?>
    <form id="link-order-form" method="post" action="<?= htmlspecialchars(appUrl('/index.php', ['edit' => 1]), ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="action" value="reorder_links" />
      <input id="order_payload" type="hidden" name="order_payload" value="" />
    </form>
    <form id="group-order-form" method="post" action="<?= htmlspecialchars(appUrl('/index.php', ['edit' => 1]), ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="action" value="reorder_groups" />
      <input id="group_order_payload" type="hidden" name="group_order_payload" value="" />
    </form>
  <?php endif; ?>

  <?php if ($showLinkModal && $user): ?>
    <?= renderLinkModal($linkModalMode, $linkForm, $formErrors, $modalIconOptions, $reusableLinks, (int) $activeGroupId) ?>
  <?php endif; ?>

  <?php if ($showAddGroupModal && $user): ?>
    <div class="modal-backdrop">
      <div class="modal-card">
        <h2 class="section-title" style="margin-bottom:16px;">Neue Gruppe anlegen</h2>
        <?php if ($formErrors !== []): ?>
          <div class="form-errors"><?= htmlspecialchars(implode(' ', $formErrors), ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post" action="<?= htmlspecialchars(appUrl('/index.php', ['add_group' => 1]), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="create_group" />
          <div class="builder-field">
            <label for="modal_group_title">Gruppenname</label>
            <input id="modal_group_title" name="title" type="text" placeholder="z. B. Tools, Haushalt, Medien" required />
          </div>
          <div class="modal-actions">
            <button class="action-button primary" type="submit">Gruppe speichern</button>
            <a class="user-panel-link" href="<?= htmlspecialchars(appUrl('/index.php'), ENT_QUOTES, 'UTF-8') ?>">Abbrechen</a>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($showAddProfileModal && $user): ?>
    <div class="modal-backdrop">
      <div class="modal-card">
        <h2 class="section-title" style="margin-bottom:16px;">Neues Profil anlegen</h2>
        <?php if ($formErrors !== []): ?>
          <div class="form-errors"><?= htmlspecialchars(implode(' ', $formErrors), ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post" action="<?= htmlspecialchars(appUrl('/index.php', ['edit' => 1, 'add_profile' => 1]), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="create_profile" />
          <div class="builder-field">
            <label for="modal_profile_name">Profilname</label>
            <input id="modal_profile_name" name="name" type="text" placeholder="z. B. Surface, Tablet, Arbeit" required />
          </div>
          <div class="modal-actions">
            <button class="action-button primary" type="submit">Profil speichern</button>
            <a class="user-panel-link" href="<?= htmlspecialchars(appUrl('/index.php', ['edit' => 1]), ENT_QUOTES, 'UTF-8') ?>">Abbrechen</a>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($showProfileSettingsModal && $user && $activeProfile !== null): ?>
    <div class="modal-backdrop">
      <div class="modal-card">
        <h2 class="section-title" style="margin-bottom:16px;">Profil bearbeiten</h2>
        <?php if ($formErrors !== []): ?>
          <div class="form-errors"><?= htmlspecialchars(implode(' ', $formErrors), ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post" action="<?= htmlspecialchars(appUrl('/index.php', ['edit' => 1, 'profile_settings' => 1]), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="rename_profile" />
          <div class="builder-field">
            <label for="profile_settings_name">Profilname</label>
            <input id="profile_settings_name" name="name" type="text" value="<?= htmlspecialchars($activeProfile['name'], ENT_QUOTES, 'UTF-8') ?>" required />
          </div>
          <div class="modal-actions">
            <button class="action-button primary" type="submit">Profil speichern</button>
          </div>
        </form>

        <?php if ($currentProfileTokenUrl !== null): ?>
          <div class="builder-field" style="margin-top:16px;">
            <label for="profile_token_url">Token-Webseite</label>
            <input id="profile_token_url" type="text" value="<?= htmlspecialchars((string) $currentProfileTokenUrl, ENT_QUOTES, 'UTF-8') ?>" readonly />
          </div>
          <div class="modal-actions">
            <form method="post" action="<?= htmlspecialchars(appUrl('/index.php', ['edit' => 1, 'profile_settings' => 1]), ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="regenerate_profile_token" />
              <button class="action-button" type="submit">Token neu erzeugen</button>
            </form>
            <button class="mini-button" type="button" onclick="copyProfileToken()">Kopieren</button>
          </div>
        <?php endif; ?>

        <div class="modal-actions" style="margin-top:16px;">
          <?php if ($profileCount > 1): ?>
            <form method="post" action="<?= htmlspecialchars(appUrl('/index.php', ['edit' => 1, 'profile_settings' => 1]), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Profil wirklich loeschen?');">
              <input type="hidden" name="action" value="delete_profile" />
              <button class="action-button danger" type="submit">Profil loeschen</button>
            </form>
          <?php endif; ?>
          <a class="user-panel-link" href="<?= htmlspecialchars(appUrl('/index.php', ['edit' => 1]), ENT_QUOTES, 'UTF-8') ?>">Schliessen</a>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <script>
    function copyProfileToken() {
      const input = document.getElementById('profile_token_url');
      if (!input) {
        return;
      }
      input.select();
      input.setSelectionRange(0, input.value.length);
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(input.value).catch(() => document.execCommand('copy'));
        return;
      }
      document.execCommand('copy');
    }

    (function () {
      const iconUploadInput = document.getElementById('icon_svg');
      const uploadModeInput = document.querySelector('input[name="icon_mode"][value="upload"]');
      if (!iconUploadInput || !uploadModeInput) {
        return;
      }

      iconUploadInput.addEventListener('change', () => {
        if (iconUploadInput.files && iconUploadInput.files.length > 0) {
          uploadModeInput.checked = true;
        }
      });
    })();

    (function () {
      const orderForm = document.getElementById('link-order-form');
      const payloadInput = document.getElementById('order_payload');
      if (!orderForm || !payloadInput) {
        return;
      }

      const sortableGrids = Array.from(document.querySelectorAll('[data-sortable-group="1"]'));
      if (sortableGrids.length === 0) {
        return;
      }

      const dragItems = Array.from(document.querySelectorAll('.card-shell.is-draggable[data-link-id]'));
      let draggedItem = null;
      let initialPayload = '';
      let isPersistingOrder = false;
      let suppressClicksUntil = 0;

      async function persistOrder(payload) {
        if (isPersistingOrder) {
          return;
        }

        isPersistingOrder = true;
        const formData = new FormData();
        formData.set('action', 'reorder_links');
        formData.set('order_payload', payload);

        try {
          const response = await fetch(orderForm.action, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
            },
          });

          if (!response.ok) {
            throw new Error('Reorder request failed');
          }
        } catch (error) {
          payloadInput.value = payload;
          orderForm.requestSubmit();
          return;
        } finally {
          isPersistingOrder = false;
        }
      }

      function serializeOrder() {
        return JSON.stringify(
          sortableGrids.map((grid) => ({
            group_id: Number.parseInt(grid.dataset.groupId || '0', 10),
            link_ids: Array.from(grid.querySelectorAll('.card-shell[data-link-id]')).map((item) =>
              Number.parseInt(item.dataset.linkId || '0', 10)
            ),
          }))
        );
      }

      function getDropPlacement(grid, clientX, clientY) {
        const hoveredElement = document.elementFromPoint(clientX, clientY);
        const hoveredItem = hoveredElement instanceof Element
          ? hoveredElement.closest('.card-shell.is-draggable:not(.dragging)')
          : null;

        if (hoveredItem && grid.contains(hoveredItem)) {
          const box = hoveredItem.getBoundingClientRect();
          const insertBefore =
            clientY < box.top + box.height / 2
            || (Math.abs(clientY - (box.top + box.height / 2)) < 12 && clientX < box.left + box.width / 2);

          return {
            reference: insertBefore ? hoveredItem : hoveredItem.nextElementSibling,
          };
        }

        const items = Array.from(grid.querySelectorAll('.card-shell.is-draggable:not(.dragging)'));
        if (items.length === 0) {
          return { reference: grid.querySelector('.add-card') };
        }

        let nearestItem = null;
        let nearestDistance = Number.POSITIVE_INFINITY;

        items.forEach((item) => {
          const box = item.getBoundingClientRect();
          const centerX = box.left + box.width / 2;
          const centerY = box.top + box.height / 2;
          const distance = Math.hypot(clientX - centerX, clientY - centerY);
          if (distance < nearestDistance) {
            nearestDistance = distance;
            nearestItem = item;
          }
        });

        if (!nearestItem) {
          return { reference: grid.querySelector('.add-card') };
        }

        const nearestBox = nearestItem.getBoundingClientRect();
        const nearestCenterY = nearestBox.top + nearestBox.height / 2;
        const insertBefore = clientY < nearestCenterY;

        return {
          reference: insertBefore ? nearestItem : nearestItem.nextElementSibling,
        };
      }

      dragItems.forEach((item) => {
        const handle = item.querySelector('.drag-handle');
        if (!handle) {
          return;
        }

        handle.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();
        });

        handle.addEventListener('dragstart', (event) => {
          draggedItem = item;
          initialPayload = serializeOrder();
          item.classList.add('dragging');
          document.body.classList.add('is-sorting');
          if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', item.dataset.linkId || '');
          }
        });

        handle.addEventListener('dragend', () => {
          suppressClicksUntil = Date.now() + 400;
          item.classList.remove('dragging');
          sortableGrids.forEach((grid) => grid.classList.remove('is-drop-target'));
          document.body.classList.remove('is-sorting');

          if (!draggedItem) {
            return;
          }

          const updatedPayload = serializeOrder();
          draggedItem = null;
          if (updatedPayload !== initialPayload) {
            persistOrder(updatedPayload);
          }
        });
      });

      document.addEventListener('click', (event) => {
        if (Date.now() > suppressClicksUntil) {
          return;
        }

        const target = event.target;
        if (!(target instanceof Element)) {
          return;
        }

        if (target.closest('.card') || target.closest('.drag-handle') || target.closest('.card-shell')) {
          event.preventDefault();
          event.stopPropagation();
        }
      }, true);

      sortableGrids.forEach((grid) => {
        grid.addEventListener('dragover', (event) => {
          if (!draggedItem) {
            return;
          }

          event.preventDefault();
          grid.classList.add('is-drop-target');
          const addCard = grid.querySelector('.add-card');
          const placement = getDropPlacement(grid, event.clientX, event.clientY);
          const reference = placement.reference instanceof Element ? placement.reference : null;
          grid.insertBefore(draggedItem, reference || addCard || null);
        });

        grid.addEventListener('dragleave', (event) => {
          const relatedTarget = event.relatedTarget;
          if (!(relatedTarget instanceof Node) || !grid.contains(relatedTarget)) {
            grid.classList.remove('is-drop-target');
          }
        });

        grid.addEventListener('drop', (event) => {
          event.preventDefault();
          grid.classList.remove('is-drop-target');
        });
      });
    })();

    (function () {
      const groupOrderForm = document.getElementById('group-order-form');
      const groupOrderInput = document.getElementById('group_order_payload');
      const groupStack = document.getElementById('group-stack');
      if (!groupOrderForm || !groupOrderInput || !groupStack) {
        return;
      }

      const groupSections = Array.from(groupStack.querySelectorAll('.draggable-group-shell[data-group-sort-id]'));
      if (groupSections.length === 0) {
        return;
      }

      let draggedGroup = null;
      let initialGroupPayload = '';
      let isPersistingGroups = false;

      function serializeGroupOrder() {
        return JSON.stringify(
          Array.from(groupStack.querySelectorAll('.draggable-group-shell[data-group-sort-id]')).map((section) =>
            Number.parseInt(section.dataset.groupSortId || '0', 10)
          )
        );
      }

      async function persistGroupOrder(payload) {
        if (isPersistingGroups) {
          return;
        }

        isPersistingGroups = true;
        const formData = new FormData();
        formData.set('action', 'reorder_groups');
        formData.set('group_order_payload', payload);

        try {
          const response = await fetch(groupOrderForm.action, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
            },
          });

          if (!response.ok) {
            throw new Error('Group reorder request failed');
          }
        } catch (error) {
          groupOrderInput.value = payload;
          groupOrderForm.requestSubmit();
          return;
        } finally {
          isPersistingGroups = false;
        }
      }

      function getGroupReference(clientY) {
        const sections = Array.from(groupStack.querySelectorAll('.draggable-group-shell:not(.dragging)'));
        let closest = null;
        let closestOffset = Number.NEGATIVE_INFINITY;

        sections.forEach((section) => {
          const box = section.getBoundingClientRect();
          const offset = clientY - box.top - box.height / 2;
          if (offset < 0 && offset > closestOffset) {
            closestOffset = offset;
            closest = section;
          }
        });

        return closest;
      }

      groupSections.forEach((section) => {
        const handle = section.querySelector('.group-drag-handle');
        if (!handle) {
          return;
        }

        handle.addEventListener('dragstart', (event) => {
          draggedGroup = section;
          initialGroupPayload = serializeGroupOrder();
          section.classList.add('dragging');
          if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', section.dataset.groupSortId || '');
          }
        });

        handle.addEventListener('dragend', () => {
          section.classList.remove('dragging');
          if (!draggedGroup) {
            return;
          }

          const updatedPayload = serializeGroupOrder();
          draggedGroup = null;
          if (updatedPayload !== initialGroupPayload) {
            persistGroupOrder(updatedPayload);
          }
        });
      });

      groupStack.addEventListener('dragover', (event) => {
        if (!draggedGroup) {
          return;
        }

        event.preventDefault();
        const reference = getGroupReference(event.clientY);
        groupStack.insertBefore(draggedGroup, reference);
      });

      groupStack.addEventListener('drop', (event) => {
        if (draggedGroup) {
          event.preventDefault();
        }
      });
    })();
  </script>
</body>
</html>
