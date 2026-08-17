@extends('layouts.app')

@section('title', __('positions.create'))
@section('heading', __('positions.create'))

@section('content')
    <form method="POST" action="{{ route('admin.positions.store') }}" class="card hud-in max-w-2xl p-5">
        @csrf
        @include('admin.positions._form')
    </form>
@endsection
