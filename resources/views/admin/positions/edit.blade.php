@extends('layouts.app')

@section('title', __('positions.edit'))
@section('heading', $position->name)

@section('content')
    <form method="POST" action="{{ route('admin.positions.update', $position) }}" class="card hud-in max-w-2xl p-5">
        @csrf
        @method('PUT')
        @include('admin.positions._form')
    </form>
@endsection
