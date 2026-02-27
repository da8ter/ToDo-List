# Google Tasks Synchronisation – Einrichtungsanleitung

## Voraussetzungen

- Ein Google-Konto
- Zugang zur [Google Cloud Console](https://console.cloud.google.com/)
- **IP-Symcon Connect** muss konfiguriert und erreichbar sein (für OAuth-Callback)

## Schritt 1: Google Cloud Projekt erstellen

1. Öffne die [Google Cloud Console](https://console.cloud.google.com/)
2. Oben links auf das Projekt-Dropdown klicken → **Neues Projekt**
3. Name z.B. `Symcon ToDoList` → **Erstellen**
4. Sicherstellen, dass das neue Projekt ausgewählt ist

## Schritt 2: Google Tasks API aktivieren

1. Navigiere zu **APIs & Dienste** → **Bibliothek**
2. Suche nach **Tasks API**
3. Klicke auf **Google Tasks API** → **Aktivieren**

## Schritt 3: OAuth-Zustimmungsbildschirm einrichten

1. Navigiere links im Menü zu ***OAuth-Zustimmungsbildschirm**
2. Wähle **Erste Schritte**
3. Fülle aus:
   - **Aanwendungsname**: z.B. `Symcon ToDoList`
   - **E-Mail**: Deine E-Mail-Adresse
   - Rest kann leer bleiben
4. **Weiter**
5. **Zielgruppe** Extern
5. **weiter**
6. **Kontaktdaten** Emailadresse eintragen
7. **weiter**
8. **Erstellen**
9. **Links im Menü auf Datenzugriff** 
10.  **Bereiche hinzufügen oder entfernen**
   - Suche nach `auth/tasks` und aktiviere `https://www.googleapis.com/auth/tasks`
   - **Aktualisieren** → **Save**
6. Bei **Zielgruppe** → **Testnutzer**: Deine Google-E-Mail-Adresse hinzufügen → **Speichern**

> Solange die App im Status **„Testen"** ist, können nur die eingetragenen Testnutzer autorisieren. Das ist für den Privatgebrauch völlig ausreichend.

## Schritt 4: OAuth-Client-ID erstellen

1. Navigiere zu **APIs & Dienste** → **Anmeldedaten**
2. Klicke **Anmeldedaten erstellen** → **OAuth-Client-ID**
3. **Anwendungstyp**: Webanwendung
4. **Name**: z.B. `Symcon`
5. Unter **Autorisierte Weiterleitungs-URIs** klicke **URI hinzufügen**:
   ```
   https://<dein-symcon-connect>/hook/todolist_google/<InstanzID> (die komplette Adresse findest du im Konfigurationsformular unter **Redirect URI** im Bereich Google Tasks Synchronisation)
   ```
6. **Erstellen** klicken
7. **Client-ID** und **Client-Secret** kopieren! (Wichtig! Kann nach dem schließen des Fensters nicht mehr angezeigt werden)

## Schritt 5: In IP-Symcon konfigurieren

1. Öffne die **ToDoList-Instanz**
2. Wähle bei **Synchronization backend** → **Google Tasks**
3. Trage ein:
   - **Client ID**: Die kopierte Client-ID aus Schritt 4
   - **Client Secret**: Das kopierte Client-Secret aus Schritt 4
4. Klicke auf **Übernehmen** (wichtig – erst danach werden die Buttons aktiv!)

## Schritt 6: Mit Google verbinden

1. Klicke auf **„Mit Google verbinden"**
2. Eine URL wird geöffnet
3. Google-Konto auswählen und anmelden
4. Berechtigungen bestätigen → **Zulassen**
5. Du siehst **„Authorization successful"** → Fenster schließen

## Schritt 7: Aufgabenliste auswählen

1. Zurück in IP-Symcon: Klicke auf **„Aufgabenlisten suchen"**
2. Die verfügbaren Listen werden danach im Dropdown "Aufgabenlisten" angezeigt.

## Schritt 8: Synchronisation aktivieren

1. Wähle ein **Sync-Intervall** (z.B. 5 oder 15 Minuten)
2. Wähle den **Konfliktmodus** (Standard: „Neuester gewinnt")
3. Optional: **Automatisch nach Änderungen synchronisieren** aktivieren
4. Klicke auf **Übernehmen**

## Synchronisierte Felder

| Lokales Feld | Google Tasks | Hinweis |
|---|---|---|
| Titel | `title` | ✅ bidirektional |
| Info | `notes` | ✅ bidirektional |
| Erledigt | `status` | ✅ completed / needsAction |
| Fälligkeit | `due` | ⚠️ **Nur Datum**, keine Uhrzeit! |

**Nicht synchronisiert bzw. nicht durch die API unterstützt:** Priorität, Wiederholungen, Benachrichtigungen, Menge – diese werden nur lokal verwaltet.

> **Wichtig:** Google Tasks speichert Fälligkeiten nur als Datum (immer `T00:00:00Z`). Wenn du eine Uhrzeit in Symcon setzt, bleibt diese lokal erhalten, wird aber nicht an Google übertragen.

## PHP-Befehle

```php
TDL_GoogleGetAuthUrl($id);           // Autorisierungs-URL anzeigen
TDL_GoogleTestConnection($id);       // Verbindung testen
TDL_GoogleDiscoverTasklists($id);    // Aufgabenlisten suchen
TDL_GoogleTasksSync($id);            // Manuell synchronisieren
TDL_GoogleResetSync($id);            // Sync-Marker zurücksetzen
TDL_GoogleDisconnect($id);           // Verbindung trennen
```