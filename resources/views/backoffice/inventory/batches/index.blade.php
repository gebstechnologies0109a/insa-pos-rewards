@extends('layouts.backoffice')

@section('page-title', 'Inventory Batches')

@section('content')
@include('backoffice.inventory.batches', get_defined_vars())
@endsection
