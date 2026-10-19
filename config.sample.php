<?php
// Zkopíruj jako config.php a vyplň. config.php se nikdy nenahrává na GitHub.
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'marmal_discgolf',
        'user' => 'marmal_discgolf',
        'pass' => 'DOPLNIT',
    ],
    // Aktualizace tlačítkem z GitHubu (soukromý repozitář → potřebuje token jen pro čtení)
    'github' => [
        'repo'   => 'marmal-code/marmal-discgolf',
        'branch' => 'main',
        'token'  => 'DOPLNIT',   // GitHub → Settings → Developer settings → Fine-grained token, Contents: Read-only
    ],
];
