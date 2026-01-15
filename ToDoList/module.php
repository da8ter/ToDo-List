<?php

declare(strict_types=1);

class ToDoList extends IPSModuleStrict
{
    public function Create(): void
    {
        parent::Create();
        $this->SetVisualizationType(1);
        $this->RegisterPropertyString('ItemsTable', '[]');
        $this->RegisterPropertyInteger('VisualizationInstanceID', 0);
        $this->RegisterPropertyInteger('NotificationLeadTime', 600);
        $this->RegisterPropertyBoolean('ShowOverview', true);
        $this->RegisterPropertyBoolean('ShowCreateButton', true);
        $this->RegisterPropertyBoolean('ShowSorting', true);
        $this->RegisterPropertyBoolean('UseGridView', false);
        $this->RegisterPropertyBoolean('ShowLargeQuantity', false);
        $this->RegisterPropertyBoolean('GridShoppingListMode', false);
        $this->RegisterPropertyBoolean('ShowInfoBadges', true);
        $this->RegisterPropertyBoolean('ShowDeleteButton', true);
        $this->RegisterPropertyBoolean('ShowEditButton', true);
        $this->RegisterPropertyBoolean('HideCompletedTasks', false);
        $this->RegisterPropertyBoolean('DeleteCompletedTasks', false);
        $this->RegisterAttributeString('Items', '[]');
        $this->RegisterAttributeInteger('NextID', 1);
        $this->RegisterAttributeInteger('LastConfigFormRequest', 0);
        $this->RegisterAttributeString('LastItemsTableHash', '');
        $this->RegisterAttributeInteger('OrderVersion', 0);
        $this->RegisterAttributeInteger('LastNotificationLeadTime', 600);
        $this->RegisterAttributeString('SortMode', 'created');
        $this->RegisterAttributeString('SortDir', 'desc');

        $this->RegisterTimer('NotificationTimer', 0, 'TDL_ProcessNotifications($_IPS[\'TARGET\']);');
        $this->RegisterTimer('RecurrenceTimer', 0, 'TDL_ProcessRecurrences($_IPS[\'TARGET\']);');

        $this->RegisterVariableInteger('OpenTasks', $this->Translate('Open Tasks'), '', 1);
        $this->RegisterVariableInteger('OverdueTasks', $this->Translate('Overdue'), '', 2);
        $this->RegisterVariableInteger('DueTodayTasks', $this->Translate('Due Today'), '', 3);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $leadTime = $this->ReadPropertyInteger('NotificationLeadTime');
        $lastLeadTime = $this->ReadAttributeInteger('LastNotificationLeadTime');
        if ($leadTime !== $lastLeadTime) {
            $this->ResetNotificationMarkers();
            $this->WriteAttributeInteger('LastNotificationLeadTime', $leadTime);
        }

        $visuID = $this->ReadPropertyInteger('VisualizationInstanceID');
        $this->SetTimerInterval('NotificationTimer', $visuID > 0 ? 60000 : 0);

        $this->UpdateRecurrenceTimer();

        $itemsTable = $this->ReadPropertyString('ItemsTable');
        $hash = md5($itemsTable);
        $lastHash = $this->ReadAttributeString('LastItemsTableHash');
        if ($hash !== $lastHash) {
            $this->SyncItemsFromConfiguration();
            $this->WriteAttributeString('LastItemsTableHash', $hash);
        }
        $this->UpdateStatistics();
        $this->SendState();

        $this->ProcessNotifications();
        $this->ProcessRecurrences();
    }

