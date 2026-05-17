<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FTP Client</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @livewireStyles  

    @vite(['resources/js/app.js', 'resources/css/app.css'])
    @livewireScripts 

</head>
<body>

    <div class="main">
        <div class="container">
            <div class="menu-bar">
                @livewire('menu')
                @livewire('connections-tabs')
            </div>

            <div class="content">
                @livewire('file-explorer', ['panel' => 'left'])
                @livewire('file-explorer', ['panel' => 'right'])
            </div>
        </div>

        @livewire('status-bar')
    </div>

    @livewire('modal-manager')

</body>
</html>