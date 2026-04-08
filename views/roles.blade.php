@extends('evoAccess::layout')
@section('title', 'Roles')

@section('script')
webix.ready(function () {
    webix.ui({
        container: 'ea-app',
        view: 'datatable',
        url: EVO_ACCESS_BASE + '/roles',
        columns: [
            { id: 'name',        header: 'Name',        fillspace: 1 },
            { id: 'label',       header: 'Label',       fillspace: 2 },
            { id: 'description', header: 'Description', fillspace: 3 },
            { id: 'is_system',   header: 'System',      width: 80 }
        ]
    });
});
@endsection
