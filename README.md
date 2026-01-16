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
- **9. Changelog**

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

## 9. Changelog

### 1.0

- Initiale Version
