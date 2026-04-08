@extends('evoAccess::layout')

@section('title', 'Permission Matrix')

@section('script')
webix.ready(function () {
    webix.ui({
        container: 'ea-app',
        type: 'space',
        rows: [
            { template: 'EvoAccess — Matrix', height: 50, css: 'ea-active' },
            {
                cols: [
                    {
                        view: 'list',
                        id: 'rolesList',
                        width: 220,
                        css: 'ea-sidebar',
                        template: '#label# (#user_assignments_count#)',
                        url: EVO_ACCESS_BASE + '/roles',
                        on: {
                            onAfterSelect: function (id) {
                                webix.ajax(EVO_ACCESS_BASE + '/matrix/data/' + id, function (text) {
                                    const data = JSON.parse(text);
                                    $$('matrixGrid').clearAll();
                                    $$('matrixGrid').parse(data.permissions);
                                });
                            }
                        }
                    },
                    {
                        view: 'datatable',
                        id: 'matrixGrid',
                        columns: [
                            { id: 'name',  header: 'Permission', fillspace: 2 },
                            { id: 'label', header: 'Description', fillspace: 3 }
                        ]
                    }
                ]
            }
        ]
    });
});
@endsection
