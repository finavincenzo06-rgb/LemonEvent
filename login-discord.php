<?php
// login-discord.php

// === CONFIGURA QUI LE TUE CREDENZIALI ===
$client_id = '1433172898033700944'; // <-- Sostituisci con il CLIENT ID reale
$redirect_uri = 'https://lemonevent.infinityfree.me/callback.php'; // <-- Assicurati che sia corretto
// =======================================

// Costruisci l'URL di autorizzazione di Discord
$auth_url = 'https://discord.com/oauth2/authorize?' . http_build_query([
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'response_type' => 'code',
    'scope' => 'identify' // Chiediamo solo l'identità di base
]);

// Reindirizza l'utente a Discord
header("Location: $auth_url");
exit();
?>