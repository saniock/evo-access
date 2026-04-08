@extends('evoAccess::layout')
@section('title', 'Audit Log')

@section('script')
webix.ready(function () {
    webix.ui({
        container: 'ea-app',
        view: 'datatable',
        url: EVO_ACCESS_BASE + '/audit/data',
        columns: [
            { id: 'created_at',    header: 'Time',     width: 160 },
            { id: 'actor_user_id', header: 'Actor',    width: 80  },
            { id: 'action',        header: 'Action',   width: 140 },
            { id: 'target_role_id',header: 'Role',     width: 60  },
            { id: 'target_user_id',header: 'User',     width: 60  },
            { id: 'permission_id', header: 'Perm',     width: 60  },
            { id: 'old_value',     header: 'Old',      fillspace: 1 },
            { id: 'new_value',     header: 'New',      fillspace: 1 }
        ]
    });
});
@endsection
