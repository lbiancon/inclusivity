<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MenuCraft AI</title>
    <link rel="stylesheet" href="assets/app.css">
    <style>
        body { font-family: "Source Sans 3", ui-sans-serif, sans-serif; background: #efe6d2; color: #1d2420; }
        h1, h2, .font-serif { font-family: "Barlow Condensed", Impact, sans-serif; }
        .counter { background: #1d4a38; color: #f6f3ea; }
    </style>
</head>
<body class="min-h-screen bg-cream text-ink">
    <main class="mx-auto max-w-5xl px-4 py-12">
        <p class="text-xs uppercase tracking-[0.28em] text-ink/50">MenuCraft AI</p>
        <h1 class="mt-3 font-serif text-5xl uppercase leading-none">Due ingressi, una cartella.</h1>
        <p class="mt-4 max-w-2xl text-lg text-ink/80">Scegli se entrare in linea come dipendente oppure al banco cucina come catering.</p>
        <div class="mt-8 grid gap-4 md:grid-cols-2">
            <a href="frontend/" class="counter block rounded-[2rem] p-8">
                <p class="text-xs uppercase tracking-[0.22em] text-[#d7eadf]">Frontend</p>
                <h2 class="mt-3 font-serif text-4xl uppercase">Linea dipendenti</h2>
                <p class="mt-3 text-[#f6f3ea]/85">Azienda, matricola e password. Il dipendente guarda il menu, sceglie il vassoio e consulta lo storico.</p>
            </a>
            <a href="backend/" class="block rounded-[2rem] border border-[#d5cbb8] bg-white p-8">
                <p class="text-xs uppercase tracking-[0.22em] text-ink/50">Backend</p>
                <h2 class="mt-3 font-serif text-4xl uppercase">Banco cucina</h2>
                <p class="mt-3 text-ink/75">Regole, messaggi di guida, menu del giorno o del mese, utenti e file per la cucina.</p>
            </a>
        </div>
    </main>
</body>
</html>