    public function GetConfigurationForm(): string
    {
        $this->WriteAttributeInteger('LastConfigFormRequest', time());
        $items = $this->LoadItems();
        $values = $this->BuildItemsTableValues($items);

        $form = [
            'elements' => [
                [
                    'type' => 'SelectInstance',
                    'name' => 'VisualizationInstanceID',
                    'width' => '400px',
                    'caption' => $this->Translate('Visualization instance to which the notification is sent')
                ],
                [
                    'type' => 'Select',
                    'name' => 'NotificationLeadTime',
                    'visible' => false,
                    'width' => '400px',
                    'caption' => $this->Translate('Notification Lead Time'),
                    'options' => [
                        ['caption' => $this->Translate('0 minutes'), 'value' => 0],
                        ['caption' => $this->Translate('5 minutes'), 'value' => 300],
                        ['caption' => $this->Translate('10 minutes'), 'value' => 600],
                        ['caption' => $this->Translate('30 minutes'), 'value' => 1800],
                        ['caption' => $this->Translate('1 hour'), 'value' => 3600],
                        ['caption' => $this->Translate('5 hours'), 'value' => 18000],
                        ['caption' => $this->Translate('12 hours'), 'value' => 43200]
                    ]
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'ShowOverview',
                    'caption' => $this->Translate('Show overview')
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'ShowCreateButton',
                    'caption' => $this->Translate('Show create button')
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'ShowSorting',
                    'caption' => $this->Translate('Show sorting')
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'UseGridView',
                    'caption' => $this->Translate('Use grid view'),
                    'visible' => false
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'ShowLargeQuantity',
                    'caption' => $this->Translate('Show large quantity'),
                    'visible' => false
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'GridShoppingListMode',
                    'caption' => $this->Translate('Grid shopping list mode'),
                    'visible' => false
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'ShowInfoBadges',
                    'caption' => $this->Translate('Show info badges')
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'ShowDeleteButton',
                    'caption' => $this->Translate('Show delete button')
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'ShowEditButton',
                    'caption' => $this->Translate('Show edit button')
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'HideCompletedTasks',
                    'caption' => $this->Translate('Hide completed tasks')
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'DeleteCompletedTasks',
                    'caption' => $this->Translate('Delete completed tasks')
                ],
                [
                    'type'  => 'List',
                    'name'  => 'ItemsTable',
                    'caption' => $this->Translate('Items'),
                    'rowCount' => 10,
                    'changeOrder' => true,
                    'add' => true,
                    'delete' => true,
                    'columns' => [
                        [
                            'caption' => $this->Translate('ID'),
                            'name' => 'id',
                            'width' => '60px',
                            'add' => 0,
                            'save' => true
                        ],
                        [
                            'caption' => $this->Translate('Done'),
                            'name' => 'done',
                            'width' => '90px',
                            'add' => false,
                            'edit' => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'caption' => $this->Translate('Title'),
                            'name' => 'title',
                            'width' => '350px',
                            'add' => '',
                            'edit' => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'caption' => $this->Translate('Info'),
                            'name' => 'info',
                            'width' => 'auto',
                            'add' => '',
                            'edit' => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'caption' => $this->Translate('Notification'),
                            'name' => 'notification',
                            'width' => '120px',
                            'visible' => false,
                            'add' => false,
                            'edit' => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'caption' => $this->Translate('Notification Lead Time'),
                            'name' => 'notificationLeadTime',
                            'width' => '200px',
                            'visible' => false,
                            'add' => 0,
                            'edit' => [
                                'type' => 'Select',
                                'options' => [
                                    ['caption' => $this->Translate('0 minutes'), 'value' => 0],
                                    ['caption' => $this->Translate('5 minutes'), 'value' => 300],
                                    ['caption' => $this->Translate('10 minutes'), 'value' => 600],
                                    ['caption' => $this->Translate('30 minutes'), 'value' => 1800],
                                    ['caption' => $this->Translate('1 hour'), 'value' => 3600],
                                    ['caption' => $this->Translate('5 hours'), 'value' => 18000],
                                    ['caption' => $this->Translate('12 hours'), 'value' => 43200]
                                ]
                            ]
                        ],
                        [
                            'caption' => $this->Translate('Quantity'),
                            'name' => 'quantity',
                            'width' => '90px',
                            'visible' => false,
                            'add' => 0,
                            'edit' => [
                                'type' => 'NumberSpinner',
                                'minimum' => 0
                            ]
                        ],
                        [
                            'caption' => $this->Translate('Due'),
                            'name' => 'due',
                            'width' => '140px',
                            'visible' => false,
                            'add' => json_encode($this->EmptySelectDateTime()),
                            'edit' => [
                                'type' => 'SelectDateTime'
                            ]
                        ],
                        [
                            'caption' => $this->Translate('Repeat'),
                            'name' => 'recurrence',
                            'width' => '140px',
                            'visible' => false,
                            'add' => 'none',
                            'edit' => [
                                'type' => 'Select',
                                'options' => [
                                    ['caption' => $this->Translate('No repeat'), 'value' => 'none'],
                                    ['caption' => $this->Translate('Custom'), 'value' => 'custom'],
                                    ['caption' => $this->Translate('Every week'), 'value' => 'w1'],
                                    ['caption' => $this->Translate('Every 2 weeks'), 'value' => 'w2'],
                                    ['caption' => $this->Translate('Every 3 weeks'), 'value' => 'w3'],
                                    ['caption' => $this->Translate('Monthly'), 'value' => 'm1'],
                                    ['caption' => $this->Translate('Quarterly'), 'value' => 'q1'],
                                    ['caption' => $this->Translate('Yearly'), 'value' => 'y1']
                                ]
                            ]
                        ],
                        [
                            'caption' => $this->Translate('Unit'),
                            'name' => 'recurrenceCustomUnit',
                            'width' => '120px',
                            'visible' => false,
                            'add' => 'w',
                            'edit' => [
                                'type' => 'Select',
                                'options' => [
                                    ['caption' => $this->Translate('Hours'), 'value' => 'h'],
                                    ['caption' => $this->Translate('Days'), 'value' => 'd'],
                                    ['caption' => $this->Translate('Weeks'), 'value' => 'w'],
                                    ['caption' => $this->Translate('Months'), 'value' => 'm'],
                                    ['caption' => $this->Translate('Years'), 'value' => 'y']
                                ]
                            ]
                        ],
                        [
                            'caption' => $this->Translate('Interval'),
                            'name' => 'recurrenceCustomValue',
                            'width' => '90px',
                            'visible' => false,
                            'add' => 1,
                            'edit' => [
                                'type' => 'NumberSpinner',
                                'minimum' => 1
                            ]
                        ],
                        [
                            'caption' => $this->Translate('Reopen'),
                            'name' => 'recurrenceResetLeadTime',
                            'width' => '140px',
                            'visible' => false,
                            'add' => 172800,
                            'edit' => [
                                'type' => 'Select',
                                'options' => [
                                    ['caption' => $this->Translate('Disabled'), 'value' => 0],
                                    ['caption' => $this->Translate('30 minutes'), 'value' => 1800],
                                    ['caption' => $this->Translate('1 hour'), 'value' => 3600],
                                    ['caption' => $this->Translate('6 hours'), 'value' => 21600],
                                    ['caption' => $this->Translate('12 hours'), 'value' => 43200],
                                    ['caption' => $this->Translate('1 day before'), 'value' => 86400],
                                    ['caption' => $this->Translate('2 days before'), 'value' => 172800],
                                    ['caption' => $this->Translate('3 days before'), 'value' => 259200],
                                    ['caption' => $this->Translate('1 week before'), 'value' => 604800],
                                    ['caption' => $this->Translate('2 weeks before'), 'value' => 1209600],
                                    ['caption' => $this->Translate('1 month before'), 'value' => 2592000]
                                ]
                            ]
                        ],
                        [
                            'caption' => $this->Translate('Priority'),
                            'name' => 'priority',
                            'width' => '120px',
                            'visible' => false,
                            'add' => 'normal',
                            'edit' => [
                                'type' => 'Select',
                                'options' => [
                                    ['caption' => $this->Translate('Low'), 'value' => 'low'],
                                    ['caption' => $this->Translate('Normal'), 'value' => 'normal'],
                                    ['caption' => $this->Translate('High'), 'value' => 'high']
                                ]
                            ]
                        ]
                    ],
                    'form' => $this->GetItemsTableEditFormScript(),
                    'values' => $values,
                    'loadValuesFromConfiguration' => false
                ],
            ]
        ];

        return json_encode($form);
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'GetState':
                $this->SendState();
                return;
            case 'SetSortPrefs':
                $this->SetSortPrefs($this->DecodeValue($Value));
                $this->SendState();
                return;
            case 'AddItem':
                $this->AddItem($this->DecodeValue($Value));
                $this->SendState();
                return;
            case 'UpdateItem':
                $this->UpdateItem($this->DecodeValue($Value));
                $this->SendState();
                return;
            case 'ToggleDone':
                $this->ToggleDone($this->DecodeValue($Value));
                $this->SendState();
                return;
            case 'DeleteItem':
                $this->DeleteItem($this->DecodeValue($Value));
                $this->SendState();
                return;
            case 'Reorder':
                $this->Reorder($this->DecodeValue($Value));
                $this->SendState();
                return;
            case 'ItemsTableRecurrenceChanged':
                $this->UpdateItemsTableRecurrenceVisibility($Value);
                return;
            default:
                throw new Exception($this->Translate('Invalid Ident'));
        }
    }

    public function GetVisualizationTile(): string
    {
        $path = __DIR__ . '/module.html';
        $html = @file_get_contents($path);
        if (!is_string($html)) {
            $exists = file_exists($path) ? 'yes' : 'no';
            $readable = is_readable($path) ? 'yes' : 'no';
            $size = file_exists($path) ? (string)@filesize($path) : 'n/a';
            $err = error_get_last();
            $errMsg = is_array($err) && isset($err['message']) ? (string)$err['message'] : '';
            IPS_LogMessage('ToDoList', 'GetVisualizationTile: module.html could not be loaded. path=' . $path . ' exists=' . $exists . ' readable=' . $readable . ' size=' . $size . ' err=' . $errMsg);
            return '';
        }
        if (strlen($html) < 200) {
            IPS_LogMessage('ToDoList', 'GetVisualizationTile: module.html loaded but is very short. bytes=' . strlen($html) . ' head=' . substr($html, 0, 80));
        }
        return $html;
    }

    public function DebugRecurrence(): string
    {
        $items = $this->LoadItems();
        $now = time();
        $interval = 60;
        $timerInterval = $this->GetTimerInterval('RecurrenceTimer');

        $debug = [
            'now' => date('Y-m-d H:i:s', $now),
            'nowTs' => $now,
            'timerActive' => $timerInterval > 0,
            'timerInterval' => $timerInterval,
            'items' => []
        ];

        foreach ($items as $item) {
            $due = (int)($item['due'] ?? 0);
            $recurrence = $this->NormalizeRecurrence($item['recurrence'] ?? 'none', $due);
            $leadTime = $this->NormalizeRecurrenceResetLeadTime($item['recurrenceResetLeadTime'] ?? null, $recurrence);
            $windowStart = $leadTime - $interval;
            $left = $due - $now;

            $wouldReopen = false;
            $reason = '';

            if (empty($item['done'])) {
                $reason = 'Task ist nicht erledigt (done=false)';
            } elseif ($due <= 0) {
                $reason = 'Keine Fälligkeit gesetzt (due=0)';
            } elseif ($recurrence === 'none') {
                $reason = 'Keine Wiederholung gesetzt (recurrence=none)';
            } elseif ($leadTime <= 0) {
                $reason = 'Wieder öffnen deaktiviert (recurrenceResetLeadTime=0)';
            } elseif ($left > $leadTime) {
                $reason = 'Noch nicht im Fenster (left > leadTime): ' . $left . ' > ' . $leadTime;
            } elseif ($left < $windowStart) {
                $reason = 'Fenster verpasst (left < windowStart): ' . $left . ' < ' . $windowStart . ' -> Due wird weitergeschoben';
            } else {
                $wouldReopen = true;
                $reason = 'WÜRDE JETZT WIEDER GEÖFFNET (left=' . $left . ', Fenster=' . $windowStart . '-' . $leadTime . ')';
            }

            $debug['items'][] = [
                'id' => $item['id'] ?? 0,
                'title' => $item['title'] ?? '',
                'done' => $item['done'] ?? false,
                'due' => $due > 0 ? date('Y-m-d H:i:s', $due) : 'nicht gesetzt',
                'dueTs' => $due,
                'recurrence' => $item['recurrence'] ?? 'none',
                'recurrenceNormalized' => $recurrence,
                'recurrenceResetLeadTime' => $item['recurrenceResetLeadTime'] ?? 'nicht gesetzt',
                'recurrenceResetLeadTimeNormalized' => $leadTime,
                'left' => $left,
                'windowStart' => $windowStart,
                'wouldReopen' => $wouldReopen,
                'reason' => $reason
            ];
        }

        return json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function AddItem(array $Item): int
    {
        $items = $this->LoadItems();

        $title = trim((string)($Item['title'] ?? ''));
        if ($title === '') {
            throw new Exception($this->Translate('Invalid title'));
        }

        $id = $this->ReadAttributeInteger('NextID');
        $this->WriteAttributeInteger('NextID', $id + 1);

        $now = time();
        $due = (int)($Item['due'] ?? 0);
        $recurrence = $this->NormalizeRecurrence($Item['recurrence'] ?? 'none', $due);
        $recurrenceResetLeadTime = $this->NormalizeRecurrenceResetLeadTime($Item['recurrenceResetLeadTime'] ?? 0, $recurrence);
        $recurrenceCustomUnit = 'w';
        $recurrenceCustomValue = 1;
        if ($recurrence === 'custom') {
            $recurrenceCustomUnit = $this->NormalizeRecurrenceCustomUnit($Item['recurrenceCustomUnit'] ?? null);
            $recurrenceCustomValue = $this->NormalizeRecurrenceCustomValue($Item['recurrenceCustomValue'] ?? null);
        }
        $notification = (bool)($Item['notification'] ?? false);
        if ($due <= 0) {
            $notification = false;
            $recurrence = 'none';
            $recurrenceResetLeadTime = 0;
        }

        $defaultLeadTime = $this->NormalizeNotificationLeadTimeDefault((int)$this->ReadPropertyInteger('NotificationLeadTime'));
        $itemLeadTime = $defaultLeadTime;
        if (array_key_exists('notificationLeadTime', $Item)) {
            $itemLeadTime = $this->NormalizeNotificationLeadTime($Item['notificationLeadTime'], $defaultLeadTime);
        }

        if ($due > 0) {
            $limit = $this->GetLeadTimeLimitSeconds($due, $now, $recurrence, $recurrenceCustomUnit, $recurrenceCustomValue);
            $itemLeadTime = $this->ClampLeadTimeToLimit($itemLeadTime, $limit, [0, 300, 600, 1800, 3600, 18000, 43200]);
        }

        if ($due > 0 && $recurrence !== 'none') {
            $interval = $this->GetRecurrenceIntervalSeconds($due, $recurrence, $recurrenceCustomUnit, $recurrenceCustomValue);
            $recurrenceResetLeadTime = $this->ClampLeadTimeToInterval($recurrenceResetLeadTime, $interval, [1800, 3600, 21600, 43200, 86400, 172800, 259200, 604800, 1209600, 2592000]);
        }

        $newItem = [
            'id'        => $id,
            'title'     => $title,
            'info'      => (string)($Item['info'] ?? ''),
            'done'      => (bool)($Item['done'] ?? false),
            'due'       => $due,
            'recurrence' => $recurrence,
            'recurrenceCustomUnit' => $recurrenceCustomUnit,
            'recurrenceCustomValue' => $recurrenceCustomValue,
            'recurrenceResetLeadTime' => $recurrenceResetLeadTime,
            'priority'  => (string)($Item['priority'] ?? 'normal'),
            'quantity'  => (int)($Item['quantity'] ?? 0),
            'notification' => $notification,
            'notificationLeadTime' => $itemLeadTime,
            'notifiedFor'  => 0,
            'createdAt' => $now,
            'updatedAt' => $now
        ];

        array_unshift($items, $newItem);
        $this->SaveItems($items);

        return $id;
    }

    public function UpdateItem(array $Data): void
    {
        $id = (int)($Data['id'] ?? 0);
        if ($id <= 0) {
            throw new Exception($this->Translate('Invalid id'));
        }

        $items = $this->LoadItems();
        $now = time();
        $deleteCompleted = $this->ReadPropertyBoolean('DeleteCompletedTasks');
        for ($i = 0; $i < count($items); $i++) {
            if (((int)($items[$i]['id'] ?? 0)) !== $id) {
                continue;
            }

            if ($deleteCompleted && array_key_exists('done', $Data) && (bool)$Data['done']) {
                unset($items[$i]);
                $this->SaveItems(array_values($items));
                return;
            }

            $resetNotify = false;
            if (array_key_exists('title', $Data)) {
                $items[$i]['title'] = trim((string)$Data['title']);
            }
            if (array_key_exists('info', $Data)) {
                $items[$i]['info'] = (string)$Data['info'];
            }
            if (array_key_exists('due', $Data)) {
                $resetNotify = $resetNotify || ((int)($items[$i]['due'] ?? 0) !== (int)$Data['due']);
                $items[$i]['due'] = (int)$Data['due'];
            }

            if (array_key_exists('recurrence', $Data) || array_key_exists('due', $Data)) {
                $due = (int)($items[$i]['due'] ?? 0);
                $items[$i]['recurrence'] = $this->NormalizeRecurrence($Data['recurrence'] ?? ($items[$i]['recurrence'] ?? 'none'), $due);
            } elseif (!array_key_exists('recurrence', $items[$i])) {
                $items[$i]['recurrence'] = 'none';
            }

            if (array_key_exists('recurrenceCustomUnit', $Data) || array_key_exists('recurrenceCustomValue', $Data) || array_key_exists('recurrence', $Data) || array_key_exists('due', $Data)) {
                if ((string)($items[$i]['recurrence'] ?? 'none') === 'custom') {
                    $items[$i]['recurrenceCustomUnit'] = $this->NormalizeRecurrenceCustomUnit($Data['recurrenceCustomUnit'] ?? ($items[$i]['recurrenceCustomUnit'] ?? null));
                    $items[$i]['recurrenceCustomValue'] = $this->NormalizeRecurrenceCustomValue($Data['recurrenceCustomValue'] ?? ($items[$i]['recurrenceCustomValue'] ?? null));
                } else {
                    $items[$i]['recurrenceCustomUnit'] = 'w';
                    $items[$i]['recurrenceCustomValue'] = 1;
                }
            } elseif (!array_key_exists('recurrenceCustomUnit', $items[$i])) {
                $items[$i]['recurrenceCustomUnit'] = 'w';
                $items[$i]['recurrenceCustomValue'] = 1;
            }

            if (array_key_exists('recurrenceResetLeadTime', $Data) || array_key_exists('recurrence', $Data) || array_key_exists('due', $Data)) {
                $rec = (string)($items[$i]['recurrence'] ?? 'none');
                $items[$i]['recurrenceResetLeadTime'] = $this->NormalizeRecurrenceResetLeadTime($Data['recurrenceResetLeadTime'] ?? ($items[$i]['recurrenceResetLeadTime'] ?? null), $rec);
            } elseif (!array_key_exists('recurrenceResetLeadTime', $items[$i])) {
                $items[$i]['recurrenceResetLeadTime'] = 0;
            }
            if (array_key_exists('priority', $Data)) {
                $items[$i]['priority'] = (string)$Data['priority'];
            }
            if (array_key_exists('done', $Data)) {
                $items[$i]['done'] = (bool)$Data['done'];
            }
            if (array_key_exists('quantity', $Data)) {
                $items[$i]['quantity'] = (int)$Data['quantity'];
            }
            if (array_key_exists('notification', $Data)) {
                $resetNotify = $resetNotify || ((bool)($items[$i]['notification'] ?? false) !== (bool)$Data['notification']);
                $items[$i]['notification'] = (bool)$Data['notification'];
            }

            $defaultLeadTime = $this->NormalizeNotificationLeadTimeDefault((int)$this->ReadPropertyInteger('NotificationLeadTime'));
            if (array_key_exists('notificationLeadTime', $Data) || array_key_exists('notificationLeadTime', $items[$i])) {
                $currentStored = (int)($items[$i]['notificationLeadTime'] ?? $defaultLeadTime);
                $newLeadTime = $Data['notificationLeadTime'] ?? ($items[$i]['notificationLeadTime'] ?? $defaultLeadTime);
                $newLeadTime = $this->NormalizeNotificationLeadTime($newLeadTime, $defaultLeadTime);
                $resetNotify = $resetNotify || ($currentStored !== $newLeadTime);
                $items[$i]['notificationLeadTime'] = $newLeadTime;
            }

            $due = (int)($items[$i]['due'] ?? 0);
            $recurrence = (string)($items[$i]['recurrence'] ?? 'none');
            if ($due > 0 && $recurrence !== 'none') {
                $unit = (string)($items[$i]['recurrenceCustomUnit'] ?? 'w');
                $val = (int)($items[$i]['recurrenceCustomValue'] ?? 1);
                $interval = $this->GetRecurrenceIntervalSeconds($due, $recurrence, $unit, $val);
                $newReopen = $this->ClampLeadTimeToInterval((int)($items[$i]['recurrenceResetLeadTime'] ?? 0), $interval, [1800, 3600, 21600, 43200, 86400, 172800, 259200, 604800, 1209600, 2592000]);
                $items[$i]['recurrenceResetLeadTime'] = $newReopen;
            }

            if ($due > 0 && array_key_exists('notificationLeadTime', $items[$i])) {
                $unit = (string)($items[$i]['recurrenceCustomUnit'] ?? 'w');
                $val = (int)($items[$i]['recurrenceCustomValue'] ?? 1);
                $limit = $this->GetLeadTimeLimitSeconds($due, $now, $recurrence, $unit, $val);
                $newLeadTime = $this->ClampLeadTimeToLimit((int)$items[$i]['notificationLeadTime'], $limit, [0, 300, 600, 1800, 3600, 18000, 43200]);
                if ((int)$items[$i]['notificationLeadTime'] !== $newLeadTime) {
                    $resetNotify = true;
                    $items[$i]['notificationLeadTime'] = $newLeadTime;
                }
            }

            if (((int)($items[$i]['due'] ?? 0)) <= 0) {
                $items[$i]['notification'] = false;
                $resetNotify = true;
                $items[$i]['recurrence'] = 'none';
                $items[$i]['recurrenceResetLeadTime'] = 0;
                $items[$i]['recurrenceCustomUnit'] = 'w';
                $items[$i]['recurrenceCustomValue'] = 1;
            }

            if ($resetNotify) {
                $items[$i]['notifiedFor'] = 0;
            }

            $items[$i]['updatedAt'] = $now;
            break;
        }

        $this->SaveItems($items);
    }

    public function ToggleDone(array $Data): void
    {
        $id = (int)($Data['id'] ?? 0);
        if ($id <= 0) {
            throw new Exception($this->Translate('Invalid id'));
        }

        $items = $this->LoadItems();
        $deleteCompleted = $this->ReadPropertyBoolean('DeleteCompletedTasks');
        for ($i = 0; $i < count($items); $i++) {
            if (((int)($items[$i]['id'] ?? 0)) !== $id) {
                continue;
            }

            $oldDone = (bool)($items[$i]['done'] ?? false);
            $newDone = $oldDone;
            if (array_key_exists('done', $Data)) {
                $newDone = (bool)$Data['done'];
            } else {
                $newDone = !$oldDone;
            }

            $recurrence = (string)($items[$i]['recurrence'] ?? 'none');
            if ($newDone && $deleteCompleted && $this->NormalizeRecurrence($recurrence, (int)($items[$i]['due'] ?? 0)) === 'none') {
                unset($items[$i]);
                $this->SaveItems(array_values($items));
                return;
            }

            $items[$i]['done'] = $newDone;
            if ($newDone && $recurrence !== 'none') {
                $due = (int)($items[$i]['due'] ?? 0);
                if ($due > 0) {
                    $unit = (string)($items[$i]['recurrenceCustomUnit'] ?? 'w');
                    $val = (int)($items[$i]['recurrenceCustomValue'] ?? 1);
                    $items[$i]['due'] = $this->GetNextDue($due, $recurrence, $unit, $val);
                    $items[$i]['notifiedFor'] = 0;
                }

                if ((int)($items[$i]['recurrenceResetLeadTime'] ?? 0) === -1) {
                    $items[$i]['done'] = false;
                    $items[$i]['notifiedFor'] = 0;
                }
            }
            if ($oldDone !== $newDone) {
                $items[$i]['notifiedFor'] = 0;
            }
            $items[$i]['updatedAt'] = time();
            break;
        }

        $this->SaveItems($items);
    }

    public function DeleteItem(array $Data): void
    {
        $id = (int)($Data['id'] ?? 0);
        if ($id <= 0) {
            throw new Exception($this->Translate('Invalid id'));
        }

        $items = array_values(array_filter($this->LoadItems(), fn($it) => (int)($it['id'] ?? 0) !== $id));
        $this->SaveItems($items);
    }

    public function Reorder(array $Data): void
    {
        $order = $Data['order'] ?? [];
        if (!is_array($order)) {
            throw new Exception($this->Translate('Invalid order'));
        }

        $items = $this->LoadItems();
        $beforeIds = array_map(fn($it) => (int)($it['id'] ?? 0), $items);
        $map = [];
        foreach ($items as $it) {
            $map[(int)($it['id'] ?? 0)] = $it;
        }

        $newItems = [];
        foreach ($order as $id) {
            $id = (int)$id;
            if (isset($map[$id])) {
                $newItems[] = $map[$id];
                unset($map[$id]);
            }
        }

        foreach ($items as $it) {
            $id = (int)($it['id'] ?? 0);
            if (isset($map[$id])) {
                $newItems[] = $map[$id];
                unset($map[$id]);
            }
        }

        $afterIds = array_map(fn($it) => (int)($it['id'] ?? 0), $newItems);
        if ($beforeIds !== $afterIds) {
            $this->WriteAttributeInteger('OrderVersion', $this->ReadAttributeInteger('OrderVersion') + 1);
            $this->WriteAttributeString('SortMode', 'manual');
        }
        $this->SaveItems($newItems);
    }

    private function SyncItemsFromConfiguration(): void
    {
        $last = $this->ReadAttributeInteger('LastConfigFormRequest');
        if ($last <= 0 || (time() - $last) > 3600) {
            return;
        }

        $rows = json_decode($this->ReadPropertyString('ItemsTable'), true);
        if (!is_array($rows)) {
            return;
        }

        $itemsBefore = $this->LoadItems();
        $beforeIds = array_map(fn($it) => (int)($it['id'] ?? 0), $itemsBefore);

        $existing = [];
        foreach ($itemsBefore as $it) {
            $existing[(int)($it['id'] ?? 0)] = $it;
        }

        $nextID = $this->ReadAttributeInteger('NextID');
        $now = time();
        $items = [];
        $newItems = [];
        $deleteCompleted = $this->ReadPropertyBoolean('DeleteCompletedTasks');

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if ($deleteCompleted && !empty($row['done'])) {
                continue;
            }

            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                $id = $nextID++;
            } else {
                $nextID = max($nextID, $id + 1);
            }

            $title = trim((string)($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $prio = (string)($row['priority'] ?? 'normal');
            if (!in_array($prio, ['low', 'normal', 'high'], true)) {
                $prio = 'normal';
            }

            $createdAt = $now;
            if (isset($existing[$id]) && isset($existing[$id]['createdAt'])) {
                $createdAt = (int)$existing[$id]['createdAt'];
            }

            $old = $existing[$id] ?? [];
            $dueTs = $this->SelectDateTimeToTimestamp($row['due'] ?? null);
            $recurrence = $this->NormalizeRecurrence($row['recurrence'] ?? ($old['recurrence'] ?? 'none'), $dueTs);
            $recurrenceCustomUnit = (string)($old['recurrenceCustomUnit'] ?? 'w');
            $recurrenceCustomValue = (int)($old['recurrenceCustomValue'] ?? 1);
            if ($recurrence === 'custom') {
                $recurrenceCustomUnit = $this->NormalizeRecurrenceCustomUnit($row['recurrenceCustomUnit'] ?? $recurrenceCustomUnit);
                $recurrenceCustomValue = $this->NormalizeRecurrenceCustomValue($row['recurrenceCustomValue'] ?? $recurrenceCustomValue);
            }
            $notification = (bool)($row['notification'] ?? false);
            $defaultLeadTime = $this->NormalizeNotificationLeadTimeDefault((int)$this->ReadPropertyInteger('NotificationLeadTime'));
            $notificationLeadTime = (int)($old['notificationLeadTime'] ?? $defaultLeadTime);
            if ($notification && array_key_exists('notificationLeadTime', $row) && is_numeric($row['notificationLeadTime'])) {
                $notificationLeadTime = $this->NormalizeNotificationLeadTime($row['notificationLeadTime'], $defaultLeadTime);
            } else {
                $notificationLeadTime = $this->NormalizeNotificationLeadTime($notificationLeadTime, $defaultLeadTime);
            }

            $recurrenceResetLeadTime = $row['recurrenceResetLeadTime'] ?? ($old['recurrenceResetLeadTime'] ?? null);
            $recurrenceResetLeadTime = $this->NormalizeRecurrenceResetLeadTime($recurrenceResetLeadTime, $recurrence);
            $notifiedFor = (int)($old['notifiedFor'] ?? 0);
            if ((int)($old['due'] ?? 0) !== $dueTs || (bool)($old['notification'] ?? false) !== $notification || (int)($old['notificationLeadTime'] ?? $notificationLeadTime) !== $notificationLeadTime) {
                $notifiedFor = 0;
            }

            if ($dueTs > 0) {
                $limit = $this->GetLeadTimeLimitSeconds($dueTs, $now, $recurrence, $recurrenceCustomUnit, $recurrenceCustomValue);
                $notificationLeadTime = $this->ClampLeadTimeToLimit($notificationLeadTime, $limit, [0, 300, 600, 1800, 3600, 18000, 43200]);
            }

            if ($dueTs > 0 && $recurrence !== 'none') {
                $interval = $this->GetRecurrenceIntervalSeconds($dueTs, $recurrence, $recurrenceCustomUnit, $recurrenceCustomValue);
                $recurrenceResetLeadTime = $this->ClampLeadTimeToInterval($recurrenceResetLeadTime, $interval, [1800, 3600, 21600, 43200, 86400, 172800, 259200, 604800, 1209600, 2592000]);
            }

            if ($dueTs <= 0) {
                $notification = false;
                $recurrence = 'none';
                $recurrenceResetLeadTime = 0;
                $recurrenceCustomUnit = 'w';
                $recurrenceCustomValue = 1;
                $notifiedFor = 0;
            }

            $items[] = [
                'id'        => $id,
                'title'     => $title,
                'info'      => (string)($row['info'] ?? ''),
                'done'      => (bool)($row['done'] ?? false),
                'quantity'  => (int)($row['quantity'] ?? 0),
                'notification' => $notification,
                'notificationLeadTime' => $notificationLeadTime,
                'notifiedFor'  => $notifiedFor,
                'due'       => $dueTs,
                'recurrence' => $recurrence,
                'recurrenceCustomUnit' => $recurrenceCustomUnit,
                'recurrenceCustomValue' => $recurrenceCustomValue,
                'recurrenceResetLeadTime' => $recurrenceResetLeadTime,
                'priority'  => $prio,
                'createdAt' => $createdAt,
                'updatedAt' => $now
            ];
        }

        foreach ($items as $it) {
            $id = (int)($it['id'] ?? 0);
            if (isset($existing[$id])) {
                continue;
            }
            $newItems[] = $it;
        }
        if (count($newItems) > 0) {
            $existingItems = array_values(array_filter($items, fn($it) => isset($existing[(int)($it['id'] ?? 0)])));
            $items = array_merge($newItems, $existingItems);
        }

        $afterIds = array_map(fn($it) => (int)($it['id'] ?? 0), $items);
        $beforeSet = $beforeIds;
        $afterSet = $afterIds;
        sort($beforeSet);
        sort($afterSet);
        if ($beforeSet === $afterSet && $beforeIds !== $afterIds) {
            $this->WriteAttributeInteger('OrderVersion', $this->ReadAttributeInteger('OrderVersion') + 1);
        }

        $this->WriteAttributeInteger('NextID', $nextID);
        $this->SaveItems($items);
    }

    private function DecodeValue(mixed $Value): array
    {
        if (is_array($Value)) {
            return $Value;
        }

        if (is_string($Value)) {
            $decoded = json_decode($Value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function EmptySelectDateTime(): array
    {
        return [
            'year'   => 0,
            'month'  => 0,
            'day'    => 0,
            'hour'   => 0,
            'minute' => 0,
            'second' => 0
        ];
    }

    private function TimestampToSelectDateTime(int $Timestamp): array
    {
        if ($Timestamp <= 0) {
            return $this->EmptySelectDateTime();
        }

        return [
            'year'   => (int)date('Y', $Timestamp),
            'month'  => (int)date('n', $Timestamp),
            'day'    => (int)date('j', $Timestamp),
            'hour'   => (int)date('G', $Timestamp),
            'minute' => (int)date('i', $Timestamp),
            'second' => (int)date('s', $Timestamp)
        ];
    }

    private function SelectDateTimeToTimestamp(mixed $Value): int
    {
        if (is_string($Value)) {
            $decoded = json_decode($Value, true);
            if (is_array($decoded)) {
                $Value = $decoded;
            }
        }

        if (is_array($Value)) {
            $year = (int)($Value['year'] ?? 0);
            $month = (int)($Value['month'] ?? 0);
            $day = (int)($Value['day'] ?? 0);
            $hour = (int)($Value['hour'] ?? 0);
            $minute = (int)($Value['minute'] ?? 0);
            $second = (int)($Value['second'] ?? 0);

            if ($year <= 0 || $month <= 0 || $day <= 0) {
                return 0;
            }

            return (int)mktime($hour, $minute, $second, $month, $day, $year);
        }

        if (is_int($Value)) {
            return $Value;
        }

        return 0;
    }

    private function LoadItems(): array
    {
        $data = json_decode($this->ReadAttributeString('Items'), true);
        if (!is_array($data)) {
            return [];
        }

        $items = [];
        foreach ($data as $item) {
            if (is_array($item)) {
                $items[] = $item;
            }
        }
        unset($item);

        return $items;
    }

    private function SaveItems(array $Items): void
    {
        $this->WriteAttributeString('Items', json_encode($Items));
        $this->UpdateStatistics();
        $this->UpdateRecurrenceTimer($Items);
    }

    private function SetSortPrefs(array $Data): void
    {
        $mode = (string)($Data['mode'] ?? '');
        $dir = (string)($Data['dir'] ?? '');

        $allowedModes = ['manual', 'created', 'due', 'priority', 'title'];
        if (!in_array($mode, $allowedModes, true)) {
            $mode = 'created';
        }
        if ($dir !== 'asc' && $dir !== 'desc') {
            $dir = 'desc';
        }

        $this->WriteAttributeString('SortMode', $mode);
        $this->WriteAttributeString('SortDir', $dir);
    }

    private function GetSortPrefs(): array
    {
        $mode = (string)$this->ReadAttributeString('SortMode');
        $dir = (string)$this->ReadAttributeString('SortDir');

        $allowedModes = ['manual', 'created', 'due', 'priority', 'title'];
        if (!in_array($mode, $allowedModes, true)) {
            $mode = 'created';
        }
        if ($dir !== 'asc' && $dir !== 'desc') {
            $dir = 'desc';
        }

        return ['mode' => $mode, 'dir' => $dir];
    }

    private function BuildItemsTableValues(array $Items): array
    {
        $values = [];
        foreach ($Items as $it) {
            $due = (int)($it['due'] ?? 0);
            $notification = (bool)($it['notification'] ?? false);
            $values[] = [
                'id'       => (int)($it['id'] ?? 0),
                'done'     => (bool)($it['done'] ?? false),
                'title'    => (string)($it['title'] ?? ''),
                'info'     => (string)($it['info'] ?? ''),
                'notification' => $notification,
                'notificationLeadTime' => (int)($it['notificationLeadTime'] ?? 0),
                'quantity' => (int)($it['quantity'] ?? 0),
                'due'      => json_encode($this->TimestampToSelectDateTime($due)),
                'recurrence' => (string)($it['recurrence'] ?? 'none'),
                'recurrenceCustomUnit' => (string)($it['recurrenceCustomUnit'] ?? 'w'),
                'recurrenceCustomValue' => (int)($it['recurrenceCustomValue'] ?? 1),
                'recurrenceResetLeadTime' => (int)($it['recurrenceResetLeadTime'] ?? 172800),
                'priority' => (string)($it['priority'] ?? 'normal')
            ];
        }
        return $values;
    }

    private function GetItemsTableEditFormScript(): string
    {
        $tID = json_encode($this->Translate('ID'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tDone = json_encode($this->Translate('Done'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tTitle = json_encode($this->Translate('Title'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tInfo = json_encode($this->Translate('Info'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tNotification = json_encode($this->Translate('Notification'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tNotificationLeadTime = json_encode($this->Translate('Notification Lead Time'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tQuantity = json_encode($this->Translate('Quantity'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tDue = json_encode($this->Translate('Due'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tRepeat = json_encode($this->Translate('Repeat'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tReopen = json_encode($this->Translate('Reopen'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tPriority = json_encode($this->Translate('Priority'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $tNoRepeat = json_encode($this->Translate('No repeat'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tCustom = json_encode($this->Translate('Custom'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tEveryWeek = json_encode($this->Translate('Every week'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tEvery2Weeks = json_encode($this->Translate('Every 2 weeks'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tEvery3Weeks = json_encode($this->Translate('Every 3 weeks'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tMonthly = json_encode($this->Translate('Monthly'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tQuarterly = json_encode($this->Translate('Quarterly'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tYearly = json_encode($this->Translate('Yearly'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $tUnit = json_encode($this->Translate('Unit'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tInterval = json_encode($this->Translate('Interval'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tHours = json_encode($this->Translate('Hours'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tDays = json_encode($this->Translate('Days'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tWeeks = json_encode($this->Translate('Weeks'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tMonths = json_encode($this->Translate('Months'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tYears = json_encode($this->Translate('Years'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $tDisabled = json_encode($this->Translate('Disabled'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tImmediate = json_encode($this->Translate('Immediate'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $t1DayBefore = json_encode($this->Translate('1 day before'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $t2DaysBefore = json_encode($this->Translate('2 days before'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $t3DaysBefore = json_encode($this->Translate('3 days before'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $t1WeekBefore = json_encode($this->Translate('1 week before'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $t2WeeksBefore = json_encode($this->Translate('2 weeks before'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $t1MonthBefore = json_encode($this->Translate('1 month before'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $tLow = json_encode($this->Translate('Low'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tNormal = json_encode($this->Translate('Normal'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tHigh = json_encode($this->Translate('High'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $t0Min = json_encode($this->Translate('0 minutes'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $t5Min = json_encode($this->Translate('5 minutes'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $t10Min = json_encode($this->Translate('10 minutes'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $t30Min = json_encode($this->Translate('30 minutes'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $t1H = json_encode($this->Translate('1 hour'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $t5H = json_encode($this->Translate('5 hours'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $t6H = json_encode($this->Translate('6 hours'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $t12H = json_encode($this->Translate('12 hours'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return '$recurrenceValue = (string)($ItemsTable[\'recurrence\'] ?? \'none\');' . PHP_EOL .
            '$showReopen = $recurrenceValue !== \'none\';' . PHP_EOL .
            '$showCustom = $recurrenceValue === \'custom\';' . PHP_EOL .
            'return [' . PHP_EOL .
            '  [\'type\' => \'NumberSpinner\', \'name\' => \'id\', \'caption\' => ' . $tID . ', \'visible\' => false, \'enabled\' => false],' . PHP_EOL .
            '  [\'type\' => \'CheckBox\', \'name\' => \'done\', \'caption\' => ' . $tDone . '],' . PHP_EOL .
            '  [\'type\' => \'ValidationTextBox\', \'name\' => \'title\', \'caption\' => ' . $tTitle . '],' . PHP_EOL .
            '  [\'type\' => \'ValidationTextBox\', \'name\' => \'info\', \'caption\' => ' . $tInfo . '],' . PHP_EOL .
            '  [\'type\' => \'NumberSpinner\', \'name\' => \'quantity\', \'caption\' => ' . $tQuantity . ', \'minimum\' => 0],' . PHP_EOL .
            '  [\'type\' => \'Select\', \'name\' => \'priority\', \'caption\' => ' . $tPriority . ', \'options\' => [' . PHP_EOL .
            '    [\'caption\' => ' . $tLow . ', \'value\' => \'low\'],' . PHP_EOL .
            '    [\'caption\' => ' . $tNormal . ', \'value\' => \'normal\'],' . PHP_EOL .
            '    [\'caption\' => ' . $tHigh . ', \'value\' => \'high\']' . PHP_EOL .
            '  ]],' . PHP_EOL .
            '  [\'type\' => \'SelectDateTime\', \'name\' => \'due\', \'caption\' => ' . $tDue . '],' . PHP_EOL .
            '  [\'type\' => \'Select\', \'name\' => \'recurrence\', \'caption\' => ' . $tRepeat . ', \'onChange\' => \'IPS_RequestAction(' . $this->InstanceID . ', "ItemsTableRecurrenceChanged", $recurrence);\', \'options\' => [' . PHP_EOL .
            '    [\'caption\' => ' . $tNoRepeat . ', \'value\' => \'none\'],' . PHP_EOL .
            '    [\'caption\' => ' . $tCustom . ', \'value\' => \'custom\'],' . PHP_EOL .
            '    [\'caption\' => ' . $tEveryWeek . ', \'value\' => \'w1\'],' . PHP_EOL .
            '    [\'caption\' => ' . $tEvery2Weeks . ', \'value\' => \'w2\'],' . PHP_EOL .
            '    [\'caption\' => ' . $tEvery3Weeks . ', \'value\' => \'w3\'],' . PHP_EOL .
            '    [\'caption\' => ' . $tMonthly . ', \'value\' => \'m1\'],' . PHP_EOL .
            '    [\'caption\' => ' . $tQuarterly . ', \'value\' => \'q1\'],' . PHP_EOL .
            '    [\'caption\' => ' . $tYearly . ', \'value\' => \'y1\']' . PHP_EOL .
            '  ]],' . PHP_EOL .
            '  [\'type\' => \'Select\', \'name\' => \'recurrenceCustomUnit\', \'caption\' => ' . $tUnit . ', \'visible\' => $showCustom, \'options\' => [' . PHP_EOL .
            '    [\'caption\' => ' . $tHours . ', \'value\' => \'h\'],' . PHP_EOL .
            '    [\'caption\' => ' . $tDays . ', \'value\' => \'d\'],' . PHP_EOL .
            '    [\'caption\' => ' . $tWeeks . ', \'value\' => \'w\'],' . PHP_EOL .
            '    [\'caption\' => ' . $tMonths . ', \'value\' => \'m\'],' . PHP_EOL .
            '    [\'caption\' => ' . $tYears . ', \'value\' => \'y\']' . PHP_EOL .
            '  ]],' . PHP_EOL .
            '  [\'type\' => \'NumberSpinner\', \'name\' => \'recurrenceCustomValue\', \'caption\' => ' . $tInterval . ', \'visible\' => $showCustom, \'minimum\' => 1],' . PHP_EOL .
            '  [\'type\' => \'Select\', \'name\' => \'recurrenceResetLeadTime\', \'caption\' => ' . $tReopen . ', \'visible\' => $showReopen, \'options\' => [' . PHP_EOL .
            '    [\'caption\' => ' . $tDisabled . ', \'value\' => 0],' . PHP_EOL .
            '    [\'caption\' => ' . $tImmediate . ', \'value\' => -1],' . PHP_EOL .
            '    [\'caption\' => ' . $t30Min . ', \'value\' => 1800],' . PHP_EOL .
            '    [\'caption\' => ' . $t1H . ', \'value\' => 3600],' . PHP_EOL .
            '    [\'caption\' => ' . $t6H . ', \'value\' => 21600],' . PHP_EOL .
            '    [\'caption\' => ' . $t12H . ', \'value\' => 43200],' . PHP_EOL .
            '    [\'caption\' => ' . $t1DayBefore . ', \'value\' => 86400],' . PHP_EOL .
            '    [\'caption\' => ' . $t2DaysBefore . ', \'value\' => 172800],' . PHP_EOL .
            '    [\'caption\' => ' . $t3DaysBefore . ', \'value\' => 259200],' . PHP_EOL .
            '    [\'caption\' => ' . $t1WeekBefore . ', \'value\' => 604800],' . PHP_EOL .
            '    [\'caption\' => ' . $t2WeeksBefore . ', \'value\' => 1209600],' . PHP_EOL .
            '    [\'caption\' => ' . $t1MonthBefore . ', \'value\' => 2592000]' . PHP_EOL .
            '  ]],' . PHP_EOL .
            '  [\'type\' => \'CheckBox\', \'name\' => \'notification\', \'caption\' => ' . $tNotification . '],' . PHP_EOL .
            '  [\'type\' => \'Select\', \'name\' => \'notificationLeadTime\', \'caption\' => ' . $tNotificationLeadTime . ', \'options\' => [' . PHP_EOL .
            '    [\'caption\' => ' . $t0Min . ', \'value\' => 0],' . PHP_EOL .
            '    [\'caption\' => ' . $t5Min . ', \'value\' => 300],' . PHP_EOL .
            '    [\'caption\' => ' . $t10Min . ', \'value\' => 600],' . PHP_EOL .
            '    [\'caption\' => ' . $t30Min . ', \'value\' => 1800],' . PHP_EOL .
            '    [\'caption\' => ' . $t1H . ', \'value\' => 3600],' . PHP_EOL .
            '    [\'caption\' => ' . $t5H . ', \'value\' => 18000],' . PHP_EOL .
            '    [\'caption\' => ' . $t12H . ', \'value\' => 43200]' . PHP_EOL .
            '  ]]' . PHP_EOL .
            '];';
    }

    private function UpdateItemsTableRecurrenceVisibility(mixed $RecurrenceValue): void
    {
        $recurrence = (string)$RecurrenceValue;
        $showReopen = $recurrence !== 'none';
        $showCustom = $recurrence === 'custom';
        $this->UpdateFormField('recurrenceResetLeadTime', 'visible', $showReopen);
        $this->UpdateFormField('recurrenceCustomUnit', 'visible', $showCustom);
        $this->UpdateFormField('recurrenceCustomValue', 'visible', $showCustom);
        if (!$showReopen) {
            $this->UpdateFormField('recurrenceResetLeadTime', 'value', 0);
        }
        if (!$showCustom) {
            $this->UpdateFormField('recurrenceCustomUnit', 'value', 'w');
            $this->UpdateFormField('recurrenceCustomValue', 'value', 1);
        }
    }

    private function FormatLeadTime(int $Seconds): string
    {
        $Seconds = max(0, $Seconds);
        if ($Seconds % 3600 === 0) {
            $hours = (int)($Seconds / 3600);
            return $hours === 1 ? ('1 ' . $this->Translate('hour')) : ($hours . ' ' . $this->Translate('hours'));
        }
        if ($Seconds % 60 === 0) {
            $minutes = (int)($Seconds / 60);
            return $minutes === 1 ? ('1 ' . $this->Translate('minute')) : ($minutes . ' ' . $this->Translate('minutes'));
        }

        return (string)$Seconds;
    }

    public function ProcessNotifications(): void
    {
        $visuID = $this->ReadPropertyInteger('VisualizationInstanceID');
        if ($visuID <= 0) {
            return;
        }

        $defaultLeadTime = max(0, $this->ReadPropertyInteger('NotificationLeadTime'));
        $now = time();

        $items = $this->LoadItems();
        $changed = false;

        foreach ($items as &$item) {
            if (empty($item['notification'])) {
                continue;
            }
            if (!empty($item['done'])) {
                continue;
            }

            $due = (int)($item['due'] ?? 0);
            if ($due <= 0) {
                continue;
            }

            $leadTime = $defaultLeadTime;
            if (array_key_exists('notificationLeadTime', $item)) {
                $leadTime = max(0, (int)$item['notificationLeadTime']);
            }

            $trigger = $due - $leadTime;
            if ($now < $trigger) {
                continue;
            }

            $alreadyFor = (int)($item['notifiedFor'] ?? 0);
            if ($alreadyFor === $trigger) {
                continue;
            }

            $itemTitle = (string)($item['title'] ?? '');
            $title = $this->Translate('Task due');
            if ($leadTime > 0) {
                $leadTimeText = $this->FormatLeadTime($leadTime);
                $title = str_replace('{0}', $leadTimeText, $this->Translate('Task due in title'));
            }
            $title = substr($title, 0, 32);

            $text = substr($itemTitle, 0, 256);

            $result = @VISU_PostNotification($visuID, $title, $text, 'Info', $this->InstanceID);
            if ($result !== false) {
                $item['notifiedFor'] = $trigger;
                $changed = true;
            }
        }
        unset($item);

        if ($changed) {
            $this->SaveItems($items);
        }
    }

    private function ResetNotificationMarkers(): void
    {
        $items = $this->LoadItems();
        $changed = false;
        foreach ($items as &$item) {
            if ((int)($item['notifiedFor'] ?? 0) !== 0) {
                $item['notifiedFor'] = 0;
                $changed = true;
            }
        }
        unset($item);

        if ($changed) {
            $this->SaveItems($items);
        }
    }

    private function SyncItemsTableFormValues(): void
    {
        $last = $this->ReadAttributeInteger('LastConfigFormRequest');
        if ($last <= 0 || (time() - $last) > 3600) {
            return;
        }

        $this->UpdateFormField('ItemsTable', 'values', json_encode($this->BuildItemsTableValues($this->LoadItems())));
    }

    private function UpdateStatistics(): void
    {
        $items = $this->LoadItems();
        $todayStart = strtotime('today');
        $todayEnd = $todayStart + 86400;

        $open = 0;
        $overdue = 0;
        $dueToday = 0;

        foreach ($items as $item) {
            if (!empty($item['done'])) {
                continue;
            }
            $open++;
            $due = (int)($item['due'] ?? 0);
            if ($due > 0) {
                if ($due < $todayStart) {
                    $overdue++;
                } elseif ($due >= $todayStart && $due < $todayEnd) {
                    $dueToday++;
                }
            }
        }

        $this->SetValue('OpenTasks', $open);
        $this->SetValue('OverdueTasks', $overdue);
        $this->SetValue('DueTodayTasks', $dueToday);
    }

    public function ProcessRecurrences(): void
    {
        $items = $this->LoadItems();
        $now = time();

        $interval = 60;

        $changed = false;

        foreach ($items as &$item) {
            if (empty($item['done'])) {
                continue;
            }

            $due = (int)($item['due'] ?? 0);
            if ($due <= 0) {
                continue;
            }

            $recurrence = $this->NormalizeRecurrence($item['recurrence'] ?? 'none', $due);
            if ($recurrence === 'none') {
                if (isset($item['recurrence']) && (string)$item['recurrence'] !== 'none') {
                    $item['recurrence'] = 'none';
                    $item['recurrenceResetLeadTime'] = 0;
                    $changed = true;
                }
                continue;
            }

            $leadTime = $this->NormalizeRecurrenceResetLeadTime($item['recurrenceResetLeadTime'] ?? null, $recurrence);
            if ($leadTime === -1) {
                if ($due <= $now) {
                    $unit = (string)($item['recurrenceCustomUnit'] ?? 'w');
                    $val = (int)($item['recurrenceCustomValue'] ?? 1);
                    $newDue = $this->GetNextDue($due, $recurrence, $unit, $val);
                    $guard = 0;
                    while ($newDue > 0 && $newDue <= $now && $guard < 24) {
                        $newDue = $this->GetNextDue($newDue, $recurrence, $unit, $val);
                        $guard++;
                    }
                    if ($newDue !== $due) {
                        $item['due'] = $newDue;
                    }
                }
                $item['done'] = false;
                $item['notifiedFor'] = 0;
                $item['updatedAt'] = $now;
                $changed = true;
                continue;
            }
            $windowStart = $leadTime - $interval;
            if ($leadTime <= 0) {
                continue;
            }

            $left = $due - $now;
            if ($left <= $leadTime && $left >= $windowStart) {
                $item['done'] = false;
                $item['notifiedFor'] = 0;
                $item['updatedAt'] = $now;
                $changed = true;
                continue;
            }

            if ($left < $windowStart) {
                $unit = (string)($item['recurrenceCustomUnit'] ?? 'w');
                $val = (int)($item['recurrenceCustomValue'] ?? 1);
                $newDue = $this->GetNextDue($due, $recurrence, $unit, $val);
                $guard = 0;
                while ($newDue > 0 && $newDue <= $now && $guard < 24) {
                    $newDue = $this->GetNextDue($newDue, $recurrence, $unit, $val);
                    $guard++;
                }
                if ($newDue !== $due) {
                    $item['due'] = $newDue;
                    $item['notifiedFor'] = 0;
                    $item['updatedAt'] = $now;
                    $changed = true;
                }
            }
        }
        unset($item);

        if ($changed) {
            $this->SaveItems($items);
            $this->SendState();
        }
    }

    private function UpdateRecurrenceTimer(?array $Items = null): void
    {
        if ($Items === null) {
            $Items = $this->LoadItems();
        }
        $has = false;
        foreach ($Items as $it) {
            if ($this->NormalizeRecurrence($it['recurrence'] ?? 'none', (int)($it['due'] ?? 0)) !== 'none') {
                $has = true;
                break;
            }
        }
        $this->SetTimerInterval('RecurrenceTimer', $has ? 60000 : 0);
    }

    private function NormalizeRecurrence(mixed $Value, int $Due): string
    {
        if ($Due <= 0) {
            return 'none';
        }
        $r = is_string($Value) ? strtolower(trim($Value)) : 'none';
        $allowed = ['none', 'custom', 'w1', 'w2', 'w3', 'm1', 'q1', 'y1'];
        if (!in_array($r, $allowed, true)) {
            return 'none';
        }
        return $r;
    }

    private function NormalizeRecurrenceCustomUnit(mixed $Value): string
    {
        $u = is_string($Value) ? strtolower(trim($Value)) : '';
        $allowed = ['h', 'd', 'w', 'm', 'y'];
        if (!in_array($u, $allowed, true)) {
            return 'w';
        }
        return $u;
    }

    private function NormalizeRecurrenceCustomValue(mixed $Value): int
    {
        $v = null;
        if (is_int($Value)) {
            $v = $Value;
        } elseif (is_numeric($Value)) {
            $v = (int)$Value;
        }
        if ($v === null) {
            return 1;
        }
        if ($v <= 0) {
            return 1;
        }
        return min($v, 1000);
    }

    private function NormalizeRecurrenceResetLeadTime(mixed $Value, string $Recurrence): int
    {
        if ($this->NormalizeRecurrence($Recurrence, 1) === 'none') {
            return 0;
        }

        if ($Value === null) {
            return 604800;
        }

        $v = null;
        if (is_int($Value)) {
            $v = $Value;
        } elseif (is_numeric($Value)) {
            $v = (int)$Value;
        }

        if ($v === null) {
            return 604800;
        }
        if ($v === -1) {
            return -1;
        }
        if ($v === 0) {
            return 0;
        }
        if ($v < 0) {
            return 604800;
        }

        $allowed = [-1, 1800, 3600, 21600, 43200, 86400, 172800, 259200, 604800, 1209600, 2592000];
        if (!in_array($v, $allowed, true)) {
            return 604800;
        }
        return $v;
    }

    private function NormalizeNotificationLeadTimeDefault(int $Value): int
    {
        $v = max(0, $Value);
        $allowed = [0, 300, 600, 1800, 3600, 18000, 43200];
        if (!in_array($v, $allowed, true)) {
            return 600;
        }
        return $v;
    }

    private function NormalizeNotificationLeadTime(mixed $Value, int $Default): int
    {
        if ($Value === null) {
            return $Default;
        }

        $v = null;
        if (is_int($Value)) {
            $v = $Value;
        } elseif (is_numeric($Value)) {
            $v = (int)$Value;
        }
        if ($v === null) {
            return $Default;
        }
        if ($v < 0) {
            return $Default;
        }

        $allowed = [0, 300, 600, 1800, 3600, 18000, 43200];
        if (!in_array($v, $allowed, true)) {
            return $Default;
        }
        return $v;
    }

    private function GetRecurrenceIntervalSeconds(int $Due, string $Recurrence, string $CustomUnit = 'w', int $CustomValue = 1): int
    {
        if ($Due <= 0) {
            return 0;
        }
        $r = $this->NormalizeRecurrence($Recurrence, $Due);
        if ($r === 'none') {
            return 0;
        }
        $next = $this->GetNextDue($Due, $r, $CustomUnit, $CustomValue);
        $delta = $next - $Due;
        return $delta > 0 ? $delta : 0;
    }

    private function ClampLeadTimeToInterval(int $LeadTime, int $Interval, array $Allowed): int
    {
        if ($LeadTime === -1) {
            return -1;
        }
        $LeadTime = max(0, $LeadTime);
        if ($Interval <= 0) {
            return $LeadTime;
        }
        if ($LeadTime === 0) {
            return 0;
        }
        if ($LeadTime < $Interval) {
            return $LeadTime;
        }

        $best = 0;
        foreach ($Allowed as $v) {
            $v = (int)$v;
            if ($v < $Interval && $v > $best) {
                $best = $v;
            }
        }
        return $best;
    }

    private function GetLeadTimeLimitSeconds(int $Due, int $Now, string $Recurrence, string $CustomUnit = 'w', int $CustomValue = 1): int
    {
        if ($Due <= 0) {
            return 0;
        }
        $limit = max(0, $Due - $Now);
        if ($limit <= 0) {
            return 0;
        }

        $r = $this->NormalizeRecurrence($Recurrence, $Due);
        if ($r !== 'none') {
            $interval = $this->GetRecurrenceIntervalSeconds($Due, $r, $CustomUnit, $CustomValue);
            if ($interval > 0) {
                $limit = min($limit, $interval);
            }
        }

        return $limit;
    }

    private function ClampLeadTimeToLimit(int $LeadTime, int $Limit, array $Allowed): int
    {
        $LeadTime = max(0, $LeadTime);
        if ($LeadTime === 0) {
            return 0;
        }
        if ($Limit <= 0) {
            return 0;
        }
        if ($LeadTime < $Limit) {
            return $LeadTime;
        }

        $best = 0;
        foreach ($Allowed as $v) {
            $v = (int)$v;
            if ($v === 0) {
                continue;
            }
            if ($v < $Limit && $v > $best) {
                $best = $v;
            }
        }
        return $best;
    }

    private function GetNextDue(int $Due, string $Recurrence, string $CustomUnit = '', int $CustomValue = 0): int
    {
        if ($Due <= 0) {
            return 0;
        }
        $r = $this->NormalizeRecurrence($Recurrence, $Due);
        switch ($r) {
            case 'custom':
                $u = $this->NormalizeRecurrenceCustomUnit($CustomUnit);
                $v = $this->NormalizeRecurrenceCustomValue($CustomValue);
                switch ($u) {
                    case 'h':
                        return $Due + (3600 * $v);
                    case 'd':
                        return $Due + (86400 * $v);
                    case 'w':
                        return $Due + (604800 * $v);
                    case 'm':
                        return $this->AddMonthsClamped($Due, $v);
                    case 'y':
                        return $this->AddMonthsClamped($Due, 12 * $v);
                    default:
                        return $Due;
                }
            case 'w1':
                return $Due + 604800;
            case 'w2':
                return $Due + 1209600;
            case 'w3':
                return $Due + 1814400;
            case 'm1':
                return $this->AddMonthsClamped($Due, 1);
            case 'q1':
                return $this->AddMonthsClamped($Due, 3);
            case 'y1':
                return $this->AddMonthsClamped($Due, 12);
            default:
                return $Due;
        }
    }

    private function AddMonthsClamped(int $Due, int $Months): int
    {
        $year = (int)date('Y', $Due);
        $month = (int)date('n', $Due);
        $day = (int)date('j', $Due);
        $hour = (int)date('G', $Due);
        $minute = (int)date('i', $Due);
        $second = (int)date('s', $Due);

        $month += $Months;
        $year += intdiv($month - 1, 12);
        $month = (($month - 1) % 12) + 1;

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $day = min($day, $daysInMonth);

        return (int)mktime($hour, $minute, $second, $month, $day, $year);
    }

    private function SendState(): void
    {
        $sort = $this->GetSortPrefs();
        $this->UpdateVisualizationValue(json_encode([
            'type'  => 'state',
            'items' => $this->LoadItems(),
            'notificationLeadTimeDefault' => $this->ReadPropertyInteger('NotificationLeadTime'),
            'sortMode' => $sort['mode'],
            'sortDir'  => $sort['dir'],
            'orderVersion' => $this->ReadAttributeInteger('OrderVersion'),
            'showOverview' => $this->ReadPropertyBoolean('ShowOverview'),
            'showCreateButton' => $this->ReadPropertyBoolean('ShowCreateButton'),
            'showSorting' => $this->ReadPropertyBoolean('ShowSorting'),
            'useGridView' => $this->ReadPropertyBoolean('UseGridView'),
            'showLargeQuantity' => $this->ReadPropertyBoolean('ShowLargeQuantity'),
            'gridShoppingListMode' => $this->ReadPropertyBoolean('GridShoppingListMode'),
            'showInfoBadges' => $this->ReadPropertyBoolean('ShowInfoBadges'),
            'showDeleteButton' => $this->ReadPropertyBoolean('ShowDeleteButton'),
            'showEditButton' => $this->ReadPropertyBoolean('ShowEditButton'),
            'hideCompletedTasks' => $this->ReadPropertyBoolean('HideCompletedTasks'),
            'deleteCompletedTasks' => $this->ReadPropertyBoolean('DeleteCompletedTasks')
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
