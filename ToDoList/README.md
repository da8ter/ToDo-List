# ToDo List

Dieses Modul stellt eine ToDo-Liste für die Tile-Visualisierung bereit.

## Funktionen

- Tasks anlegen, bearbeiten, löschen
- Fälligkeit, Priorität, Anzahl
- Drag&Drop Sortierung
- Detailansicht per Klick auf Titel/Info

## Benachrichtigungen

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
