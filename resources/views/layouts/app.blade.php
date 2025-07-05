

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'FishBack')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Google Fonts: Roboto -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">

    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        roboto: ['Roboto', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <style>
        body {
            position: relative;
            min-height: 100vh;
            color: white;
        }

        body::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: url('/build/images/fisherman.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            z-index: -2;
        }

        body::after {
            content: "";
            position: absolute;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.35);
            z-index: -1;
        }
    </style>
</head>

<body class="relative text-white">

    <!-- Transparent Black Navbar -->
    <nav class="bg-black bg-opacity- shadow py-7 px-8 flex justify-between z-10 relative">
        <div class="text-xl font-bold">FishBack</div>
        <div>
            <a href="/home" class="text-sm hover:underline mr-4">Home</a>
            <a href="/about" class="text-sm hover:underline mr-4">About</a>
            <a href="/forum" class="text-sm hover:underline">Forum</a>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
        @yield('content')
    </main>

</body>

</html>
