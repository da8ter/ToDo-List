<?php

declare(strict_types=1);

trait CalDAVSync
{
    private function UpdateCalDAVTimer(): void
    {
        $enabled = $this->GetSyncBackend() === 'caldav';
        $interval = $this->ReadPropertyInteger('CalDAVSyncInterval');
        if ($enabled && $interval > 0) {
            $this->SetTimerInterval('CalDAVSyncTimer', $interval * 60 * 1000);
        } else {
            $this->SetTimerInterval('CalDAVSyncTimer', 0);
        }

        if (!$enabled) {
            $this->SetTimerInterval('CalDAVOnChangeTimer', 0);
        }
    }

    private function GetCalDAVStatusLabel(): string
    {
        $lastSync = $this->ReadAttributeInteger('CalDAVLastSync');
        if ($lastSync <= 0) {
            return $this->Translate('Last sync') . ': ' . $this->Translate('Never');
        }
        return $this->Translate('Last sync') . ': ' . date('d.m.Y H:i:s', $lastSync);
    }

    public function CalDAVTestConnection(): bool
    {
        $url = trim($this->ReadPropertyString('CalDAVServerURL'));
        $user = trim($this->ReadPropertyString('CalDAVUsername'));
        $pass = trim($this->ReadPropertyString('CalDAVPassword'));

        if ($url === '' || $user === '' || $pass === '') {
            echo $this->Translate('Please fill in server URL, username and password.');
            return false;
        }

        $testUrl = rtrim($url, '/') . '/';
        $res = $this->CalDAVRequest(
            'PROPFIND',
            $testUrl,
            $user,
            $pass,
            [
                'Depth: 0',
                'Content-Type: application/xml; charset=utf-8'
            ],
            '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:current-user-principal/></d:prop></d:propfind>',
            10
        );

        if (($res['status'] ?? 0) === 0) {
            echo $this->Translate('Connection failed') . ': ' . ($this->GetLastHttpError() ?: 'Unknown error');
            return false;
        }

        $statusCode = (int)($res['status'] ?? 0);
        if ($statusCode === 207 || $statusCode === 200) {
            echo $this->Translate('Connection successful');
            return true;
        }

        if ($statusCode === 401) {
            echo $this->Translate('Authentication failed');
            return false;
        }

        echo $this->Translate('Connection failed') . ' (HTTP ' . $statusCode . ')';
        return false;
    }

    public function CalDAVResetSync(): void
    {
        $this->SyncResetItems(
            ['caldavUid'],
            ['caldavEtag', 'caldavHref'],
            ['caldavSynced'],
            'CalDAVLastSync',
            'CalDAVPendingDeletes'
        );
    }

    public function CalDAVSync(): bool
    {
        $sem = 'TDL_CalDAVSync_' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($sem, 0)) {
            $this->SendDebug('CalDAV', 'Sync skipped - already running', 0);
            return false;
        }

