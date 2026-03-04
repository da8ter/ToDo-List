<?php

declare(strict_types=1);

trait GoogleTasksSync
{
    private function RegisterGoogleWebHook(): void
    {
        $this->OAuthRegisterWebHook('/hook/todolist_google/');
    }

    private function UpdateGoogleTasksTimer(): void
    {
        $hasToken = $this->GoogleGetDecryptedToken('GoogleRefreshToken') !== '';
        $this->SyncUpdateTimer('google', 'GoogleTasksSyncTimer', $this->ReadPropertyInteger('GoogleSyncInterval'), $hasToken);
    }

    private function GoogleSetEncryptedToken(string $Attribute, string $Token): void
    {
        $this->OAuthSetEncryptedToken($Attribute, $Token, 'GKey');
    }

    private function GoogleGetDecryptedToken(string $Attribute): string
    {
        return $this->OAuthGetDecryptedToken($Attribute, 'GKey');
    }

    public function GoogleGetAuthUrl(): string
    {
        $clientId = trim($this->ReadPropertyString('GoogleClientID'));
        if ($clientId === '') {
            return $this->Translate('Please enter Client ID first.');
        }

        $redirectUri = $this->OAuthGetRedirectUri('/hook/todolist_google/');
        $state = $this->InstanceID . '_' . bin2hex(random_bytes(8));

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/tasks',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public function GoogleHandleCallback(string $Code): bool
    {
        $clientId = trim($this->ReadPropertyString('GoogleClientID'));
        $clientSecret = trim($this->ReadPropertyString('GoogleClientSecret'));
        $redirectUri = $this->OAuthGetRedirectUri('/hook/todolist_google/');

        if ($clientId === '' || $clientSecret === '' || $Code === '') {
            $this->SendDebug('GoogleTasks', 'HandleCallback: Missing credentials or code', 0);
            return false;
        }

        $postData = [
            'code' => $Code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ];

        $success = $this->OAuthExchangeToken(
            'https://oauth2.googleapis.com/token',
            $postData, 'GKey',
            'GoogleAccessToken', 'GoogleRefreshToken', 'GoogleTokenExpires',
            'GoogleTasks'
        );

        if ($success) {
            $this->UpdateGoogleTasksTimer();
            $this->GoogleFetchAndStoreTaskListOptions();
        }
        return $success;
    }

    private function GoogleGetValidAccessToken(): string
    {
        return $this->OAuthGetValidAccessToken(
            'GKey',
            'GoogleAccessToken', 'GoogleRefreshToken', 'GoogleTokenExpires',
            'https://oauth2.googleapis.com/token',
            trim($this->ReadPropertyString('GoogleClientID')),
            trim($this->ReadPropertyString('GoogleClientSecret')),
            'GoogleTasks'
        );
    }

    private function GoogleApiRequest(string $Method, string $Endpoint, mixed $Body = null): ?array
    {
        $url = 'https://tasks.googleapis.com' . $Endpoint;
        $token = $this->GoogleGetValidAccessToken();
        $response = $this->OAuthHttpRequest($Method, $url, [], is_array($Body) ? json_encode($Body) : $Body, true, 'GoogleTasks', $token);

        if ($response === null) {
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            $this->SendDebug('GoogleTasks', 'Invalid JSON response', 0);
            return null;
        }

        if (isset($data['error'])) {
            $this->SendDebug('GoogleTasks', 'API error: ' . json_encode($data['error']), 0);
            return null;
        }

        return $data;
    }

    public function GoogleRefreshTaskListOptions(): void
    {
        $token = $this->GoogleGetValidAccessToken();
        if ($token === '') {
            echo $this->Translate('Not connected to Google. Please authorize first.');
            return;
        }

        $stored = $this->GoogleFetchAndStoreTaskListOptions();
        if ($stored === null) {
            echo $this->Translate('Failed to fetch task lists.');
            return;
        }

        $options = $this->GetGoogleTaskListOptions();
        $this->UpdateFormField('GoogleTaskListID', 'options', json_encode($options));
        echo sprintf($this->Translate('Found %d task list(s).'), count($stored));
    }

    public function GoogleDiscoverTasklists(): string
    {
        ob_start();
        $this->GoogleRefreshTaskListOptions();
        return (string)ob_get_clean();
    }

    private function GoogleSanitizeTaskListTitle(string $Title): string
    {
        $title = trim($Title);
        if ($title === '') {
            return $title;
        }

        $title = preg_replace('/[\x{FE0F}\x{200D}]/u', '', $title) ?? $title;
        $title = preg_replace('/[\x{2600}-\x{27BF}]/u', '', $title) ?? $title;
        $title = preg_replace('/[\x{1F000}-\x{1FAFF}]/u', '', $title) ?? $title;
        $title = preg_replace('/\s{2,}/u', ' ', $title) ?? $title;

        return trim($title);
    }

    private function GoogleFetchAndStoreTaskListOptions(): ?array
    {
        $data = $this->GoogleApiRequest('GET', '/tasks/v1/users/@me/lists');
        if ($data === null) {
            return null;
        }

        $items = $data['items'] ?? [];
        $stored = [];
        foreach ($items as $list) {
            if (!is_array($list)) {
                continue;
            }
            $id = (string)($list['id'] ?? '');
            $title = $this->GoogleSanitizeTaskListTitle((string)($list['title'] ?? 'Untitled'));
            if ($id !== '') {
                $stored[] = ['id' => $id, 'title' => $title];
            }
        }

        $this->WriteAttributeString('GoogleTaskListOptions', json_encode($stored));
        return $stored;
    }

    private function GetGoogleTaskListOptions(): array
    {
        $options = [['caption' => $this->Translate('Please select...'), 'value' => '']];

        $stored = json_decode($this->ReadAttributeString('GoogleTaskListOptions'), true);
        if (is_array($stored)) {
            foreach ($stored as $item) {
                $options[] = [
                    'caption' => $item['title'] ?? 'Untitled',
                    'value' => $item['id'] ?? ''
                ];
            }
        }

        $currentId = $this->ReadPropertyString('GoogleTaskListID');
        if ($currentId !== '') {
            $found = false;
            foreach ($options as $opt) {
                if ($opt['value'] === $currentId) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $options[] = ['caption' => $currentId, 'value' => $currentId];
            }
        }

        return $options;
    }

    public function GoogleTestConnection(): bool
    {
        $token = $this->GoogleGetValidAccessToken();
        if ($token === '') {
            echo $this->Translate('Not connected. Please authorize first.');
            return false;
        }

        $data = $this->GoogleApiRequest('GET', '/tasks/v1/users/@me/lists');
        if ($data === null) {
            echo $this->Translate('Connection failed.');
            return false;
        }

        $count = count($data['items'] ?? []);
        echo sprintf($this->Translate('Connection successful. Found %d task list(s).'), $count);
        return true;
    }

    public function GoogleDisconnect(): void
    {
        $this->GoogleSetEncryptedToken('GoogleAccessToken', '');
        $this->GoogleSetEncryptedToken('GoogleRefreshToken', '');
        $this->WriteAttributeInteger('GoogleTokenExpires', 0);
        $this->WriteAttributeInteger('GoogleLastSync', 0);
        $this->UpdateGoogleTasksTimer();
        echo $this->Translate('Disconnected from Google.');
    }

    public function GoogleTasksSync(): bool
    {
        $sem = 'TDL_GoogleSync_' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($sem, 0)) {
            $this->SendDebug('GoogleTasks', 'Sync skipped - already running', 0);
            return false;
        }

        try {
            return $this->GoogleTasksSyncInternal();
        } finally {
            IPS_SemaphoreLeave($sem);
        }
    }

