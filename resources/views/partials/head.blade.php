{{-- Purpose: Defines shared document metadata and Limitless stylesheet assets for Blade layouts. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'WindowShop'))</title>

    <!-- Global stylesheets -->
    <link href="{{ asset('assets/admin/fonts/inter/inter.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/admin/icons/phosphor/styles.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/admin/css/ltr/all.min.css') }}" id="stylesheet" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/admin/css/windowshop-shared.css') }}" rel="stylesheet" type="text/css">
    <!-- /global stylesheets -->

    @stack('styles')
    <style>
        .sidebar {
            width: 13.875rem !important;
        }
        body {
            font-size: 12px !important;
        }
        .navbar-brand img {
    height: 50px !important;
    border-radius: 5px !important;
}
    </style>
</head>
