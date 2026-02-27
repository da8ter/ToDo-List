# ToDo List

Dieses Modul stellt eine ToDo-Liste für die Tile-Visualisierung bereit.

![ToDo List](https://github.com/da8ter/images/blob/main/todo.png)

## Inhalt

- **1. Funktionsumfang**
- **2. Voraussetzungen**
- **3. Installation**
- **4. Konfiguration in IP-Symcon**
- **5. Visualisierung (Tile/HTML-SDK)**
- **6. Statusvariablen**
- **7. PHP-Befehlsreferenz**
- **8. Benachrichtigungen**
- **9. CalDAV Synchronisation**
- **10. Google Tasks Synchronisation**
- **11. Microsoft To Do Synchronisation**
- **12. Changelog**

## 1. Funktionsumfang

- **Tasks**
  - Anlegen, Bearbeiten, Löschen
  - Erledigt-Status
  - Titel / Info / Anzahl / Priorität / Fälligkeit
  - Wiederkehrend basierend auf Fälligkeit (Individuell: Stunden/Tage/Wochen/Monate/Jahre, sowie 1/2/3 Wochen, monatlich, quartalsweise, jährlich)
  - Wiederkehrende Tasks werden automatisch vor Fälligkeit wieder auf offen gesetzt (pro Task konfigurierbar)
  - Wieder öffnen: zusätzlich "Sofort" (direkt nach dem Erledigen wieder öffnen)
  - Optional: Benachrichtigung vor Fälligkeit
- **IPSView / HTMLBox**
  - Read-only HTML-Ausgabe der Taskliste über die Statusvariable **TaskListHtml** (für `~HTMLBox`)
  - Sortierung folgt den Sortier-Einstellungen aus dem Frontend
- **Sortierung**
  - Datum, Fälligkeit, Priorität, Titel
  - Manuell (automatisch aktiv, wenn per Drag&Drop umsortiert wurde)
  - Drag&Drop für manuelle Reihenfolge (Frontend)
- **Detailansicht**
  - Öffnen per Klick auf Titel/Info

## 2. Voraussetzungen

- IP-Symcon ab Version **8.0**
- Nutzung in der **Kachel-Visualisierung** (Tile-Visualisierung)

## 3. Installation

1. Repository/Library installieren (Module Control)
2. Instanz anlegen: **ToDo List**

## 4. Konfiguration in IP-Symcon

### Instanz-Eigenschaften

- **Visualisierungsinstanz** (`VisualizationInstanceID`)
  - ID einer Kachel-Visualisierung, an die Push-Benachrichtigungen gesendet werden.
- **Benachrichtigungs-Vorlaufzeit** (`NotificationLeadTime`)
  - Standard-Vorlaufzeit in Sekunden (z. B. 600 = 10 Minuten).
- **Übersicht einblenden** (`ShowOverview`)
  - Blendet im Frontend die Statistik-Kacheln ein/aus.
- **Erstellen Button einblenden** (`ShowCreateButton`)
  - Blendet im Frontend den Button **„Neuer Task“** ein/aus.
- **Sortieren Button einblenden** (`ShowSorting`)
  - Blendet im Frontend die Sortier-Bedienelemente (Dropdown + Auf/Ab) ein/aus.
- **Info-Badges einblenden** (`ShowInfoBadges`)
  - Blendet in der Hauptansicht die Badges für Priorität, Fälligkeit und Benachrichtigung ein/aus.
- **Löschen Button einblenden** (`ShowDeleteButton`)
  - Blendet den Löschen-Button in der Hauptansicht ein/aus (im Edit-Dialog ist er immer verfügbar).
- **Editier Button einblenden** (`ShowEditButton`)
  - Blendet den Editier-Button in der Hauptansicht ein/aus.
- **Erledigte Tasks ausblenden** (`HideCompletedTasks`)
  - Blendet erledigte Tasks im Frontend aus.
- **Erledigte Tasks löschen** (`DeleteCompletedTasks`)
  - Löscht einen Task automatisch, sobald er als erledigt markiert wird.
- **HTMLBox CSS** (`HtmlBoxCss`)
  - Vollständiges CSS für die HTMLBox-Ausgabe (`TaskListHtml`).
- **Items** (Listenelement im Konfigurationsformular)
  - Ermöglicht Bearbeitung der Tasks im Backend.
  - **Wiederholen** wird im Bearbeiten-Dialog immer angezeigt. **Wieder öffnen** wird nur angezeigt, wenn **Wiederholen** nicht **Keine** ist.
  - Wenn **Wiederholen = Individuell**, werden **Einheit** (Stunden/Tage/Wochen/Monate/Jahre) und **Intervall** eingeblendet.
  - Drag&Drop zum Umsortieren ist aktiviert.
  - Die Übernahme ins Frontend erfolgt beim **„Übernehmen“** der Instanz.

## 5. Visualisierung (Tile/HTML-SDK)

Die Visualisierung wird über `module.html` bereitgestellt (HTML-SDK).

- Die Instanz kann direkt als Kachel eingebunden werden.
- Änderungen in den Instanz-Einstellungen werden per State-Update an die Visualisierung übertragen.

## 6. Statusvariablen

Folgende Statusvariablen werden von der Instanz angelegt:

- **OpenTasks**
  - Anzahl offener Tasks
- **OverdueTasks**
  - Anzahl überfälliger Tasks
- **DueTodayTasks**
  - Anzahl heute fälliger Tasks

- **TaskListHtml** (`~HTMLBox`)
  - Read-only HTML-Ausgabe der Taskliste für IPSView (HTMLBox).
  - Wird bei Änderungen der Tasks sowie bei Änderungen der Sortier-Einstellungen aktualisiert.

Optional kann das CSS der HTMLBox über die Instanz-Eigenschaften angepasst werden.

- **HTMLBox CSS**
  - Vollständiges CSS für die HTMLBox.

## 7. PHP-Befehlsreferenz

Die folgenden Funktionen stehen in der Instanz zur Verfügung:

- **`Export()`**
  - Exportiert die Taskliste als JSON-String.
- **`AddItem(array $Item)`**
  - Fügt einen Task hinzu und gibt die neue ID zurück.
- **`UpdateItem(array $Data)`**
  - Aktualisiert einen Task.
- **`ToggleDone(array $Data)`**
  - Setzt/ändert den Erledigt-Status.
- **`DeleteItem(array $Data)`**
  - Löscht einen Task.
- **`Reorder(array $Data)`**
  - Setzt die Reihenfolge anhand einer ID-Liste.
- **`ProcessNotifications()`**
  - Prüft fällige Benachrichtigungen und sendet diese (sofern konfiguriert).
- **`ProcessRecurrences()`**
  - Verarbeitet wiederkehrende Tasks (5-Tage-Regel und Terminfortschreibung).

## 8. Benachrichtigungen

Pro Task kann über die Checkbox **"Benachrichtigung"** festgelegt werden, ob eine Push-Benachrichtigung verschickt werden soll.

Im Konfigurationsformular der Instanz:

- **"Visualisierungs Instanz"**
  ID einer Kachel-Visualisierung, an die die Push-Benachrichtigung gesendet wird.
- **"Benachrichtigung Vorlauf"**
  Zeit vor dem Fälligkeitstermin, zu der die Benachrichtigung gesendet wird.

Benachrichtigung:

- Titel (Vorlaufzeit = 0): **"Task fällig"**
- Titel (Vorlaufzeit > 0): **"Task in {Vorlaufzeit} fällig"**
- Text: Task-Titel
- Type: **Info**
- TargetID: Instanz-ID der ToDoList

## 9. CalDAV Synchronisation

Das Modul unterstützt die bidirektionale Synchronisation mit CalDAV-Servern (OwnCloud, Nextcloud, etc.) über das VTODO-Format.

### Konfiguration

Im Konfigurationsformular unter **"CalDAV Synchronisation"**:

| Eigenschaft | Beschreibung |
|-------------|--------------|
| **Aktiviert** | CalDAV-Sync ein-/ausschalten |
| **Server URL** | Basis-URL des CalDAV-Servers (z.B. `https://cloud.example.com/remote.php/dav`) |
| **Benutzername** | CalDAV-Benutzername |
| **Passwort** | CalDAV-Passwort |
| **Kalender-Pfad** | Pfad zum Aufgaben-Kalender (z.B. `calendars/user/tasks/`) |
| **Sync-Intervall** | Automatische Synchronisation (Nur manuell / 5/15/30/60 Minuten) |
| **Bei Konflikt** | Konfliktauflösung: Server gewinnt / Lokal gewinnt / Neuester gewinnt |

Hinweise für **hosted/managed CalDAV**:

- **Server URL** kann je nach Anbieter unterschiedlich sein. Typische Beispiele:
  - Nextcloud/ownCloud: `https://cloud.example.com/remote.php/dav`
  - Subdirectory-Installationen: `https://cloud.example.com/nextcloud/remote.php/dav`
  - Universell (wenn vom Anbieter unterstützt): `https://cloud.example.com/.well-known/caldav`
- **Kalender-Pfad** kann relativ oder absolut sein:
  - Relativ (wird an die Server URL angehängt): `calendars/user/tasks/`
  - Absolut (vom Host-Root): `/remote.php/dav/calendars/user/tasks/`

### Funktionsweise

- **Bidirektionale Sync**: Lokale Änderungen werden hochgeladen, Server-Änderungen heruntergeladen
- **ETag-basiert**: Änderungserkennung über ETags für effiziente Delta-Syncs
- **Konfliktauflösung**: Konfigurierbar (Server/Lokal/Neuester gewinnt)
- **Auto-Sync nach Änderungen**: Bei aktivem CalDAV wird nach lokalen Änderungen automatisch eine Synchronisation (kurz verzögert) angestoßen
- **Löschen wird synchronisiert**: Lokal gelöschte Tasks werden bei aktivem CalDAV auch auf dem Server gelöscht
- **Feld-Mapping**:
  - `title` ↔ `SUMMARY`
  - `info` ↔ `DESCRIPTION`
  - `done` ↔ `STATUS` (COMPLETED/NEEDS-ACTION)
  - `due` ↔ `DUE`
  - `priority` ↔ `PRIORITY` (1=hoch, 5=normal, 9=niedrig)

### PHP-Befehle

- **`CalDAVTestConnection()`** – Testet die Verbindung zum Server
- **`CalDAVDiscoverCalendars()`** – Listet verfügbare Kalender auf (nützlich für iCloud)
- **`CalDAVSync()`** – Führt eine manuelle Synchronisation durch

### Beispiel: Nextcloud

```
Server URL:     https://nextcloud.example.com/remote.php/dav
Benutzername:   meinuser
Passwort:       meinpasswort
Kalender-Pfad:  calendars/meinuser/tasks/
```

### Beispiel: iCloud

1. App-spezifisches Passwort unter [appleid.apple.com](https://appleid.apple.com) generieren
2. Konfiguration:
   ```
   Server URL:     https://caldav.icloud.com
   Benutzername:   deine@apple-id.com
   Passwort:       xxxx-xxxx-xxxx-xxxx (App-spezifisches Passwort)
   ```
3. Button **"Kalender suchen"** klicken, um verfügbare Kalender anzuzeigen
4. Den angezeigten Pfad eines VTODO-fähigen Kalenders in **Kalender-Pfad** eintragen

## 10. Google Tasks Synchronisation

Das Modul unterstützt die bidirektionale Synchronisation mit Google Tasks über die Google Tasks API.

### Voraussetzungen

1. **Google Cloud Projekt erstellen**:
   - [Google Cloud Console](https://console.cloud.google.com/) öffnen
   - Neues Projekt erstellen oder bestehendes auswählen
   - **Google Tasks API** aktivieren (APIs & Services → Bibliothek → "Tasks API" suchen → Aktivieren)

2. **OAuth 2.0 Zugangsdaten erstellen**:
   - APIs & Services → Anmeldedaten → Anmeldedaten erstellen → OAuth-Client-ID
   - Anwendungstyp: **Webanwendung**
   - Autorisierte Weiterleitungs-URIs hinzufügen: `https://<dein-symcon-connect>/hook/todolist_google/<InstanzID>`
   - **Client-ID** und **Client-Secret** notieren

3. **IP-Symcon Connect** muss konfiguriert sein (für OAuth-Callback)

### Konfiguration

Im Konfigurationsformular unter **"Google Tasks Synchronisation"**:

| Eigenschaft | Beschreibung |
|-------------|--------------|
| **Aktiviert** | Google Tasks Sync ein-/ausschalten |
| **Client ID** | OAuth 2.0 Client-ID aus der Google Cloud Console |
| **Client Secret** | OAuth 2.0 Client-Secret aus der Google Cloud Console |
| **Task List ID** | ID der Google Task-Liste (über "Aufgabenlisten suchen" ermitteln) |
| **Sync-Intervall** | Automatische Synchronisation (Nur manuell / 5/15/30/60 Minuten) |
| **Bei Konflikt** | Konfliktauflösung: Server gewinnt / Lokal gewinnt / Neuester gewinnt |

### Einrichtung

1. **Client ID** und **Client Secret** eintragen
2. Instanz übernehmen (Änderungen speichern)
3. Button **"Mit Google verbinden"** klicken
4. Die angezeigte URL im Browser öffnen und Google-Konto autorisieren
5. Button **"Aufgabenlisten suchen"** klicken
6. Gewünschte **Task List ID** kopieren und eintragen
7. **Aktiviert** anhaken und Instanz übernehmen

### Funktionsweise

- **Bidirektionale Sync**: Lokale Änderungen werden hochgeladen, Google-Änderungen heruntergeladen
- **OAuth 2.0**: Sichere Authentifizierung mit automatischer Token-Erneuerung
- **Konfliktauflösung**: Konfigurierbar (Server/Lokal/Neuester gewinnt)
- **Feld-Mapping**:
  - `title` ↔ `title`
  - `info` ↔ `notes`
  - `done` ↔ `status` (completed/needsAction)
  - `due` ↔ `due` (nur Datum, keine Uhrzeit!)

**Einschränkungen**:
- Google Tasks unterstützt **keine Priorität** – wird nur lokal gespeichert
- Google Tasks speichert bei `due` **nur das Datum**, keine Uhrzeit
- Google Tasks unterstützt **keine Wiederholungen** – werden nur lokal verwaltet

### PHP-Befehle

- **`GoogleGetAuthUrl()`** – Gibt die Autorisierungs-URL zurück
- **`GoogleTestConnection()`** – Testet die Verbindung zu Google
- **`GoogleDiscoverTasklists()`** – Listet verfügbare Aufgabenlisten auf
- **`GoogleTasksSync()`** – Führt eine manuelle Synchronisation durch
- **`GoogleDisconnect()`** – Trennt die Verbindung zu Google
- **`GoogleResetSync()`** – Setzt alle Google-Sync-Marker zurück

## 11. Microsoft To Do Synchronisation

Das Modul unterstützt die bidirektionale Synchronisation mit **Microsoft To Do** über die **Microsoft Graph API**.

### Voraussetzungen

1. **App-Registrierung in Microsoft Entra ID (Azure Portal)**:
   - App registrieren (Anwendungstyp: Web)
   - Berechtigungen (Delegated): **Tasks.ReadWrite**
   - Redirect URI hinzufügen: `https://<dein-symcon-connect>/hook/todolist_microsoft/<InstanzID>`
   - **Client ID** und **Client Secret** notieren

2. **IP-Symcon Connect** muss konfiguriert sein (für OAuth-Callback)

### Konfiguration

Im Konfigurationsformular unter **"Microsoft To Do Synchronisation"**:

| Eigenschaft | Beschreibung |
|-------------|--------------|
| **Client ID** | OAuth Client-ID aus Entra ID |
| **Client Secret** | OAuth Client-Secret aus Entra ID |
| **Tenant** | `common` (Standard), `organizations` oder Tenant-ID |
| **List ID** | ID der Microsoft To Do Liste (über "Listen suchen" ermitteln) |
| **Sync-Intervall** | Automatische Synchronisation (Nur manuell / 5/15/30/60 Minuten) |
| **Bei Konflikt** | Konfliktauflösung: Server gewinnt / Lokal gewinnt / Neuester gewinnt |

### Einrichtung

1. **Client ID**, **Client Secret** und optional **Tenant** eintragen
2. Instanz übernehmen (Änderungen speichern)
3. Button **"Mit Microsoft verbinden"** klicken
4. Die angezeigte URL im Browser öffnen und Microsoft Konto autorisieren
5. Button **"Listen suchen"** klicken
6. Gewünschte **List ID** kopieren und eintragen

### Funktionsweise

- **Bidirektionale Sync**: Lokale Änderungen werden hochgeladen, Microsoft-Änderungen heruntergeladen
- **OAuth 2.0**: Sichere Authentifizierung mit automatischer Token-Erneuerung
- **Konfliktauflösung**: Konfigurierbar (Server/Lokal/Neuester gewinnt)

**Einschränkungen**:
- Uhrzeit bei Fälligkeit und Wiederholungen werden nicht vollständig abgebildet und sind daher in Symcon ggf. nur lokal nutzbar.

### PHP-Befehle

- **`MicrosoftGetAuthUrl()`** – Gibt die Autorisierungs-URL zurück
- **`MicrosoftTestConnection()`** – Testet die Verbindung zu Microsoft
- **`MicrosoftDiscoverLists()`** – Listet verfügbare Listen auf
- **`MicrosoftToDoSync()`** – Führt eine manuelle Synchronisation durch
- **`MicrosoftDisconnect()`** – Trennt die Verbindung zu Microsoft
- **`MicrosoftResetSync()`** – Setzt alle Microsoft-Sync-Marker zurück

## 12. Architektur

Das Modul nutzt PHP-Traits zur Modularisierung der Sync-Backends:

```
ToDoList/
├── module.php                          # Hauptklasse (~2500 Zeilen)
├── module.html                         # Tile-Visualisierung (HTML-SDK)
├── module.json                         # Modul-Metadaten
├── form.json                           # Basis-Formular
├── locale.json                         # Übersetzungen (DE/EN)
├── assets/
│   └── default.css                     # Standard-CSS für HTMLBox
└── traits/
    ├── OAuthHelper.php                 # Gemeinsame OAuth Encrypt/Decrypt/HTTP
    ├── SyncHelper.php                  # Gemeinsame Sync-Logik (Reset, PendingDelete, Status, Timer, Options)
    ├── CalDAVSync.php                  # CalDAV-Synchronisation
    ├── GoogleTasksSync.php             # Google Tasks-Synchronisation
    └── MicrosoftToDoSync.php           # Microsoft To Do-Synchronisation
```

- **OAuthHelper** – Token-Verschlüsselung, HTTP-Requests, OAuth-Token-Exchange/Refresh, WebHook-Registrierung
- **SyncHelper** – Gemeinsame Methoden für Reset, PendingDeletes, Status-Labels, Timer-Updates, Form-Options (Sync-Intervall, Konfliktmodus, Benachrichtigungs-Vorlaufzeit)
- **CalDAVSync** – CalDAV-spezifisch: VTODO-Parsing, XML/PROPFIND, iCal-Handling
- **GoogleTasksSync** – Google Tasks API, OAuth 2.0 mit Google
- **MicrosoftToDoSync** – Microsoft Graph API, OAuth 2.0 mit Microsoft, Recurrence-Mapping

## 13. Changelog

### 1.2

- Google Tasks Synchronisation (OAuth 2.0)

### 1.1

- CalDAV-Synchronisation (OwnCloud/Nextcloud)

### 1.0

- Initiale Version