    public function GoogleResetSync(): void
    {
        $this->SyncResetItems(
            ['googleTaskId'],
            ['googleEtag'],
            ['googleSynced'],
            'GoogleLastSync',
            'GooglePendingDeletes'
        );
    }

    private function GoogleTasksSyncInternal(): bool
    {
        if ($this->GetSyncBackend() !== 'google') {
            $this->SendDebug('GoogleTasks', 'Sync skipped - not enabled', 0);
            return false;
        }

        $taskListId = trim($this->ReadPropertyString('GoogleTaskListID'));
        if ($taskListId === '') {
            $this->SendDebug('GoogleTasks', 'Sync skipped - no task list selected', 0);
            return false;
        }

        $token = $this->GoogleGetValidAccessToken();
        if ($token === '') {
            $this->SendDebug('GoogleTasks', 'Sync skipped - not authenticated', 0);
            return false;
        }

        $this->SendDebug('GoogleTasks', 'Starting sync...', 0);

        $pendingDeletes = json_decode((string)$this->ReadAttributeString('GooglePendingDeletes'), true);
        if (!is_array($pendingDeletes)) {
            $pendingDeletes = [];
        }
        $this->SyncProcessPendingDeletes($pendingDeletes, fn($id) => $this->GoogleDeleteTask($taskListId, $id), 'GoogleTasks');
        $this->WriteAttributeString('GooglePendingDeletes', json_encode($pendingDeletes, JSON_UNESCAPED_SLASHES));

        $serverTasks = $this->GoogleFetchTasks($taskListId);
        if ($serverTasks === null) {
            $this->SendDebug('GoogleTasks', 'Failed to fetch tasks from Google', 0);
            return false;
        }

        $localItems = $this->LoadItems();
        $conflictMode = $this->ReadPropertyString('GoogleConflictMode');
        $result = $this->GoogleMergeItems($localItems, $serverTasks, $conflictMode);

        $items = $result['items'];
        $now = time();

        foreach ($result['toUpload'] as $uploadItem) {
            $uploadResult = $this->GoogleUploadTask($taskListId, $uploadItem);
            if ($uploadResult['success']) {
                $oldId = $uploadResult['oldId'];
                $newId = $uploadResult['newId'];
                for ($i = 0; $i < count($items); $i++) {
                    if (($items[$i]['googleTaskId'] ?? '') === $oldId || ($items[$i]['id'] ?? 0) === ($uploadItem['id'] ?? -1)) {
                        $items[$i]['googleTaskId'] = $newId;
                        $items[$i]['googleEtag'] = $uploadResult['etag'];
                        $items[$i]['googleSynced'] = $now;
                        $items[$i]['localModified'] = 0;
                        break;
                    }
                }
                $this->SendDebug('GoogleTasks', 'Uploaded: ' . ($uploadItem['title'] ?? $newId), 0);
            }
        }

        foreach ($items as &$item) {
            if (!empty($item['googleTaskId']) && ($item['googleSynced'] ?? 0) === 0) {
                $item['googleSynced'] = $now;
            }
        }
        unset($item);

        $this->SaveItems($items);
        $this->WriteAttributeInteger('GoogleLastSync', $now);
        $this->SyncPostComplete();

        $this->SendDebug('GoogleTasks', 'Sync completed', 0);
        return true;
    }

