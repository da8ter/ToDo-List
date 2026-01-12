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
  - Optional: Benachrichtigung vor Fälligkeit
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
- **Grid-Ansicht verwenden** (`UseGridView`)
  - Schaltet die Darstellung zwischen Liste und Grid um.
  - Im Grid werden Badges minimiert (nur Icons).
- **Anzahl groß anzeigen** (`ShowLargeQuantity`)
  - Zeigt die Anzahl in 3× größer Darstellung.
  - Im Grid erscheint sie zentriert unter dem Infotext.
- **Grid Einkaufslisten Modus** (`GridShoppingListMode`)
  - Nur im Grid relevant: Kacheln werden kompakter dargestellt.
  - Die Checkbox zum Erledigen wird ausgeblendet; ein Klick auf die Kachel markiert den Task als erledigt.
  - Info-Badges (außer Anzahl) werden ausgeblendet.
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
- **Items** (Listenelement im Konfigurationsformular)
  - Ermöglicht Bearbeitung der Tasks im Backend.
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

## 8. Benachrichtigungen

Pro Task kann über die Checkbox **"Benachrichtigung"** festgelegt werden, ob eine Push-Benachrichtigung verschickt werden soll.

Im Konfigurationsformular der Instanz:

- **"Visualisierungs Instanz"**
  ID einer Kachel-Visualisierung, an die die Push-Benachrichtigung gesendet wird.
- **"Benachrichtigung Vorlauf"**
  Zeit vor dem Fälligkeitstermin, zu der die Benachrichtigung gesendet wird.

Benachrichtigung:

- Titel: **"Task fällig"**
- Text: Task-Titel
- Type: **Info**
- TargetID: Instanz-ID der ToDoList

## 9. Changelog

### 1.0

- Initiale Version
