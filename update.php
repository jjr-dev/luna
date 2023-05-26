<?php
    $apiUrl = "https://api.github.com/repos/jjr-dev/luna/releases/latest";
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'PHP'
    ];

    $curl = curl_init($apiUrl);
    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if($statusCode != 200)
        die("Erro ao obter atualização");
        
    $release = json_decode($response, true);

    if(!$release)
        die("Erro ao obter dados da atualização");

    $zipUrl = $release['zipball_url'];
    $zipFile = __DIR__ . '/latest_release.zip';
    $file = fopen($zipFile, 'w');

    $curl = curl_init($zipUrl);
    curl_setopt_array($curl, $options);
    curl_setopt($curl, CURLOPT_FILE, $file);
    curl_exec($curl);
    $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    fclose($file);

    try {
        if($statusCode != 200)
            throw new \Exception("Erro ao baixar atualização");

        $zip = new \ZipArchive;
        if($zip->open($zipFile) !== true)
            throw new \Exception("Erro ao extrair atualização");

        $zipRootFolder = $zip->getNameIndex(0);

        $zip->extractTo(__DIR__ . '/.');
        $zip->close();

        $releaseFolder = __DIR__ . "/" . $zipRootFolder;

        $protectedsFiles = [
            '\routes\pages.php',
            '\routes\errors.php',
            '\middlewares.php',
            '\define.php',
            '\resources\components\flash\alert.html',
            '\resources\components\pagination\ellipsis.html',
            '\resources\components\pagination\next.html',
            '\resources\components\pagination\number.html',
            '\resources\components\pagination\number.html',
            '\resources\views\pages\footer.html',
            '\resources\views\pages\header.html',
            '\resources\views\pages\home.html',
            '\resources\views\pages\page.html',
            '\resources\views\errors\404.html',
            '\public\assets\css\index.css',
            '\App\Controllers\Pages\Home.php',
            '\App\Controllers\Errors\PageNotFound.php'
        ];

        foreach($protectedsFiles as $key => $file) {
            $protectedsFiles[$key] = str_replace('/\\', '\\', $releaseFolder . $file);
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($releaseFolder, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach($files as $file) {
            $targetPath = __DIR__ . '/' . (substr($file, strlen($releaseFolder)));

            $fileName = $file->getPathname();
            if(in_array($fileName, $protectedsFiles)) continue;
            
            if($file->isDir()) @mkdir($targetPath);
            else rename($fileName, $targetPath);
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($releaseFolder, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach($files as $file) {
            $path = $file->getRealPath();

            if($file->isDir()) rmdir($path);
            else unlink($path);
        }

        unlink($zipFile);
        rmdir($releaseFolder);
        die("Atualização concluída");
    } catch(\Exception $e) {
        die($e->getMessage());
        unlink($zipFile);
    }