    private function GoogleFetchTasks(string $TaskListId): ?array
    {
        $allTasks = [];
        $pageToken = '';

        do {
            $endpoint = '/tasks/v1/lists/' . urlencode($TaskListId) . '/tasks?showCompleted=true&showHidden=true&showDeleted=true&maxResults=100';
            if ($pageToken !== '') {
                $endpoint .= '&pageToken=' . urlencode($pageToken);
            }

            $data = $this->GoogleApiRequest('GET', $endpoint);
            if ($data === null) {
                return null;
            }

            foreach ($data['items'] ?? [] as $task) {
                if (!empty($task['deleted'])) {
                    continue;
                }
                $this->SendDebug('GoogleTasksPayload', json_encode($task, JSON_UNESCAPED_SLASHES), 0);
                $allTasks[] = $this->GoogleTaskToLocal($task);
            }

            $pageToken = $data['nextPageToken'] ?? '';
        } while ($pageToken !== '');

        return $allTasks;
    }

    private function GoogleTaskToLocal(array $Task): array
    {
        $done = ($Task['status'] ?? '') === 'completed';
        $doneAt = 0;
        if ($done && isset($Task['completed'])) {
            $doneAt = strtotime($Task['completed']) ?: 0;
        }

        $due = 0;
        if (isset($Task['due'])) {
            $dueStr = (string) $Task['due'];
            if (preg_match('/^(\d{4}-\d{2}-\d{2})T00:00:00(\.000)?Z$/', $dueStr, $m)) {
                $due = strtotime($m[1] . ' 00:00:00') ?: 0;
            } else {
                $dt = new DateTime($dueStr);
                $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
                $due = $dt->getTimestamp();
            }
        }

        $updated = 0;
        if (isset($Task['updated'])) {
            $updated = strtotime($Task['updated']) ?: 0;
        }

        return [
            'googleTaskId' => $Task['id'] ?? '',
            'googleEtag' => $Task['etag'] ?? '',
            'title' => $Task['title'] ?? '',
            'info' => $Task['notes'] ?? '',
            'done' => $done,
            'doneAt' => $doneAt,
            'due' => $due,
            'googleUpdated' => $updated
        ];
    }

