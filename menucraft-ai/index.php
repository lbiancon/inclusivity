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
        <p class="mt-4 max-w-2xl text-lg text-ink/80">La cartella da copiare in XAMPP è questa, <span class="font-semibold">menucraft-ai</span>. Va messa dentro <span class="font-semibold">C:\xampp\htdocs\</span>, poi Apache e MySQL accesi. Non è già dentro htdocs: htdocs sta sul PC dove gira XAMPP.</p>
        <p class="mt-2 max-w-2xl text-ink/70">Apri <span class="font-semibold">http://localhost/menucraft-ai/</span>. Il database si crea da solo, utente <span class="font-semibold">root</span> e password vuota.</p>
        <div class="mt-8 grid gap-4 md:grid-cols-2">
            <a href="frontend/" class="counter block rounded-[2rem] p-8">
                <p class="text-xs uppercase tracking-[0.22em] text-[#d7eadf]">Frontend</p>
                <h2 class="mt-3 font-serif text-4xl uppercase">Linea dipendenti</h2>
                <p class="mt-3 text-[#f6f3ea]/85">Azienda, matricola e password. Il dipendente guarda il menu, sceglie il vassoio e consulta lo storico delle prenotazioni.</p>
            </a>
            <a href="backend/" class="block rounded-[2rem] border border-[#d5cbb8] bg-white p-8">
                <p class="text-xs uppercase tracking-[0.22em] text-ink/50">Backend</p>
                <h2 class="mt-3 font-serif text-4xl uppercase">Banco cucina</h2>
                <p class="mt-3 text-ink/75">Il catering imposta se l’azienda gira in intranet o su internet, le regole del vassoio, il menu del giovedì e il file da mandare con l’applicazione esterna.</p>
            </a>
        </div>
    </main>
</body>
</html>
