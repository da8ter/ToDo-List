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

        $this->RegisterTimer('NotificationTimer', 0, 'TDL_ProcessNotifications($_IPS[\'TARGET\']);');

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
                    'caption' => 'Visualization instance to which the notification is sent'
                ],
                [
                    'type' => 'Select',
                    'name' => 'NotificationLeadTime',
                    'caption' => 'Notification Lead Time',
                    'options' => [
                        ['caption' => '0 minutes', 'value' => 0],
                        ['caption' => '5 minutes', 'value' => 300],
                        ['caption' => '10 minutes', 'value' => 600],
                        ['caption' => '30 minutes', 'value' => 1800],
                        ['caption' => '1 hour', 'value' => 3600],
                        ['caption' => '5 hours', 'value' => 18000],
                        ['caption' => '12 hours', 'value' => 43200]
                    ]
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'ShowOverview',
                    'caption' => 'Show overview'
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'ShowCreateButton',
                    'caption' => 'Show create button'
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'ShowSorting',
                    'caption' => 'Show sorting'
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'UseGridView',
                    'caption' => 'Use grid view',
                    'visible' => false
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'ShowLargeQuantity',
                    'caption' => 'Show large quantity',
                    'visible' => false
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'GridShoppingListMode',
                    'caption' => 'Grid shopping list mode',
                    'visible' => false
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'ShowInfoBadges',
                    'caption' => 'Show info badges'
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'ShowDeleteButton',
                    'caption' => 'Show delete button'
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'ShowEditButton',
                    'caption' => 'Show edit button'
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'HideCompletedTasks',
                    'caption' => 'Hide completed tasks'
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'DeleteCompletedTasks',
                    'caption' => 'Delete completed tasks'
                ],
                [
                    'type'  => 'List',
                    'name'  => 'ItemsTable',
                    'caption' => 'Items',
                    'rowCount' => 10,
                    'changeOrder' => true,
                    'add' => true,
                    'delete' => true,
                    'columns' => [
                        [
                            'caption' => 'ID',
                            'name' => 'id',
                            'width' => '60px',
                            'add' => 0,
                            'save' => true
                        ],
                        [
                            'caption' => 'Done',
                            'name' => 'done',
                            'width' => '60px',
                            'add' => false,
                            'edit' => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'caption' => 'Title',
                            'name' => 'title',
                            'width' => '220px',
                            'add' => '',
                            'edit' => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'caption' => 'Info',
                            'name' => 'info',
                            'width' => 'auto',
                            'add' => '',
                            'edit' => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'caption' => 'Notification',
                            'name' => 'notification',
                            'width' => '120px',
                            'add' => false,
                            'edit' => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'caption' => 'Quantity',
                            'name' => 'quantity',
                            'width' => '90px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'NumberSpinner',
                                'minimum' => 0
                            ]
                        ],
                        [
                            'caption' => 'Due',
                            'name' => 'due',
                            'width' => '140px',
                            'add' => json_encode($this->EmptySelectDateTime()),
                            'edit' => [
                                'type' => 'SelectDateTime'
                            ]
                        ],
                        [
                            'caption' => 'Priority',
                            'name' => 'priority',
                            'width' => '120px',
                            'add' => 'normal',
                            'edit' => [
                                'type' => 'Select',
                                'options' => [
                                    ['caption' => 'Low', 'value' => 'low'],
                                    ['caption' => 'Normal', 'value' => 'normal'],
                                    ['caption' => 'High', 'value' => 'high']
                                ]
                            ]
                        ]
                    ],
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
            default:
                throw new Exception($this->Translate('Invalid Ident'));
        }
    }

    public function Export(): string
    {
        return $this->ReadAttributeString('Items');
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
        $notification = (bool)($Item['notification'] ?? false);
        if ($due <= 0) {
            $notification = false;
        }

        $leadTime = $this->ReadPropertyInteger('NotificationLeadTime');
        $itemLeadTime = $leadTime;
        if (array_key_exists('notificationLeadTime', $Item)) {
            $itemLeadTime = max(0, (int)$Item['notificationLeadTime']);
        }

        $newItem = [
            'id'        => $id,
            'title'     => $title,
            'info'      => (string)($Item['info'] ?? ''),
            'done'      => (bool)($Item['done'] ?? false),
            'due'       => $due,
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

            if (array_key_exists('notificationLeadTime', $Data)) {
                $resetNotify = $resetNotify || ((int)($items[$i]['notificationLeadTime'] ?? 0) !== (int)$Data['notificationLeadTime']);
                $items[$i]['notificationLeadTime'] = max(0, (int)$Data['notificationLeadTime']);
            }

            if (((int)($items[$i]['due'] ?? 0)) <= 0) {
                $items[$i]['notification'] = false;
                $resetNotify = true;
            }

            if ($resetNotify) {
                $items[$i]['notifiedFor'] = 0;
            }

            $items[$i]['updatedAt'] = time();
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

            if ($deleteCompleted && $newDone) {
                unset($items[$i]);
                $this->SaveItems(array_values($items));
                return;
            }

            $items[$i]['done'] = $newDone;
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
        }
        $this->SaveItems($newItems);
    }

    public function GetVisualizationTile(): string
    {
        return file_get_contents(__DIR__ . '/module.html');
    }

    private function LoadItems(): array
    {
        $data = json_decode($this->ReadAttributeString('Items'), true);
        if (!is_array($data)) {
            return [];
        }

        foreach ($data as &$item) {
            if (is_array($item) && array_key_exists('icon', $item)) {
                unset($item['icon']);
            }
        }
        unset($item);

        return $data;
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
            $notification = (bool)($row['notification'] ?? false);
            $notificationLeadTime = (int)($old['notificationLeadTime'] ?? $this->ReadPropertyInteger('NotificationLeadTime'));
            $notifiedFor = (int)($old['notifiedFor'] ?? 0);
            if ((int)($old['due'] ?? 0) !== $dueTs || (bool)($old['notification'] ?? false) !== $notification || (int)($old['notificationLeadTime'] ?? $notificationLeadTime) !== $notificationLeadTime) {
                $notifiedFor = 0;
            }

            $items[] = [
                'id'        => $id,
                'title'     => $title,
                'info'      => (string)($row['info'] ?? ''),
                'done'      => (bool)($row['done'] ?? false),
                'quantity'  => (int)($row['quantity'] ?? 0),
                'notification' => $notification,
                'notificationLeadTime' => max(0, $notificationLeadTime),
                'notifiedFor'  => $notifiedFor,
                'due'       => $dueTs,
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

    private function SaveItems(array $Items): void
    {
        $this->WriteAttributeString('Items', json_encode($Items));
        $this->UpdateStatistics();
    }

    private function BuildItemsTableValues(array $Items): array
    {
        $values = [];
        foreach ($Items as $it) {
            $due = (int)($it['due'] ?? 0);
            $values[] = [
                'id'       => (int)($it['id'] ?? 0),
                'done'     => (bool)($it['done'] ?? false),
                'title'    => (string)($it['title'] ?? ''),
                'info'     => (string)($it['info'] ?? ''),
                'notification' => (bool)($it['notification'] ?? false),
                'quantity' => (int)($it['quantity'] ?? 0),
                'due'      => json_encode($this->TimestampToSelectDateTime($due)),
                'priority' => (string)($it['priority'] ?? 'normal')
            ];
        }
        return $values;
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

            $title = $this->Translate('Task due');
            $title = substr($title, 0, 32);
            $text = (string)($item['title'] ?? '');
            $text = substr($text, 0, 256);

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

    private function SendState(): void
    {
        $this->UpdateVisualizationValue(json_encode([
            'type'  => 'state',
            'items' => $this->LoadItems(),
            'notificationLeadTimeDefault' => $this->ReadPropertyInteger('NotificationLeadTime'),
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
