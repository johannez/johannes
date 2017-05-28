<!doctype html>
<html lang="{{ config('app.locale') }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <title>Johannes | {{ $title }}</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        {{--<link rel="shortcut icon" href="/img/favicon.png">--}}
        <link href='//fonts.googleapis.com/css?family=Roboto+Slab:400,100,700' rel='stylesheet' type='text/css'>

        @section('stylesheet')
            <link rel="stylesheet" href="/css/app.css">
        @show

    </head>
    <body class="{{ implode(' ', $body_classes) }}">
        <!--[if lt IE 9]>
        <p class="chromeframe">You are using an <strong>outdated</strong> browser. Please <a href="http://browsehappy.com/">upgrade your browser</a> or <a href="http://www.google.com/chromeframe/?redirect=true">activate Google Chrome Frame</a> to improve your experience.</p>
        <![endif]-->

        <div id="page">
            @yield('page')
        </div>

        @section('javascript')
            {{--<script src="{{ asset('assets/twotabs/js/libs/jquery-2.0.3.min.js') }}"></script>--}}

            {{--<script src="{{ asset('assets/twotabs/js/main.js') }}"></script>--}}
        @show
    </body>
</html>
