# CalDAV Synchronisation – Einrichtungsanleitung

## Voraussetzungen

- Ein CalDAV-kompatibler Server (z.B. Nextcloud, ownCloud, Synology)
- Zugangsdaten (Benutzername + Passwort)
- Der Pfad zum Aufgaben-Kalender (VTODO-fähig)



## Schritt 1: In IP-Symcon konfigurieren

1. Öffne die **ToDoList-Instanz**
2. Wähle bei **Synchronization backend** → **CalDAV**
3. Trage **Server URL**, **Benutzername** und **Passwort** ein
4. Klicke auf **Übernehmen**

## Schritt 2: Verbindung testen

Klicke auf **„Verbindung testen"**:

- ✅ **„Connection successful"** → weiter mit Schritt 4
- ❌ **„Authentication failed"** → Benutzername/Passwort prüfen
- ❌ **„Connection failed"** → Server-URL prüfen

## Schritt 3: Kalender-Pfad ermitteln

### Option A: Automatisch suchen

1. Klicke auf **„Kalender suchen"**
2. Es werden alle verfügbaren Kalender aufgelistet, z.B.:
   ```
   Tasks (VTODO) – calendars/user/tasks/
   Persönlich (VEVENT) – calendars/user/personal/
   ```
3. Kopiere den Pfad eines **VTODO**-fähigen Kalenders
4. Trage ihn in **Kalender-Pfad** ein

### Option B: Manuell eintragen

Der Kalender-Pfad kann relativ oder absolut sein:

- **Relativ** (wird an die Server-URL angehängt): `calendars/user/tasks/`
- **Absolut** (vom Host-Root): `/remote.php/dav/calendars/user/tasks/`

## Schritt 4: Sync aktivieren

1. Wähle ein **Sync-Intervall** (z.B. 5 oder 15 Minuten)
2. Wähle den **Konfliktmodus**:
   - **Server gewinnt** – Server-Daten überschreiben lokale Änderungen
   - **Lokal gewinnt** – Lokale Daten überschreiben Server-Änderungen
   - **Neuester gewinnt** – Die zuletzt geänderte Version wird übernommen
3. Optional: **Auto sync after changes** aktivieren
4. Klicke auf **Übernehmen**

## Synchronisierte Felder

| Lokales Feld | CalDAV (VTODO) | Hinweis |
|---|---|---|
| Titel | `SUMMARY` | ✅ bidirektional |
| Info | `DESCRIPTION` | ✅ bidirektional |
| Erledigt | `STATUS` | ✅ COMPLETED / NEEDS-ACTION |
| Fälligkeit | `DUE` | ✅ Datum + Uhrzeit (UTC) |
| Priorität | `PRIORITY` | ✅ 1–4 = Hoch, 5 = Normal, 6–9 = Niedrig |

**Nicht synchronisiert:** Wiederholungen, Benachrichtigungen, Menge – diese werden nur lokal verwaltet.

## PHP-Befehle

```php
TDL_CalDAVTestConnection($id);      // Verbindung testen
TDL_CalDAVDiscoverCalendars($id);   // Kalender suchen
TDL_CalDAVSync($id);                // Manuell synchronisieren
TDL_CalDAVResetSync($id);           // Sync-Marker zurücksetzen
```