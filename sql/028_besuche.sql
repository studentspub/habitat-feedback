-- Eigener Besuchszähler ohne Cookies (habitat-feedback-betrieb#8): nur Summen je Tag, Seite und Herkunft.
-- Keine IP-Adresse, kein Cookie, kein Hash, keine Einzelzeilen. tag = deutsche Zeit (in PHP berechnet).
-- herkunft: google, bing, duckduckgo, ecosia, intern, direkt, sonst der Hostname der verweisenden Seite;
-- Bots als 'bot:<name>' (werden nicht als Besuch gezählt, aber getrennt mitgeschrieben).
CREATE TABLE IF NOT EXISTS besuche_tag (
  tag        DATE NOT NULL,
  pfad       VARCHAR(64) NOT NULL,
  herkunft   VARCHAR(64) NOT NULL,
  aufrufe    INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (tag, pfad, herkunft)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