    private function LocalToGoogleTask(array $Item): array
    {
        $task = [
            'title' => $Item['title'] ?? '',
            'notes' => $Item['info'] ?? '',
            'status' => ($Item['done'] ?? false) ? 'completed' : 'needsAction'
        ];

        $due = (int)($Item['due'] ?? 0);
        if ($due > 0) {
            $localDate = date('Y-m-d', $due);
            $task['due'] = $localDate . 'T00:00:00.000Z';
        }

        return $task;
    }

    private function MergeDueWithLocalTime(int $localDue, int $serverDue): int
    {
        if ($serverDue === 0) {
            return 0;
        }
        if ($localDue === 0) {
            return $serverDue;
        }

        $serverDate = date('Y-m-d', $serverDue);
        $localTime = date('H:i:s', $localDue);
        return strtotime($serverDate . ' ' . $localTime) ?: $serverDue;
    }

    private function GoogleMergeItems(array $LocalItems, array $ServerTasks, string $ConflictMode): array
    {
        $toUpload = [];
        $serverByGoogleId = [];
        foreach ($ServerTasks as $st) {
            $gid = $st['googleTaskId'] ?? '';
            if ($gid !== '') {
                $serverByGoogleId[$gid] = $st;
            }
        }

        $processedGoogleIds = [];

        foreach ($LocalItems as &$local) {
            $googleId = $local['googleTaskId'] ?? '';

            if ($googleId === '') {
                $newGoogleId = 'pending_' . $this->InstanceID . '_' . ($local['id'] ?? uniqid());
                $local['googleTaskId'] = $newGoogleId;
                $local['localModified'] = time();
                $toUpload[] = $local;
                continue;
            }

            $processedGoogleIds[$googleId] = true;

            if (!isset($serverByGoogleId[$googleId])) {
                $lastSynced = (int)($local['googleSynced'] ?? 0);
                $localMod = (int)($local['localModified'] ?? 0);
                $localChanged = $localMod > $lastSynced;

                if (strpos($googleId, 'pending_') !== 0) {
                    if ($ConflictMode === 'local_wins' && $localChanged) {
                        $newGoogleId = 'pending_' . $this->InstanceID . '_' . ($local['id'] ?? uniqid());
                        $local['googleTaskId'] = $newGoogleId;
                        $local['localModified'] = time();
                        $toUpload[] = $local;
                    } else {
                        $local['_googleDeleted'] = true;
                    }
                }
                continue;
            }

            $server = $serverByGoogleId[$googleId];
            $localMod = (int)($local['localModified'] ?? 0);
            $serverMod = (int)($server['googleUpdated'] ?? 0);
            $lastSynced = (int)($local['googleSynced'] ?? 0);

            $localChanged = $localMod > $lastSynced;
            $serverChanged = $serverMod > $lastSynced;

            if ($localChanged && $serverChanged) {
                $localWins = ($ConflictMode === 'local_wins') || ($ConflictMode === 'newest_wins' && $localMod > $serverMod);

                if ($localWins) {
                    $toUpload[] = $local;
                } else {
                    $this->GoogleApplyServerToLocal($local, $server);
                }
            } elseif ($localChanged) {
                $toUpload[] = $local;
            } else {
                $this->GoogleApplyServerToLocal($local, $server);
            }
        }
        unset($local);

        $filtered = [];
        foreach ($LocalItems as $it) {
            if (!empty($it['_googleDeleted'])) {
                continue;
            }
            unset($it['_googleDeleted']);
            $filtered[] = $it;
        }
        $LocalItems = $filtered;

        foreach ($ServerTasks as $server) {
            $pendingDeletes = json_decode((string)$this->ReadAttributeString('GooglePendingDeletes'), true);
            if (!is_array($pendingDeletes)) {
                $pendingDeletes = [];
            }
            $gid = $server['googleTaskId'] ?? '';
            if ($gid === '' || isset($processedGoogleIds[$gid])) {
                continue;
            }

            if (isset($pendingDeletes[$gid])) {
                continue;
            }

            $newItem = [
                'id' => $this->GetNextItemID(),
                'title' => $server['title'],
                'info' => $server['info'],
                'done' => $server['done'],
                'doneAt' => $server['doneAt'],
                'due' => $server['due'],
                'createdAt' => time(),
                'priority' => 'normal',
                'notification' => false,
                'quantity' => 0,
                'recurrence' => 'none',
                'googleTaskId' => $gid,
                'googleEtag' => $server['googleEtag'],
                'googleSynced' => time(),
                'localModified' => 0
            ];
            $LocalItems[] = $newItem;
        }

        return [
            'items' => $LocalItems,
            'toUpload' => $toUpload
        ];
    }

