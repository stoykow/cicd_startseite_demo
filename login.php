<?php
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

if (isLoggedIn()) {
    redirectTo('/index.php');
}

$mode = ($_GET['mode'] ?? 'login') === 'register' ? 'register' : 'login';
if (!APP_ALLOW_REGISTRATION) {
    $mode = 'login';
}
$errors = [];
$successMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'register') {
        if (!APP_ALLOW_REGISTRATION) {
            $errors[] = 'Registrierung ist aktuell deaktiviert.';
            $mode = 'login';
        }
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if (APP_ALLOW_REGISTRATION && ($email === '' || $password === '')) {
            $errors[] = 'Bitte alle Pflichtfelder ausfuellen.';
        }
        if (APP_ALLOW_REGISTRATION && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Bitte eine gueltige E-Mail-Adresse eingeben.';
        }
        if (APP_ALLOW_REGISTRATION && strlen($password) < 8) {
            $errors[] = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
        }
        if (APP_ALLOW_REGISTRATION && $password !== $passwordConfirm) {
            $errors[] = 'Die Passwoerter stimmen nicht ueberein.';
        }

        if (APP_ALLOW_REGISTRATION && $errors === []) {
            $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $check->execute([
                ':email' => $email,
            ]);

            if ($check->fetch()) {
                $errors[] = 'Diese E-Mail ist bereits registriert.';
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO users (username, email, password_hash)
                     VALUES (:username, :email, :password_hash)'
                );
                $insert->execute([
                    ':username' => $email,
                    ':email' => $email,
                    ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);

                $userId = (int) $pdo->lastInsertId();
                ensureUserStarterData($pdo, $userId);

                loginUser([
                    'id' => $userId,
                    'email' => $email,
                ]);

                redirectTo('/index.php');
            }
        }

        $mode = 'register';
    }

    if ($action === 'login') {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $errors[] = 'Bitte E-Mail und Passwort eingeben.';
        } else {
            $statement = $pdo->prepare(
                'SELECT id, email, password_hash
                 FROM users
                 WHERE email = :email
                 LIMIT 1'
            );
            $statement->execute([':email' => $email]);
            $user = $statement->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $errors[] = 'Login fehlgeschlagen. Bitte Eingaben pruefen.';
            } else {
                ensureUserStarterData($pdo, (int) $user['id']);
                loginUser($user);
                redirectTo('/index.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login | <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars(appUrl('/config/auth.css'), ENT_QUOTES, 'UTF-8') ?>" />
</head>
<body>
  <main class="auth-shell">
    <section class="auth-brand">
      <div>
        <div class="eyebrow">Persoenliche Startseite</div>
        <h1>Dein Dashboard mit Login, Profil und eigenen Links.</h1>
        <p class="lead">
          Melde dich an oder registriere dich, um eine persoenliche Startseite zu erhalten.
          Standardlinks bleiben sichtbar, persoenliche Links kommen als eigener Bereich dazu.
        </p>
      </div>
      <div class="feature-list">
        <div class="feature">
          <h3>Persoenlich</h3>
          <p class="lead">Jeder Benutzer bekommt einen eigenen Bereich "Meine Startseite".</p>
        </div>
        <div class="feature">
          <h3>Zentral</h3>
          <p class="lead">Die zentrale Startseite bleibt fuer alle verfuegbar und wird aus der Datenbank geladen.</p>
        </div>
        <div class="feature">
          <h3>Erweiterbar</h3>
          <p class="lead">Die Struktur ist so vorbereitet, dass spaeter ein Link-Editor dazugebaut werden kann.</p>
        </div>
      </div>
    </section>

    <section class="auth-card">
      <div class="tabs">
        <a class="tab-link <?= $mode === 'login' ? 'is-active' : '' ?>" href="<?= htmlspecialchars(appUrl('/login.php', ['mode' => 'login']), ENT_QUOTES, 'UTF-8') ?>">Login</a>
        <?php if (APP_ALLOW_REGISTRATION): ?>
          <a class="tab-link <?= $mode === 'register' ? 'is-active' : '' ?>" href="<?= htmlspecialchars(appUrl('/login.php', ['mode' => 'register']), ENT_QUOTES, 'UTF-8') ?>">Register</a>
        <?php endif; ?>
      </div>

      <?php foreach ($errors as $error): ?>
        <div class="flash flash-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endforeach; ?>

      <?php if ($successMessage !== null): ?>
        <div class="flash flash-success"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <?php if ($mode === 'login'): ?>
        <h2>Einloggen</h2>
        <form method="post" action="<?= htmlspecialchars(appUrl('/login.php', ['mode' => 'login']), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="login" />
          <div class="field">
            <label for="login-email">E-Mail</label>
            <input id="login-email" name="email" type="email" required />
          </div>
          <div class="field">
            <label for="password">Passwort</label>
            <input id="password" name="password" type="password" required />
          </div>
          <button class="button" type="submit">Login</button>
        </form>
        <p class="small-text">Noch kein Konto? <a href="<?= htmlspecialchars(appUrl('/login.php', ['mode' => 'register']), ENT_QUOTES, 'UTF-8') ?>">Jetzt registrieren</a></p>
      <?php elseif (APP_ALLOW_REGISTRATION): ?>
        <h2>Registrieren</h2>
        <form method="post" action="<?= htmlspecialchars(appUrl('/login.php', ['mode' => 'register']), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="register" />
          <div class="field">
            <label for="email">E-Mail</label>
            <input id="email" name="email" type="email" required />
          </div>
          <div class="field">
            <label for="register-password">Passwort</label>
            <input id="register-password" name="password" type="password" required />
          </div>
          <div class="field">
            <label for="password-confirm">Passwort wiederholen</label>
            <input id="password-confirm" name="password_confirm" type="password" required />
          </div>
          <button class="button" type="submit">Konto anlegen</button>
        </form>
        <p class="small-text">Schon registriert? <a href="<?= htmlspecialchars(appUrl('/login.php', ['mode' => 'login']), ENT_QUOTES, 'UTF-8') ?>">Zum Login</a></p>
      <?php else: ?>
        <h2>Registrierung deaktiviert</h2>
        <p class="lead">Neue Konten koennen aktuell nur manuell angelegt werden.</p>
        <p class="small-text"><a href="<?= htmlspecialchars(appUrl('/login.php', ['mode' => 'login']), ENT_QUOTES, 'UTF-8') ?>">Zum Login</a></p>
      <?php endif; ?>
    </section>
    <footer class="legal-footer">
      <a href="<?= htmlspecialchars(appUrl('/impressum.php'), ENT_QUOTES, 'UTF-8') ?>">Impressum</a>
      <a href="<?= htmlspecialchars(appUrl('/datenschutz.php'), ENT_QUOTES, 'UTF-8') ?>">Datenschutz</a>
    </footer>
  </main>
</body>
</html>
