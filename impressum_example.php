<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Impressum | <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="/config/legal.css" />
</head>
<body>
  <main class="legal-shell">
    <a class="back-link" href="/index.php">Zurück zur Startseite</a>
    <section class="legal-card">
      <h1>Impressum</h1>

      <h4>Muster für eine private Projektseite</h4>
      <p>Diese Datei ist ein Beispiel. Ersetzen Sie die Platzhalter vor einer Veröffentlichung durch eigene Angaben.</p>

      <h4>Verantwortlich für den Inhalt</h4>
      <p>
        Vorname Nachname<br />
        Straße Hausnummer<br />
        PLZ Ort
      </p>

      <h5>Kontakt</h5>
      <p>E-Mail: <a href="mailto:mail@example.test">mail@example.test</a></p>

      <hr />

      <h5>Haftung für Inhalte</h5>
      <p>Die Inhalte dieser privaten Website wurden mit Sorgfalt erstellt. Für die Richtigkeit, Vollständigkeit und Aktualität der Inhalte kann jedoch keine Gewähr übernommen werden. Sobald konkrete Rechtsverletzungen bekannt werden, werden entsprechende Inhalte umgehend entfernt.</p>

      <h5>Haftung für Links</h5>
      <p>Diese Website kann Links zu externen Webseiten enthalten, auf deren Inhalte kein Einfluss besteht. Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber verantwortlich. Bei Bekanntwerden von Rechtsverletzungen werden entsprechende Links umgehend entfernt.</p>

      <h5>Urheberrecht</h5>
      <p>Die auf dieser Website erstellten Inhalte und Werke unterliegen dem deutschen Urheberrecht. Downloads und Kopien dieser Seite sind nur für den privaten, nicht kommerziellen Gebrauch gestattet. Soweit Inhalte nicht selbst erstellt wurden, werden die Urheberrechte Dritter beachtet. Hinweise auf mögliche Urheberrechtsverletzungen können per E-Mail gesendet werden.</p>

      <h5>Datenschutzerklärung</h5>
      <p>Weitere Informationen zum Schutz Ihrer Daten finden Sie in der <a href="/datenschutz.php">Datenschutzerklärung</a>.</p>
    </section>
  </main>
</body>
</html>