    private function GoogleApplyServerToLocal(array &$Local, array $Server): void
    {
        $Local['title'] = $Server['title'];
        $Local['info'] = $Server['info'];
        $Local['done'] = $Server['done'];
        $Local['doneAt'] = $Server['doneAt'];
        $Local['due'] = $this->MergeDueWithLocalTime((int)($Local['due'] ?? 0), (int)($Server['due'] ?? 0));
        $Local['googleEtag'] = $Server['googleEtag'];
        $Local['localModified'] = 0;
    }

    private function GoogleUploadTask(string $TaskListId, array $Item): array
    {
        $googleId = $Item['googleTaskId'] ?? '';
        $taskData = $this->LocalToGoogleTask($Item);

        if ($googleId === '' || strpos($googleId, 'pending_') === 0) {
            $data = $this->GoogleApiRequest('POST', '/tasks/v1/lists/' . urlencode($TaskListId) . '/tasks', $taskData);
            if ($data === null) {
                return ['success' => false, 'oldId' => $googleId, 'newId' => '', 'etag' => ''];
            }

            return [
                'success' => true,
                'oldId' => $googleId,
                'newId' => $data['id'] ?? '',
                'etag' => $data['etag'] ?? ''
            ];
        }

        $data = $this->GoogleApiRequest('PATCH', '/tasks/v1/lists/' . urlencode($TaskListId) . '/tasks/' . urlencode($googleId), $taskData);
        return [
            'success' => $data !== null,
            'oldId' => $googleId,
            'newId' => $googleId,
            'etag' => $data['etag'] ?? ''
        ];
    }

    private function GoogleDeleteTask(string $TaskListId, string $GoogleTaskId): bool
    {
        if ($GoogleTaskId === '' || strpos($GoogleTaskId, 'pending_') === 0) {
            return true;
        }

        $url = 'https://tasks.googleapis.com/tasks/v1/lists/' . urlencode($TaskListId) . '/tasks/' . urlencode($GoogleTaskId);
        $token = $this->GoogleGetValidAccessToken();
        $response = $this->OAuthHttpRequest('DELETE', $url, [], null, true, 'GoogleTasks', $token);
        return $response !== null;
    }

