@extends('evoAccess::layout')
@section('title', 'Users')

@section('script')
webix.ready(function () {
    webix.ui({
        container: 'ea-app',
        rows: [
            { view: 'text', id: 'userSearch', placeholder: 'Search by name or user_id...' },
            { view: 'list', id: 'userList', template: '#fullname# (id #user_id#)' }
        ]
    });
});
@endsection