        try {
            return $this->CalDAVSyncInternal();
        } finally {
            IPS_SemaphoreLeave($sem);
        }
    }

    public function CalDAVSyncOnChange(): void
    {
        $this->SetTimerInterval('CalDAVOnChangeTimer', 0);

        if ($this->GetSyncBackend() !== 'caldav') {
            return;
        }
        $sem = 'TDL_CalDAVSync_' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($sem, 0)) {
            $this->SetTimerInterval('CalDAVOnChangeTimer', 3000);
            return;
        }

        try {
            $this->CalDAVSyncInternal();
        } finally {
            IPS_SemaphoreLeave($sem);
        }
    }

    private function CalDAVSyncInternal(): bool
    {
        if ($this->GetSyncBackend() !== 'caldav') {
            $this->SendDebug('CalDAV', 'Sync skipped - not enabled', 0);
            return false;
        }

        $url = trim($this->ReadPropertyString('CalDAVServerURL'));
        $user = trim($this->ReadPropertyString('CalDAVUsername'));
        $pass = trim($this->ReadPropertyString('CalDAVPassword'));
        $calendarPath = trim($this->ReadPropertyString('CalDAVCalendarPath'));

        if ($url === '' || $user === '' || $pass === '' || $calendarPath === '') {
            $this->SendDebug('CalDAV', 'Sync skipped - missing configuration', 0);
            return false;
        }

        $this->SendDebug('CalDAV', 'Starting sync...', 0);

        try {
            $calendarUrl = $this->CalDAVResolveUrl($url, $calendarPath);
            $serverItems = $this->CalDAVFetchItems($calendarUrl, $user, $pass);

            if ($serverItems === null) {
                $this->SendDebug('CalDAV', 'Failed to fetch items from server', 0);
                return false;
            }

            $pendingDeletes = json_decode((string)$this->ReadAttributeString('CalDAVPendingDeletes'), true);
            if (!is_array($pendingDeletes)) {
                $pendingDeletes = [];
            }
            if (count($pendingDeletes) > 0) {
                $pendingUids = array_keys($pendingDeletes);
                $serverItems = array_values(array_filter($serverItems, function (array $si) use ($pendingUids): bool {
                    return !in_array((string)($si['caldavUid'] ?? ''), $pendingUids, true);
                }));

                foreach ($pendingDeletes as $uid => $href) {
                    $uid = (string)$uid;
                    if ($uid === '') {
                        unset($pendingDeletes[$uid]);
                        continue;
                    }
                    $ok = $this->CalDAVDeleteItem($calendarUrl, $user, $pass, $uid, (string)$href);
                    if ($ok) {
                        unset($pendingDeletes[$uid]);
                        $this->SendDebug('CalDAV', 'Deleted on server: ' . $uid, 0);
                    } else {
                        $this->SendDebug('CalDAV', 'Server delete failed (will retry): ' . $uid, 0);
                    }
                }
                $this->WriteAttributeString('CalDAVPendingDeletes', json_encode($pendingDeletes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }

            $localItems = $this->LoadItems();
            $conflictMode = $this->ReadPropertyString('CalDAVConflictMode');

            $result = $this->CalDAVMergeItems($localItems, $serverItems, $conflictMode);

            $items = $result['items'];
            $now = time();

            foreach ($result['toUpload'] as $uploadItem) {
                $success = $this->CalDAVUploadItem($calendarUrl, $user, $pass, $uploadItem);
                if ($success) {
                    $uid = $uploadItem['caldavUid'] ?? '';
                    for ($i = 0; $i < count($items); $i++) {
                        if (($items[$i]['caldavUid'] ?? '') === $uid) {
                            $items[$i]['caldavSynced'] = $now;
                            $items[$i]['localModified'] = 0;
                            break;
                        }
                    }
                    $this->SendDebug('CalDAV', 'Uploaded: ' . ($uploadItem['title'] ?? $uid), 0);
                } else {
                    $this->SendDebug('CalDAV', 'Upload failed: ' . ($uploadItem['title'] ?? ''), 0);
                }
            }

            $this->SaveItems($items);
            $this->WriteAttributeInteger('CalDAVLastSync', $now);

            $this->SyncPostComplete();

            $this->SendDebug('CalDAV', 'Sync completed', 0);
            echo $this->Translate('Synchronization completed');
            return true;
        } catch (Exception $e) {
            $this->SendDebug('CalDAV', 'Sync failed: ' . $e->getMessage(), 0);
            return false;
        }
    }

    private function ScheduleCalDAVSyncOnChange(): void
    {
        if ($this->GetSyncBackend() !== 'caldav') {
            return;
        }

        $url = trim($this->ReadPropertyString('CalDAVServerURL'));
        $user = trim($this->ReadPropertyString('CalDAVUsername'));
        $pass = trim($this->ReadPropertyString('CalDAVPassword'));
        $calendarPath = trim($this->ReadPropertyString('CalDAVCalendarPath'));
        if ($url === '' || $user === '' || $pass === '' || $calendarPath === '') {
            return;
        }

        $this->SetTimerInterval('CalDAVOnChangeTimer', 3000);
    }

    private function CalDAVFetchItems(string $CalendarUrl, string $User, string $Pass): ?array
    {
        $res = $this->CalDAVRequest(
            'REPORT',
            $CalendarUrl,
            $User,
            $Pass,
            [
                'Depth: 1',
                'Content-Type: application/xml; charset=utf-8'
            ],
            '<?xml version="1.0" encoding="utf-8"?>' .
                '<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">' .
                '<d:prop><d:getetag/><c:calendar-data/></d:prop>' .
                '<c:filter><c:comp-filter name="VCALENDAR"><c:comp-filter name="VTODO"/></c:comp-filter></c:filter>' .
                '</c:calendar-query>',
            30
        );

        if (($res['status'] ?? 0) === 0) {
            return null;
        }

        $statusCode = (int)($res['status'] ?? 0);
        if ($statusCode !== 207) {
            return null;
        }

        return $this->CalDAVParseMultiStatus((string)($res['body'] ?? ''));
    }

    private function CalDAVParseMultiStatus(string $Xml): array
    {
        $items = [];
        
        $xml = @simplexml_load_string($Xml);
        if ($xml === false) {
            return $items;
        }

        $xml->registerXPathNamespace('d', 'DAV:');
        $xml->registerXPathNamespace('c', 'urn:ietf:params:xml:ns:caldav');

        $responses = $xml->xpath('//d:response');
        foreach ($responses as $response) {
            $response->registerXPathNamespace('d', 'DAV:');
            $response->registerXPathNamespace('c', 'urn:ietf:params:xml:ns:caldav');
            
            $hrefNodes = $response->xpath('d:href');
            $href = !empty($hrefNodes) ? (string)$hrefNodes[0] : '';
            
            $etagNodes = $response->xpath('d:propstat/d:prop/d:getetag');
            $etag = !empty($etagNodes) ? (string)$etagNodes[0] : '';
            
            $calDataNodes = $response->xpath('d:propstat/d:prop/c:calendar-data');
            $calData = !empty($calDataNodes) ? (string)$calDataNodes[0] : '';

            if ($calData !== '') {
                $vtodo = $this->CalDAVParseVTodo($calData);
                if ($vtodo !== null) {
                    $vtodo['caldavHref'] = $href;
                    $vtodo['caldavEtag'] = trim($etag, '"');
                    $items[] = $vtodo;
                }
            }
        }

        return $items;
    }

    private function CalDAVParseVTodo(string $ICalData): ?array
    {
        $rawLines = preg_split('/\r?\n/', $ICalData);
        if ($rawLines === false) {
            return null;
        }

        $lines = [];
        foreach ($rawLines as $line) {
            if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t") && count($lines) > 0) {
                $lines[count($lines) - 1] .= substr($line, 1);
            } else {
                $lines[] = $line;
            }
        }

        $inVTodo = false;
        $props = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === 'BEGIN:VTODO') {
                $inVTodo = true;
                continue;
            }
            if ($line === 'END:VTODO') {
                break;
            }
            if (!$inVTodo) {
                continue;
            }

            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $key = strtoupper(explode(';', $key)[0]);
                $props[$key] = $value;
            }
        }

        if (empty($props['UID'])) {
            return null;
        }

        $done = ($props['STATUS'] ?? '') === 'COMPLETED';
        $priority = 'normal';
        $priVal = (int)($props['PRIORITY'] ?? 0);
        if ($priVal >= 1 && $priVal <= 4) {
            $priority = 'high';
        } elseif ($priVal >= 6 && $priVal <= 9) {
            $priority = 'low';
        }

        return [
            'caldavUid' => $props['UID'],
            'title' => $this->CalDAVUnescapeText($props['SUMMARY'] ?? ''),
            'info' => $this->CalDAVUnescapeText($props['DESCRIPTION'] ?? ''),
            'done' => $done,
            'doneAt' => $done ? $this->CalDAVParseDateTime($props['COMPLETED'] ?? '') : 0,
            'due' => $this->CalDAVParseDateTime($props['DUE'] ?? ''),
            'priority' => $priority,
            'createdAt' => $this->CalDAVParseDateTime($props['CREATED'] ?? ''),
            'caldavLastModified' => $this->CalDAVParseDateTime($props['LAST-MODIFIED'] ?? '')
        ];
    }

    private function CalDAVParseDateTime(string $Value): int
    {
        if ($Value === '') {
            return 0;
        }
        $Value = preg_replace('/[^0-9TZ]/', '', $Value) ?? $Value;
        
        $formats = ['Ymd\THis\Z', 'Ymd\THis', 'Ymd'];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $Value, new DateTimeZone('UTC'));
            if ($dt !== false) {
                return $dt->getTimestamp();
            }
        }
        return 0;
    }

    private function CalDAVMergeItems(array $LocalItems, array $ServerItems, string $ConflictMode): array
    {
        $result = [];
        $toUpload = [];
        $toDelete = [];

        $serverByUid = [];
        foreach ($ServerItems as $si) {
            $serverByUid[$si['caldavUid']] = $si;
        }

        $localByUid = [];
        foreach ($LocalItems as $li) {
            if (!empty($li['caldavUid'])) {
                $localByUid[$li['caldavUid']] = $li;
            }
        }

        foreach ($LocalItems as $local) {
            $uid = $local['caldavUid'] ?? '';
            
            if ($uid === '') {
                $uid = 'symcon-' . $this->InstanceID . '-' . $local['id'];
                $local['caldavUid'] = $uid;
                $local['caldavHref'] = $local['caldavHref'] ?? '';
                $toUpload[] = $local;
                $result[] = $local;
                continue;
            }

            if (!isset($serverByUid[$uid])) {
                if (($local['caldavSynced'] ?? 0) > 0) {
                    continue;
                } else {
                    $toUpload[] = $local;
                    $result[] = $local;
                }
                continue;
            }

            $server = $serverByUid[$uid];
            unset($serverByUid[$uid]);

            $serverEtag = $server['caldavEtag'] ?? '';
            $localEtag = $local['caldavEtag'] ?? '';
            $localModified = $local['localModified'] ?? 0;
            $lastSynced = $local['caldavSynced'] ?? 0;

            if (!empty($server['caldavHref'])) {
                $local['caldavHref'] = $server['caldavHref'];
            }

            if ($serverEtag === $localEtag && $localModified <= $lastSynced) {
                $local['title'] = $this->CalDAVMaybeUnescapeText((string)($local['title'] ?? ''));
                $local['info'] = $this->CalDAVMaybeUnescapeText((string)($local['info'] ?? ''));
                $result[] = $local;
                continue;
            }

            if ($localModified > $lastSynced && $serverEtag !== $localEtag) {
                $merged = $this->CalDAVResolveConflict($local, $server, $ConflictMode);
                if ($merged['uploadLocal']) {
                    $toUpload[] = $merged['item'];
                }
                $result[] = $merged['item'];
            } elseif ($serverEtag !== $localEtag) {
                $merged = $this->CalDAVApplyServerChanges($local, $server);
                $result[] = $merged;
            } else {
                $toUpload[] = $local;
                $result[] = $local;
            }
        }

        foreach ($serverByUid as $uid => $server) {
            $newItem = [
                'id' => $this->GetNextItemID(),
                'title' => $server['title'],
                'info' => $server['info'],
                'done' => $server['done'],
                'doneAt' => $server['doneAt'],
                'due' => $server['due'],
                'priority' => $server['priority'],
                'createdAt' => $server['createdAt'] ?: time(),
                'caldavUid' => $uid,
                'caldavEtag' => $server['caldavEtag'],
                'caldavHref' => $server['caldavHref'] ?? '',
                'caldavSynced' => time(),
                'localModified' => 0,
                'notification' => false,
                'notificationLeadTime' => 0,
                'notified' => false,
                'quantity' => 1,
                'recurrence' => 'none',
                'recurrenceCustomUnit' => '',
                'recurrenceCustomValue' => 0,
                'recurrenceReopenDays' => 0
            ];
            $result[] = $newItem;
        }

        return [
            'items' => $result,
            'toUpload' => $toUpload,
            'toDelete' => $toDelete
        ];
    }

    private function CalDAVResolveConflict(array $Local, array $Server, string $Mode): array
    {
        switch ($Mode) {
            case 'local_wins':
                return ['item' => $Local, 'uploadLocal' => true];
            
            case 'newest_wins':
                $localMod = $Local['localModified'] ?? 0;
                $serverMod = $Server['caldavLastModified'] ?? 0;
                if ($localMod >= $serverMod) {
                    return ['item' => $Local, 'uploadLocal' => true];
                }
                return ['item' => $this->CalDAVApplyServerChanges($Local, $Server), 'uploadLocal' => false];
            
            case 'server_wins':
            default:
                return ['item' => $this->CalDAVApplyServerChanges($Local, $Server), 'uploadLocal' => false];
        }
    }

    private function CalDAVApplyServerChanges(array $Local, array $Server): array
    {
        $Local['title'] = $Server['title'];
        $Local['info'] = $Server['info'];
        $Local['done'] = $Server['done'];
        $Local['doneAt'] = $Server['doneAt'];
        $Local['due'] = $Server['due'];
        $Local['priority'] = $Server['priority'];
        $Local['caldavEtag'] = $Server['caldavEtag'];
        if (!empty($Server['caldavHref'])) {
            $Local['caldavHref'] = $Server['caldavHref'];
        }
        $Local['caldavSynced'] = time();
        $Local['localModified'] = 0;
        return $Local;
    }

    private function CalDAVUploadItem(string $CalendarUrl, string $User, string $Pass, array $Item): bool
    {
        $uid = $Item['caldavUid'] ?? '';
        if ($uid === '') {
            return false;
        }

        $vcal = $this->CalDAVBuildVTodo($Item);
        $href = $Item['caldavHref'] ?? '';
        if ($href !== '') {
            $itemUrl = $this->CalDAVResolveUrl($CalendarUrl, $href);
        } else {
            $itemUrl = rtrim($CalendarUrl, '/') . '/' . urlencode($uid) . '.ics';
        }

        $headers = [
            'Content-Type: text/calendar; charset=utf-8'
        ];

        $etag = $Item['caldavEtag'] ?? '';
        if ($etag !== '') {
            $headers[] = 'If-Match: "' . $etag . '"';
        }

        $res = $this->CalDAVRequest('PUT', $itemUrl, $User, $Pass, $headers, $vcal, 10);

        $statusCode = (int)($res['status'] ?? 0);
        $this->SendDebug('CalDAV Upload', 'URL: ' . ($res['url'] ?? $itemUrl), 0);
        $this->SendDebug('CalDAV Upload', 'Status: ' . $statusCode, 0);
        if ($statusCode < 200 || $statusCode >= 300) {
            $this->SendDebug('CalDAV Upload', 'Response: ' . (($res['body'] ?? '') ?: 'empty'), 0);
        }
        return ($statusCode >= 200 && $statusCode < 300);
    }

    private function CalDAVDeleteItem(string $CalendarUrl, string $User, string $Pass, string $Uid, string $Href = ''): bool
    {
        if ($Href !== '') {
            $itemUrl = $this->CalDAVResolveUrl($CalendarUrl, $Href);
        } else {
            $itemUrl = rtrim($CalendarUrl, '/') . '/' . urlencode($Uid) . '.ics';
        }

        $res = $this->CalDAVRequest('DELETE', $itemUrl, $User, $Pass, [], '', 10);
        $statusCode = (int)($res['status'] ?? 0);
        return ($statusCode >= 200 && $statusCode < 300) || $statusCode === 404;
    }

    private function CalDAVBuildVTodo(array $Item): string
    {
        $uid = $Item['caldavUid'] ?? '';
        if ($uid === '' || strpos($uid, 'symcon-') === 0) {
            $uid = sprintf(
                '%08x-%04x-%04x-%04x-%012x',
                $this->InstanceID,
                $Item['id'] & 0xFFFF,
                mt_rand(0, 0xFFFF),
                mt_rand(0, 0x3FFF) | 0x8000,
                mt_rand(0, 0xFFFFFFFFFFFF)
            );
        }
        
        $now = gmdate('Ymd\THis\Z');
        $created = ($Item['createdAt'] ?? 0) > 0 ? gmdate('Ymd\THis\Z', $Item['createdAt']) : $now;
        
        $status = ($Item['done'] ?? false) ? 'COMPLETED' : 'NEEDS-ACTION';
        $percentComplete = ($Item['done'] ?? false) ? 100 : 0;
        $priority = match($Item['priority'] ?? 'normal') {
            'high' => 1,
            'low' => 9,
            default => 0
        };

        $titleText = $this->CalDAVMaybeUnescapeText((string)($Item['title'] ?? ''));
        $infoText = $this->CalDAVMaybeUnescapeText((string)($Item['info'] ?? ''));

        $serverUrl = (string)$this->ReadPropertyString('CalDAVServerURL');
        $isICloud = stripos($serverUrl, 'icloud.com') !== false;

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Symcon//ToDoList//EN',
            'CALSCALE:GREGORIAN'
        ];

        if ($isICloud) {
            $lines[] = 'METHOD:PUBLISH';
        }

        $lines = array_merge($lines, [
            'BEGIN:VTODO',
            'UID:' . $uid,
            'DTSTAMP:' . $now,
            'CREATED:' . $created,
            'LAST-MODIFIED:' . $now,
            'SEQUENCE:0',
            'SUMMARY:' . $this->CalDAVEscapeText($titleText),
            'STATUS:' . $status,
            'PERCENT-COMPLETE:' . $percentComplete
        ]);

        if ($priority > 0) {
            $lines[] = 'PRIORITY:' . $priority;
        }

        if ($infoText !== '') {
            $lines[] = 'DESCRIPTION:' . $this->CalDAVEscapeText($infoText);
        }

        if (($Item['due'] ?? 0) > 0) {
            $lines[] = 'DUE:' . gmdate('Ymd\THis\Z', $Item['due']);
        }

        if ($Item['done'] && ($Item['doneAt'] ?? 0) > 0) {
            $lines[] = 'COMPLETED:' . gmdate('Ymd\THis\Z', $Item['doneAt']);
        }

        $lines[] = 'END:VTODO';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines);
    }

    private function CalDAVEscapeText(string $Text): string
    {
        return str_replace(["\r\n", "\n", "\r", ',', ';', '\\'], ['\\n', '\\n', '\\n', '\\,', '\\;', '\\\\'], $Text);
    }

    private function CalDAVUnescapeText(string $Text): string
    {
        if ($Text === '') {
            return '';
        }
        $Text = str_replace('\\\\', '\\', $Text);
        $Text = str_replace(['\\n', '\\N'], "\n", $Text);
        $Text = str_replace(['\\,', '\\;'], [',', ';'], $Text);

        $Text = preg_replace('/\\\\+,/', ',', $Text) ?? $Text;
        $Text = preg_replace('/\\\\+;/', ';', $Text) ?? $Text;
        return $Text;
    }

    private function CalDAVMaybeUnescapeText(string $Text): string
    {
        if ($Text === '' || strpos($Text, '\\') === false) {
            return $Text;
        }
        return $this->CalDAVUnescapeText($Text);
    }

    private function CalDAVRequest(string $Method, string $Url, string $User, string $Pass, array $Headers, string $Body = '', int $Timeout = 15): array
    {
        $maxRedirects = 5;
        $currentUrl = $Url;

        for ($i = 0; $i <= $maxRedirects; $i++) {
            $reqHeaders = array_merge([
                'Authorization: Basic ' . base64_encode($User . ':' . $Pass),
                'User-Agent: IP-Symcon ToDoList'
            ], $Headers);

            $opts = [
                'http' => [
                    'method' => $Method,
                    'header' => $reqHeaders,
                    'content' => $Body,
                    'ignore_errors' => true,
                    'timeout' => $Timeout
                ]
            ];

            $context = stream_context_create($opts);
            $body = @file_get_contents($currentUrl, false, $context);
            $respHeaders = $http_response_header ?? [];
            $statusCode = $this->GetHttpStatusCode($respHeaders);

            if (in_array($statusCode, [301, 302, 307, 308], true)) {
                $location = $this->GetHttpHeaderValue($respHeaders, 'Location');
                if ($location === '') {
                    break;
                }
                $currentUrl = $this->CalDAVResolveUrl($currentUrl, $location);
                continue;
            }

            return [
                'status' => $statusCode,
                'body' => ($body === false) ? '' : $body,
                'headers' => $respHeaders,
                'url' => $currentUrl
            ];
        }

        return [
            'status' => 0,
            'body' => '',
            'headers' => [],
            'url' => $currentUrl
        ];
    }

    public function CalDAVRefreshCalendarOptions(): void
    {
        $stored = $this->CalDAVFetchAndStoreCalendarOptions();
        if ($stored === null) {
            echo $this->Translate('Failed to fetch calendars.');
            return;
        }

        if (empty($stored)) {
            echo $this->Translate('No calendars found.');
            return;
        }

        $options = $this->GetCalDAVCalendarOptions();
        $this->UpdateFormField('CalDAVCalendarPath', 'options', json_encode($options));
        echo sprintf($this->Translate('Found %d calendar(s).'), count($stored));
    }

    public function CalDAVDiscoverCalendars(): string
    {
        ob_start();
        $this->CalDAVRefreshCalendarOptions();
        return (string)ob_get_clean();
    }

    private function CalDAVFetchAndStoreCalendarOptions(): ?array
    {
        $url = trim($this->ReadPropertyString('CalDAVServerURL'));
        $user = trim($this->ReadPropertyString('CalDAVUsername'));
        $pass = trim($this->ReadPropertyString('CalDAVPassword'));

        if ($url === '' || $user === '' || $pass === '') {
            return null;
        }

        $principal = $this->CalDAVGetPrincipal($url, $user, $pass);
        if ($principal === null) {
            return null;
        }

        $calendarHome = $this->CalDAVGetCalendarHome($url, $principal, $user, $pass);
        if ($calendarHome === null) {
            return null;
        }

        $calendars = $this->CalDAVListCalendars($url, $calendarHome, $user, $pass);
        $stored = [];
        foreach ($calendars as $cal) {
            $stored[] = [
                'name' => $cal['name'],
                'path' => $cal['path'],
                'supportsTodo' => $cal['supportsTodo']
            ];
        }

        $this->WriteAttributeString('CalDAVCalendarOptions', json_encode($stored));
        return $stored;
    }

    private function GetCalDAVCalendarOptions(): array
    {
        $options = [['caption' => $this->Translate('Please select...'), 'value' => '']];

        $stored = json_decode($this->ReadAttributeString('CalDAVCalendarOptions'), true);
        if (is_array($stored)) {
            foreach ($stored as $cal) {
                $name = $cal['name'] ?? 'Untitled';
                $path = $cal['path'] ?? '';
                $todo = !empty($cal['supportsTodo']);
                $suffix = $todo ? ' (VTODO)' : ' (' . $this->Translate('Events only') . ')';
                $options[] = [
                    'caption' => $name . $suffix,
                    'value' => $path
                ];
            }
        }

        $currentPath = $this->ReadPropertyString('CalDAVCalendarPath');
        if ($currentPath !== '') {
            $found = false;
            foreach ($options as $opt) {
                if ($opt['value'] === $currentPath) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $options[] = ['caption' => $currentPath, 'value' => $currentPath];
            }
        }

        return $options;
    }

    private function CalDAVGetPrincipal(string $BaseUrl, string $User, string $Pass): ?string
    {
        $testUrl = rtrim($BaseUrl, '/') . '/';

        $res = $this->CalDAVRequest(
            'PROPFIND',
            $testUrl,
            $User,
            $Pass,
            [
                'Depth: 0',
                'Content-Type: application/xml; charset=utf-8'
            ],
            '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:current-user-principal/></d:prop></d:propfind>',
            15
        );

        if (($res['status'] ?? 0) !== 207 && ($res['status'] ?? 0) !== 200) {
            return null;
        }

        $xml = @simplexml_load_string((string)($res['body'] ?? ''));
        if ($xml === false) {
            return null;
        }

        $xml->registerXPathNamespace('d', 'DAV:');
        $principals = $xml->xpath('//d:current-user-principal/d:href');
        
        if (!empty($principals)) {
            return (string)$principals[0];
        }

        return null;
    }

    private function CalDAVGetCalendarHome(string $BaseUrl, string $Principal, string $User, string $Pass): ?string
    {
        $principalUrl = $this->CalDAVResolveUrl($BaseUrl, $Principal);

        $res = $this->CalDAVRequest(
            'PROPFIND',
            $principalUrl,
            $User,
            $Pass,
            [
                'Depth: 0',
                'Content-Type: application/xml; charset=utf-8'
            ],
            '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav"><d:prop><c:calendar-home-set/></d:prop></d:propfind>',
            15
        );

        if (($res['status'] ?? 0) !== 207) {
            return null;
        }

        $xml = @simplexml_load_string((string)($res['body'] ?? ''));
        if ($xml === false) {
            return null;
        }

        $xml->registerXPathNamespace('d', 'DAV:');
        $xml->registerXPathNamespace('c', 'urn:ietf:params:xml:ns:caldav');
        
        $homes = $xml->xpath('//c:calendar-home-set/d:href');
        
        if (!empty($homes)) {
            return (string)$homes[0];
        }

        return null;
    }

    private function CalDAVListCalendars(string $BaseUrl, string $CalendarHome, string $User, string $Pass): array
    {
        $homeUrl = $this->CalDAVResolveUrl($BaseUrl, $CalendarHome);

        $res = $this->CalDAVRequest(
            'PROPFIND',
            $homeUrl,
            $User,
            $Pass,
            [
                'Depth: 1',
                'Content-Type: application/xml; charset=utf-8'
            ],
            '<?xml version="1.0" encoding="utf-8"?>' .
                '<d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:cs="http://calendarserver.org/ns/">' .
                '<d:prop><d:displayname/><d:resourcetype/><c:supported-calendar-component-set/></d:prop>' .
                '</d:propfind>',
            15
        );

        $this->SendDebug('CalDAV Discovery', 'Home URL: ' . ($res['url'] ?? $homeUrl), 0);

        if (($res['status'] ?? 0) !== 207) {
            $this->SendDebug('CalDAV Discovery', 'Failed to fetch calendar list', 0);
            return [];
        }

        $this->SendDebug('CalDAV Discovery', 'Response length: ' . strlen((string)($res['body'] ?? '')), 0);

        $xml = @simplexml_load_string((string)($res['body'] ?? ''));
        if ($xml === false) {
            $this->SendDebug('CalDAV Discovery', 'Failed to parse XML', 0);
            return [];
        }

        $xml->registerXPathNamespace('d', 'DAV:');
        $xml->registerXPathNamespace('c', 'urn:ietf:params:xml:ns:caldav');

        $calendars = [];
        $responses = $xml->xpath('//d:response');
        
        $this->SendDebug('CalDAV Discovery', 'Found ' . count($responses) . ' responses', 0);

        foreach ($responses as $response) {
            $response->registerXPathNamespace('d', 'DAV:');
            $response->registerXPathNamespace('c', 'urn:ietf:params:xml:ns:caldav');
            
            $hrefNodes = $response->xpath('d:href');
            $href = !empty($hrefNodes) ? (string)$hrefNodes[0] : '';
            
            $displayNameNodes = $response->xpath('d:propstat/d:prop/d:displayname');
            $displayName = !empty($displayNameNodes) ? (string)$displayNameNodes[0] : '';
            
            $this->SendDebug('CalDAV Discovery', 'Checking: ' . ($displayName ?: $href), 0);
            
            $resourceTypes = $response->xpath('d:propstat/d:prop/d:resourcetype/c:calendar');
            if (empty($resourceTypes)) {
                $this->SendDebug('CalDAV Discovery', '  -> Skipped (not a calendar)', 0);
                continue;
            }

            $supportsTodo = false;
            $components = $response->xpath('d:propstat/d:prop/c:supported-calendar-component-set/c:comp');
            foreach ($components as $comp) {
                $name = (string)($comp->attributes()['name'] ?? '');
                if (strtoupper($name) === 'VTODO') {
                    $supportsTodo = true;
                    break;
                }
            }

            $path = $href;
            if (strpos($href, '://') !== false) {
                $parsed = parse_url($href);
                $path = $parsed['path'] ?? $href;
            }

            $baseParsed = parse_url($BaseUrl);
            $basePath = rtrim($baseParsed['path'] ?? '', '/');
            if ($basePath !== '' && strpos($path, $basePath) === 0) {
                $path = substr($path, strlen($basePath));
            }
            $path = ltrim($path, '/');

            $calendars[] = [
                'name' => $displayName ?: basename($path),
                'path' => $path,
                'href' => $href,
                'supportsTodo' => $supportsTodo
            ];
        }

        usort($calendars, fn($a, $b) => ($b['supportsTodo'] <=> $a['supportsTodo']) ?: strcasecmp($a['name'], $b['name']));

        return $calendars;
    }

    private function CalDAVResolveUrl(string $BaseUrl, string $Path): string
    {
        if (strpos($Path, '://') !== false) {
            return $Path;
        }

        $parsed = parse_url($BaseUrl);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $basePath = $parsed['path'] ?? '/';

        if ($Path === '') {
            $Path = $basePath;
        } elseif ($Path[0] !== '/') {
            $dir = $basePath;
            if ($dir === '') {
                $dir = '/';
            }
            if (substr($dir, -1) !== '/') {
                $dir .= '/';
            }
            $Path = $dir . ltrim($Path, '/');
        }

        if ($Path !== '' && $Path[0] !== '/') {
            $Path = '/' . $Path;
        }

        return $scheme . '://' . $host . $port . $Path;
    }

    private function GetCalDAVFormElements(string $SyncBackend): array
    {
        return [
            'type' => 'ExpansionPanel',
            'caption' => $this->Translate('CalDAV Synchronization'),
            'visible' => $SyncBackend === 'caldav',
            'items' => [
                [
                    'type' => 'CheckBox',
                    'name' => 'CalDAVEnabled',
                    'caption' => $this->Translate('Enabled'),
                    'visible' => false
                ],
                [
                    'type' => 'ValidationTextBox',
                    'name' => 'CalDAVServerURL',
                    'caption' => $this->Translate('Server URL'),
                    'width' => '400px'
                ],
                [
                    'type' => 'ValidationTextBox',
                    'name' => 'CalDAVUsername',
                    'caption' => $this->Translate('Username'),
                    'width' => '250px'
                ],
                [
                    'type' => 'PasswordTextBox',
                    'name' => 'CalDAVPassword',
                    'caption' => $this->Translate('Password'),
                    'width' => '250px'
                ],
                [
                    'type' => 'Select',
                    'name' => 'CalDAVCalendarPath',
                    'caption' => $this->Translate('Calendar'),
                    'width' => '400px',
                    'options' => $this->GetCalDAVCalendarOptions()
                ],
                [
                    'type' => 'Select',
                    'name' => 'CalDAVSyncInterval',
                    'caption' => $this->Translate('Sync Interval'),
                    'width' => '200px',
                    'options' => $this->GetSyncIntervalOptions()
                ],
                [
                    'type' => 'Select',
                    'name' => 'CalDAVConflictMode',
                    'caption' => $this->Translate('On Conflict'),
                    'width' => '250px',
                    'options' => $this->GetConflictModeOptions()
                ],
                [
                    'type' => 'RowLayout',
                    'items' => [
                        [
                            'type' => 'Button',
                            'caption' => $this->Translate('Test Connection'),
                            'onClick' => 'TDL_CalDAVTestConnection($id);'
                        ],
                        [
                            'type' => 'Button',
                            'caption' => $this->Translate('Refresh Calendars'),
                            'onClick' => 'TDL_CalDAVRefreshCalendarOptions($id);'
                        ],
                        [
                            'type' => 'Button',
                            'caption' => $this->Translate('Sync Now'),
                            'onClick' => 'TDL_CalDAVSync($id);'
                        ],
                        [
                            'type' => 'Button',
                            'caption' => $this->Translate('Reset Sync'),
                            'onClick' => 'echo TDL_CalDAVResetSync($id);'
                        ]
                    ]
                ],
                [
                    'type' => 'Label',
                    'caption' => $this->GetCalDAVStatusLabel()
                ]
            ]
        ];
    }
}
