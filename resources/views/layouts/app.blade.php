@php
    $isEpayPlus = str_contains(request()->getHost(), 'epayplus');
    $brandName = $isEpayPlus ? 'ePay Plus' : 'INSA POS';
@endphp
<!DOCTYPE html>
<html>
<head>
    <title>{{ $brandName }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    @yield('content')
</body>
</html>
