@extends('evoAccess::layout')

@section('content')
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3">
                <div class="list-group">
                    @foreach ($sections as $slug => $section)
                        <a href="/access/docs/{{ $slug }}"
                           class="list-group-item list-group-item-action {{ $slug === $currentSlug ? 'active' : '' }}">
                            {{ $section['title'] }}
                        </a>
                    @endforeach
                </div>
            </div>
            <div class="col-md-9">
                <div class="card">
                    <div class="card-body">
                        {!! $html !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
