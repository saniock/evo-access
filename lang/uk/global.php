<?php

return [
    'title' => 'Доступи',

    'nav' => [
        'users'  => 'Користувачі',
        'roles'  => 'Ролі',
        'matrix' => 'Матриця',
        'audit'  => 'Журнал',
        'docs'   => 'Документація',
    ],

    'common' => [
        'save'     => 'Зберегти',
        'cancel'   => 'Скасувати',
        'delete'   => 'Видалити',
        'edit'     => 'Редагувати',
        'create'   => 'Створити',
        'close'    => 'Закрити',
        'search'   => 'Пошук',
        'filter'   => 'Фільтр',
        'yes'      => 'Так',
        'no'       => 'Ні',
        'loading'  => 'Завантаження…',
        'error'    => 'Помилка',
        'language' => 'Мова',
    ],

    'users' => [
        'title'              => 'Користувачі',
        'search_placeholder' => 'Пошук менеджера…',
        'filter_by_role'     => 'Фільтр за роллю',
        'all_roles'          => 'Усі ролі',
        'column' => [
            'id'        => 'ID',
            'manager'   => 'Менеджер',
            'role'      => 'Роль',
            'modules'   => 'Модулі',
            'grants'    => 'Дозволи',
            'overrides' => 'Виключення',
        ],
        'popup' => [
            'role_label'     => 'Роль:',
            'permission'     => 'Дозвіл',
            'legend' => [
                'from_role'       => 'Від ролі',
                'override_grant'  => 'Override +',
                'override_revoke' => 'Override −',
                'no_access'       => 'Без доступу',
            ],
        ],
        'save_success'     => 'Дозволи збережені',
        'manager_no_name'  => 'Менеджер #:id',
    ],

    'roles' => [
        'title'         => 'Ролі',
        'create_button' => 'Створити роль',
        'column' => [
            'name'        => 'Ім’я',
            'label'       => 'Назва',
            'description' => 'Опис',
            'users'       => 'Користувачі',
            'system'      => 'Системна',
        ],
        'form' => [
            'create_title'        => 'Створення ролі',
            'edit_title'          => 'Редагування ролі: :name',
            'name_label'          => 'Ім’я (slug)',
            'name_placeholder'    => 'напр. warehouse_manager',
            'label_label'         => 'Назва',
            'label_placeholder'   => 'Відображуване ім’я',
            'description_label'   => 'Опис',
        ],
        'clone' => [
            'title'  => 'Клонування ролі: :name',
            'hint'   => 'Клонування з <b>:name</b>. Усі дозволи будуть скопійовані.',
            'button' => 'Клонувати',
        ],
        'delete' => [
            'confirm_title'     => 'Видалення ролі',
            'confirm_text'      => 'Видалити роль «:name»? Цю дію не можна скасувати.',
            'reassign_title'    => 'Видалення ролі: :name',
            'reassign_text'     => 'Роль <b>:name</b> має <b>:count</b> призначеного(-их) користувача(-ів). Перепризначте їх на іншу роль перед видаленням:',
            'reassign_new_role' => 'Нова роль',
            'reassign_button'   => 'Перепризначити і видалити',
            'select_role'       => 'Оберіть роль',
        ],
        'msg' => [
            'created'          => 'Роль створено',
            'updated'          => 'Роль оновлено',
            'cloned'           => 'Роль клоновано',
            'deleted'          => 'Роль видалено',
            'reassigned'       => ':count користувача(-ів) перепризначено, роль видалено',
        ],
    ],

    'matrix' => [
        'title'             => 'Матриця дозволів',
        'select_role'       => 'Оберіть роль',
        'column' => [
            'module'     => 'Модуль',
            'permission' => 'Дозвіл',
        ],
        'system_notice'     => 'системна — ігнорує матрицю',
    ],

    'audit' => [
        'title' => 'Журнал',
        'filter' => [
            'from'        => 'З дати',
            'to'          => 'По дату',
            'actor_id'    => 'ID актора',
            'action_type' => 'Тип дії',
            'all_actions' => 'Усі дії',
            'button'      => 'Фільтрувати',
        ],
        'action' => [
            'grant'             => 'Надання',
            'revoke'            => 'Зняття',
            'user_assigned'     => 'Призначення користувача',
            'user_role_changed' => 'Зміна ролі',
            'role_created'      => 'Створення ролі',
            'role_deleted'      => 'Видалення ролі',
            'role_cloned'       => 'Клонування ролі',
            'override_grant'    => 'Override надано',
            'override_revoke'   => 'Override знято',
            'override_removed'  => 'Override видалено',
        ],
        'column' => [
            'datetime'      => 'Дата/Час',
            'actor'         => 'Актор',
            'action'        => 'Дія',
            'role_id'       => 'ID ролі',
            'user_id'       => 'ID користувача',
            'permission_id' => 'ID дозволу',
            'old'           => 'Було',
            'new'           => 'Стало',
            'details'       => 'Деталі',
        ],
    ],

    'docs' => [
        'title' => 'Документація',
        'no_content' => 'Документація для цієї мови недоступна.',
    ],

    // Legacy action vocabulary — used by older parts of the package
    // and kept for backwards compatibility.
    'action' => [
        'view'   => 'Перегляд',
        'create' => 'Створення',
        'update' => 'Редагування',
        'edit'   => 'Редагування',
        'delete' => 'Видалення',
        'export' => 'Експорт',
        'import' => 'Імпорт',
        'bulk'   => 'Масові операції',
    ],
];
