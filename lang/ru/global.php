<?php

return [
    'title' => 'Доступы',

    'nav' => [
        'users'  => 'Пользователи',
        'roles'  => 'Роли',
        'matrix' => 'Матрица',
        'audit'  => 'Журнал',
        'docs'   => 'Документация',
    ],

    'common' => [
        'save'     => 'Сохранить',
        'cancel'   => 'Отмена',
        'delete'   => 'Удалить',
        'edit'     => 'Редактировать',
        'create'   => 'Создать',
        'close'    => 'Закрыть',
        'search'   => 'Поиск',
        'filter'   => 'Фильтр',
        'yes'      => 'Да',
        'no'       => 'Нет',
        'loading'  => 'Загрузка…',
        'error'    => 'Ошибка',
        'language' => 'Язык',
    ],

    'users' => [
        'title'              => 'Пользователи',
        'search_placeholder' => 'Поиск менеджера…',
        'filter_by_role'     => 'Фильтр по роли',
        'all_roles'          => 'Все роли',
        'column' => [
            'id'        => 'ID',
            'manager'   => 'Менеджер',
            'role'      => 'Роль',
            'modules'   => 'Модули',
            'grants'    => 'Права',
            'overrides' => 'Исключения',
        ],
        'popup' => [
            'role_label'     => 'Роль:',
            'permission'     => 'Разрешение',
            'legend' => [
                'from_role'       => 'От роли',
                'override_grant'  => 'Override +',
                'override_revoke' => 'Override −',
                'no_access'       => 'Нет доступа',
            ],
        ],
        'save_success'     => 'Права сохранены',
        'manager_no_name'  => 'Менеджер #:id',
    ],

    'roles' => [
        'title'         => 'Роли',
        'create_button' => 'Создать роль',
        'column' => [
            'name'        => 'Имя',
            'label'       => 'Название',
            'description' => 'Описание',
            'users'       => 'Пользователи',
            'system'      => 'Системная',
        ],
        'form' => [
            'create_title'        => 'Создание роли',
            'edit_title'          => 'Редактирование роли: :name',
            'name_label'          => 'Имя (slug)',
            'name_placeholder'    => 'напр. warehouse_manager',
            'label_label'         => 'Название',
            'label_placeholder'   => 'Отображаемое имя',
            'description_label'   => 'Описание',
        ],
        'clone' => [
            'title'  => 'Клонирование роли: :name',
            'hint'   => 'Клонирование из <b>:name</b>. Все разрешения будут скопированы.',
            'button' => 'Клонировать',
        ],
        'delete' => [
            'confirm_title'     => 'Удаление роли',
            'confirm_text'      => 'Удалить роль «:name»? Это действие нельзя отменить.',
            'reassign_title'    => 'Удаление роли: :name',
            'reassign_text'     => 'У роли <b>:name</b> есть <b>:count</b> назначенный(-х) пользователь(-ей). Переназначьте их на другую роль перед удалением:',
            'reassign_new_role' => 'Новая роль',
            'reassign_button'   => 'Переназначить и удалить',
            'select_role'       => 'Выберите роль',
        ],
        'msg' => [
            'created'    => 'Роль создана',
            'updated'    => 'Роль обновлена',
            'cloned'     => 'Роль клонирована',
            'deleted'    => 'Роль удалена',
            'reassigned' => ':count пользователь(-ей) переназначено, роль удалена',
        ],
    ],

    'matrix' => [
        'title'         => 'Матрица разрешений',
        'select_role'   => 'Выберите роль',
        'column' => [
            'module'     => 'Модуль',
            'permission' => 'Разрешение',
        ],
        'system_notice' => 'системная — игнорирует матрицу',
    ],

    'audit' => [
        'title' => 'Журнал',
        'filter' => [
            'from'        => 'С даты',
            'to'          => 'По дату',
            'actor_id'    => 'ID актора',
            'action_type' => 'Тип действия',
            'all_actions' => 'Все действия',
            'button'      => 'Фильтровать',
        ],
        'action' => [
            'grant'             => 'Предоставление',
            'revoke'            => 'Снятие',
            'user_assigned'     => 'Назначение пользователя',
            'user_role_changed' => 'Смена роли',
            'role_created'      => 'Создание роли',
            'role_deleted'      => 'Удаление роли',
            'role_cloned'       => 'Клонирование роли',
            'override_grant'    => 'Override предоставлен',
            'override_revoke'   => 'Override снят',
            'override_removed'  => 'Override удалён',
        ],
        'column' => [
            'datetime'      => 'Дата/Время',
            'actor'         => 'Актор',
            'action'        => 'Действие',
            'role_id'       => 'ID роли',
            'user_id'       => 'ID пользователя',
            'permission_id' => 'ID права',
            'old'           => 'Было',
            'new'           => 'Стало',
            'details'       => 'Детали',
        ],
    ],

    'docs' => [
        'title'      => 'Документация',
        'no_content' => 'Документация для этого языка недоступна.',
    ],

    'action' => [
        'view'   => 'Просмотр',
        'create' => 'Создание',
        'update' => 'Редактирование',
        'edit'   => 'Редактирование',
        'delete' => 'Удаление',
        'export' => 'Экспорт',
        'import' => 'Импорт',
        'bulk'       => 'Массовые операции',
        'manage_all' => 'Управление всеми',
    ],
];
