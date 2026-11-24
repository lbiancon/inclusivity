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
    <title>MenuCraft AI — scegli l’ingresso</title>
    <link rel="stylesheet" href="assets/app.css">
    <style>
        :root {
            --ink: #1d2420;
            --moss: #1d4a38;
            --cream: #efe6d2;
            --paper: #f7f1e4;
        }
        body {
            font-family: "Source Sans 3", ui-sans-serif, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(ellipse 80% 50% at 10% -10%, rgba(29, 74, 56, 0.18), transparent 55%),
                radial-gradient(ellipse 70% 45% at 100% 0%, rgba(182, 64, 44, 0.12), transparent 50%),
                linear-gradient(180deg, var(--paper), var(--cream));
        }
        h1, h2, .font-serif { font-family: "Barlow Condensed", Impact, sans-serif; }
        .porta {
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .porta:hover {
            transform: translateY(-3px);
            box-shadow: 0 18px 40px rgba(29, 36, 32, 0.12);
        }
        .porta-utente {
            background: var(--moss);
            color: #f6f3ea;
        }
        .porta-azienda {
            background: #fff;
            border: 1px solid #d5cbb8;
        }
    </style>
</head>
<body class="min-h-screen text-ink antialiased">
    <main class="mx-auto flex min-h-screen max-w-5xl flex-col justify-center px-4 py-12">
        <p class="text-xs uppercase tracking-[0.28em] text-ink/50">MenuCraft AI</p>
        <h1 class="mt-3 max-w-3xl font-serif text-5xl uppercase leading-none">Chi sei?</h1>
        <p class="mt-4 max-w-2xl text-lg text-ink/80">Scegli l’ingresso: dipendente che prenota il pasto, oppure azienda / catering che gestisce il servizio.</p>
        <div class="mt-8 grid gap-4 md:grid-cols-2">
            <a href="frontend/" class="porta porta-utente block rounded-[2rem] p-8">
                <p class="text-xs uppercase tracking-[0.22em] text-[#d7eadf]">Utente</p>
                <h2 class="mt-3 font-serif text-4xl uppercase">Dipendente</h2>
                <p class="mt-3 text-[#f6f3ea]/85">Azienda, matricola e password. Guardi il menu, compili il vassoio e vedi le tue prenotazioni.</p>
                <span class="mt-6 inline-block text-sm font-semibold uppercase tracking-wider text-[#d7eadf]">Entra in linea →</span>
            </a>
            <a href="backend/" class="porta porta-azienda block rounded-[2rem] p-8">
                <p class="text-xs uppercase tracking-[0.22em] text-ink/50">Azienda</p>
                <h2 class="mt-3 font-serif text-4xl uppercase">Catering / banco</h2>
                <p class="mt-3 text-ink/75">Regole, ferie del servizio, menu, utenti e file cucina. Per chi gestisce la mensa.</p>
                <span class="mt-6 inline-block text-sm font-semibold uppercase tracking-wider text-moss">Entra al banco →</span>
            </a>
        </div>
    </main>
</body>
</html>