    private function AddGooglePendingDelete(string $GoogleTaskId): void
    {
        $this->SyncAddPendingDelete($GoogleTaskId, 'pending_', 'GooglePendingDeletes');
    }

    private function GetGoogleTasksStatusLabel(): string
    {
        $refreshToken = $this->GoogleGetDecryptedToken('GoogleRefreshToken');
        $lastSync = $this->ReadAttributeInteger('GoogleLastSync');
        return $this->SyncGetStatusLabel($refreshToken, $lastSync);
    }

    private function GetGoogleTasksFormElements(string $SyncBackend): array
    {
        return [
            'type' => 'ExpansionPanel',
            'caption' => $this->Translate('Google Tasks Synchronization'),
            'visible' => $SyncBackend === 'google',
            'items' => [
                [
                    'type' => 'Label',
                    'caption' => $this->Translate('Due time and recurrences are not supported by the Google API.')
                ],
                [
                    'type' => 'ValidationTextBox',
                    'caption' => $this->Translate('Redirect URI'),
                    'value' => $this->OAuthGetRedirectUri('/hook/todolist_google/'),
                    'width' => '550px',
                    'enabled' => false
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'GoogleTasksEnabled',
                    'caption' => $this->Translate('Enabled'),
                    'visible' => false
                ],
                [
                    'type' => 'ValidationTextBox',
                    'name' => 'GoogleClientID',
                    'caption' => $this->Translate('Client ID'),
                    'width' => '400px'
                ],
                [
                    'type' => 'PasswordTextBox',
                    'name' => 'GoogleClientSecret',
                    'caption' => $this->Translate('Client Secret'),
                    'width' => '400px'
                ],
                [
                    'type' => 'Select',
                    'name' => 'GoogleTaskListID',
                    'caption' => $this->Translate('Task List'),
                    'width' => '400px',
                    'options' => $this->GetGoogleTaskListOptions()
                ],
                [
                    'type' => 'Select',
                    'name' => 'GoogleSyncInterval',
                    'caption' => $this->Translate('Sync Interval'),
                    'width' => '200px',
                    'options' => $this->GetSyncIntervalOptions()
                ],
                [
                    'type' => 'Select',
                    'name' => 'GoogleConflictMode',
                    'caption' => $this->Translate('On Conflict'),
                    'width' => '250px',
                    'options' => $this->GetConflictModeOptions()
                ],
                [
                    'type' => 'RowLayout',
                    'items' => [
                        [
                            'type' => 'Button',
                            'caption' => $this->Translate('Authorize with Google'),
                            'onClick' => 'echo TDL_GoogleGetAuthUrl($id);'
                        ],
                        [
                            'type' => 'Button',
                            'caption' => $this->Translate('Test Connection'),
                            'onClick' => 'TDL_GoogleTestConnection($id);'
                        ],
                        [
                            'type' => 'Button',
                            'caption' => $this->Translate('Refresh Task Lists'),
                            'onClick' => 'TDL_GoogleRefreshTaskListOptions($id);'
                        ]
                    ]
                ],
                [
                    'type' => 'RowLayout',
                    'items' => [
                        [
                            'type' => 'Button',
                            'caption' => $this->Translate('Sync Now'),
                            'onClick' => 'TDL_GoogleTasksSync($id);'
                        ],
                        [
                            'type' => 'Button',
                            'caption' => $this->Translate('Reset Sync'),
                            'onClick' => 'echo TDL_GoogleResetSync($id);'
                        ],
                        [
                            'type' => 'Button',
                            'caption' => $this->Translate('Disconnect'),
                            'onClick' => 'TDL_GoogleDisconnect($id);'
                        ]
                    ]
                ],
                [
                    'type' => 'Label',
                    'caption' => $this->GetGoogleTasksStatusLabel()
                ]
            ]
        ];
    }
}
