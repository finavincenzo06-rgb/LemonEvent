<?php
// callback.php - Versione con cURL (più robusta)

session_start();

// === CONFIGURA QUI LE TUE CREDENZIALI ===
$client_id = '1433172898033700944'; // <-- SOSTITUISCI CON IL TUO CLIENT ID REALE
$client_secret = 'jR0GEkz88Blp5876quS_fmxqdHJdhp1i'; // <-- SOSTITUISCI CON IL TUO CLIENT SECRET REALE
$redirect_uri = 'https://lemonevent.infinityfree.me/callback.php'; // <-- ASSICURATI CHE SIA IDENTICO A QUELLO SU DISCORD DEVELOPER PORTAL
// =======================================

include 'db.php'; // Assicurati che db.php sia presente e funzionante

// Verifica se c'è un errore da Discord
if (isset($_GET['error'])) {
    die("Errore durante il login con Discord: " . htmlspecialchars($_GET['error']));
}

// Verifica se è presente il codice di autorizzazione
if (!isset($_GET['code'])) {
    die("Codice di autorizzazione mancante.");
}

$code = $_GET['code'];

echo "<h2>Debug: Inizio processo</h2>";
echo "<p>Codice ricevuto: " . htmlspecialchars($code) . "</p>";

// === PASSO 1: Scambia il codice con un Access Token ===
$token_url = 'https://discord.com/api/oauth2/token';

$data = [
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => $redirect_uri
];

// Usa cURL invece di file_get_contents
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Verifica certificato SSL
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // Verifica hostname
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);

$result = curl_exec($ch);
$curl_error = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($result === false) {
    echo "<p style='color: red;'>❌ Errore nella richiesta del token a Discord.</p>";
    echo "<p>Dettagli cURL: " . htmlspecialchars($curl_error) . "</p>";
    echo "<p>Status Code: " . htmlspecialchars($http_code) . "</p>";
    die();
}

echo "<p style='color: green;'>✅ Richiesta del token avvenuta con successo.</p>";
echo "<p>Risposta da Discord (token):</p><pre>" . htmlspecialchars($result) . "</pre>";

$token_data = json_decode($result, true);

if (isset($token_data['error'])) {
    echo "<p style='color: red;'>❌ Errore da Discord API (scambio token): " . htmlspecialchars($token_data['error_description'] ?? 'Errore sconosciuto') . "</p>";
    die();
}

$access_token = $token_data['access_token'];
echo "<p style='color: green;'>✅ Token ottenuto con successo.</p>";

// === PASSO 2: Usa il token per ottenere i dati dell'utente ===
$user_url = 'https://discord.com/api/users/@me';
$ch_user = curl_init();
curl_setopt($ch_user, CURLOPT_URL, $user_url);
curl_setopt($ch_user, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token
]);
curl_setopt($ch_user, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch_user, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch_user, CURLOPT_SSL_VERIFYHOST, 2);

$user_result = curl_exec($ch_user);
$curl_user_error = curl_error($ch_user);
$http_user_code = curl_getinfo($ch_user, CURLINFO_HTTP_CODE);

curl_close($ch_user);

if ($user_result === false) {
    echo "<p style='color: red;'>❌ Errore nel recupero dei dati utente da Discord.</p>";
    echo "<p>Dettagli cURL: " . htmlspecialchars($curl_user_error) . "</p>";
    echo "<p>Status Code: " . htmlspecialchars($http_user_code) . "</p>";
    die();
}

echo "<p style='color: green;'>✅ Dati utente recuperati con successo.</p>";
echo "<p>Risposta da Discord (utente):</p><pre>" . htmlspecialchars($user_result) . "</pre>";

$user_data = json_decode($user_result, true);

if (isset($user_data['error'])) {
    echo "<p style='color: red;'>❌ Errore da Discord API (recupero utente): " . htmlspecialchars($user_data['error']['message'] ?? 'Errore sconosciuto') . "</p>";
    die();
}

// === PASSO 3: Salva o recupera l'utente nel database ===
try {
    $discord_id = $user_data['id'];
    $username = $user_data['username'];
    $discriminator = $user_data['discriminator'];
    $avatar_hash = $user_data['avatar'] ?? null;
    $discord_tag = $username . '#' . $discriminator;

    echo "<p style='color: blue;'>ℹ️ Dati utente: ID=" . htmlspecialchars($discord_id) . ", Tag=" . htmlspecialchars($discord_tag) . "</p>";

    $stmt = $pdo->prepare("SELECT id FROM utenti_discord WHERE discord_id = ?");
    $stmt->execute([$discord_id]);
    $user_row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_row) {
        echo "<p style='color: blue;'>ℹ️ Utente nuovo, lo sto creando...</p>";
        $stmt = $pdo->prepare("INSERT INTO utenti_discord (discord_id, username, discriminator, discord_tag, avatar_hash) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$discord_id, $username, $discriminator, $discord_tag, $avatar_hash]);
        $user_id = $pdo->lastInsertId();
    } else {
        echo "<p style='color: blue;'>ℹ️ Utente già esistente, lo recupero...</p>";
        $user_id = $user_row['id'];
    }

    // Memorizza i dati nell'array di sessione
    $_SESSION['user_id'] = $user_id;
    $_SESSION['discord_id'] = $discord_id;
    $_SESSION['discord_tag'] = $discord_tag;
    $_SESSION['logged_in'] = true;

    echo "<p style='color: green;'>✅ Sessione avviata con successo.</p>";

    // Reindirizza alla homepage
    header("Location: index.html");
    exit();

} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Errore database: " . htmlspecialchars($e->getMessage()) . "</p>";
    die();
}
?>