<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Datenschutz | <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="/config/legal.css" />
</head>
<body>
  <main class="legal-shell">
    <a class="back-link" href="/index.php">Zurück zur Startseite</a>
    <section class="legal-card">
      <h1>Datenschutzerklärung</h1>
      <h3>Muster-Datenschutzhinweise für eine private Projektseite</h3>

      <p>Diese Datei ist ein Beispiel. Ersetzen Sie die Platzhalter vor einer Veröffentlichung durch eigene Angaben und prüfen Sie, ob der Text zur konkreten Website passt.</p>

      <h4>1. Verantwortliche Person</h4>
      <p>Diese Datenschutzerklärung gilt für die Datenverarbeitung durch:</p>
      <p>
        Vorname Nachname<br />
        Straße Hausnummer<br />
        PLZ Ort<br />
        Deutschland
      </p>
      <p>E-Mail: <a href="mailto:mail@example.test">mail@example.test</a></p>

      <h4>2. Private Nutzung und Zweck der Website</h4>
      <p>Diese Website ist ein privates, nicht gewerbliches Projekt. Sie dient der Bereitstellung einer persönlichen Startseite mit gespeicherten Links, Profilen und Einstellungen.</p>

      <h4>3. Zugriff auf die Website</h4>
      <p>Beim Aufrufen dieser Website werden durch den Webserver keine dauerhaften Server-Logfiles gespeichert.</p>
      <p>Eine Verarbeitung personenbezogener Daten erfolgt beim reinen Besuch der Website grundsätzlich nicht.</p>

      <h4>4. Benutzerkonten und Startseiten-Funktion</h4>
      <p>Wenn ein Benutzerkonto angelegt oder die Startseiten-Funktion genutzt wird, werden die dafür erforderlichen Daten verarbeitet. Dazu gehören insbesondere Login-Daten, gespeicherte Profile, Gruppen, Links, Icon-Auswahlen und technische Einstellungen der Startseite.</p>
      <p>Diese Daten werden ausschließlich zur Bereitstellung der persönlichen Startseite, zur Anmeldung und zur Verwaltung der gespeicherten Inhalte verwendet.</p>
      <p>Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO. Das berechtigte Interesse liegt im sicheren und funktionsfähigen Betrieb dieser privaten Website.</p>

      <h4>5. Kontaktaufnahme</h4>
      <p>Wenn Sie per E-Mail Kontakt aufnehmen, werden die dabei übermittelten Daten ausschließlich zur Bearbeitung der Anfrage verwendet.</p>
      <p>Rechtsgrundlage ist Art. 6 Abs. 1 lit. a DSGVO oder Art. 6 Abs. 1 lit. f DSGVO.</p>

      <h4>6. Weitergabe von Daten</h4>
      <p>Eine Übermittlung personenbezogener Daten an Dritte erfolgt nicht, außer wenn:</p>
      <ul>
        <li>Sie ausdrücklich eingewilligt haben,</li>
        <li>eine gesetzliche Verpflichtung besteht,</li>
        <li>die Weitergabe zur Geltendmachung, Ausübung oder Verteidigung von Rechtsansprüchen erforderlich ist.</li>
      </ul>

      <h4>7. Cookies</h4>
      <p>Auf dieser Website werden ausschließlich technisch notwendige Cookies verwendet.</p>
      <p>Diese Cookies dienen der Funktionsfähigkeit der Website, insbesondere dem Login-Vorgang und der Sitzungsverwaltung.</p>
      <ul>
        <li><strong>PHP-Session-Cookie</strong> - Sitzungsverwaltung während der Nutzung der Website.</li>
      </ul>
      <p>Das Session-Cookie wird automatisch gelöscht, sobald die Sitzung beendet wird.</p>
      <p>Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO.</p>

      <h4>8. Analyse-Tools und Social Media</h4>
      <p>Auf dieser Website werden keine Analyse- oder Tracking-Tools eingesetzt. Es werden keine Social-Media-Plugins verwendet.</p>

      <h4>9. Externe Links</h4>
      <p>Die Website kann Links zu externen Webseiten enthalten. Für die Inhalte und Datenschutzbestimmungen der jeweiligen Anbieter sind ausschließlich deren Betreiber verantwortlich.</p>

      <h4>10. Betroffenenrechte</h4>
      <p>Sie haben das Recht:</p>
      <ul>
        <li>gemäß Art. 15 DSGVO Auskunft über Ihre gespeicherten personenbezogenen Daten zu verlangen,</li>
        <li>gemäß Art. 16 DSGVO Berichtigung unrichtiger Daten zu verlangen,</li>
        <li>gemäß Art. 17 DSGVO Löschung Ihrer Daten zu verlangen,</li>
        <li>gemäß Art. 18 DSGVO Einschränkung der Verarbeitung zu verlangen,</li>
        <li>gemäß Art. 20 DSGVO Datenübertragbarkeit zu verlangen,</li>
        <li>gemäß Art. 7 Abs. 3 DSGVO eine erteilte Einwilligung jederzeit zu widerrufen,</li>
        <li>gemäß Art. 77 DSGVO Beschwerde bei einer Datenschutzaufsichtsbehörde einzulegen.</li>
      </ul>

      <h4>11. Widerspruchsrecht</h4>
      <p>Sofern personenbezogene Daten auf Grundlage von berechtigten Interessen gemäß Art. 6 Abs. 1 lit. f DSGVO verarbeitet werden, besteht gemäß Art. 21 DSGVO das Recht, Widerspruch gegen die Verarbeitung einzulegen.</p>
      <p>Dafür genügt eine E-Mail an: <a href="mailto:mail@example.test">mail@example.test</a></p>
    </section>
  </main>
</body>
</html>
