<?php
// Imposta questi header all'inizio per gestire meglio CORS e richieste da frontend
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // ATTENZIONE: meno sicuro, ma funziona su hosting come InfinityFree
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestione richiesta OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Assicurati che non ci siano output prima del JSON
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php'; // Assicurati che questo file contenga i dati di connessione corretti

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Ottieni tutti i suggerimenti
        $stmt = $pdo->prepare("SELECT id, discord_id, titolo, descrizione, requisiti, quando, data_invio, voti_positivi, voti_negativi FROM suggerimenti ORDER BY data_invio DESC");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($results);
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (isset($input['action'])) {
            $action = $input['action'];

            if ($action === 'delete') {
                $id = (int)$input['id'];
                if ($id <= 0) {
                    throw new Exception('ID non valido per la cancellazione.');
                }

                // Cancella voti associati
                $stmt = $pdo->prepare("DELETE FROM voti WHERE suggerimento_id = ?");
                $stmt->execute([$id]);

                // Cancella suggerimento
                $stmt = $pdo->prepare("DELETE FROM suggerimenti WHERE id = ?");
                $stmt->execute([$id]);

                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Suggerimento eliminato.']);
                } else {
                    throw new Exception('Nessun suggerimento trovato con questo ID.');
                }
            } elseif ($action === 'vote') {
                $id = (int)$input['id'];
                $type = $input['type'];
                $ip = $_SERVER['REMOTE_ADDR'];

                if (!in_array($type, ['up', 'down']) || $id <= 0) {
                    throw new Exception('Dati di voto non validi.');
                }

                // Controlla se ha già votato
                $stmt = $pdo->prepare("SELECT 1 FROM voti WHERE suggerimento_id = ? AND ip_address = ?");
                $stmt->execute([$id, $ip]);
                if ($stmt->fetch()) {
                    throw new Exception('Hai già votato questo suggerimento.');
                }

                // Registra il voto
                $stmt = $pdo->prepare("INSERT INTO voti (suggerimento_id, ip_address, tipo_voto) VALUES (?, ?, ?)");
                $stmt->execute([$id, $ip, $type]);

                // Aggiorna il contatore
                $colonna = $type === 'up' ? 'voti_positivi' : 'voti_negativi';
                $stmt = $pdo->prepare("UPDATE suggerimenti SET $colonna = $colonna + 1 WHERE id = ?");
                $stmt->execute([$id]);

                echo json_encode(['success' => true, 'message' => 'Voto registrato.']);
            } else {
                throw new Exception('Azione POST non riconosciuta: ' . $action);
            }
        } else {
            // Inserimento nuovo suggerimento
            $discord_id = $input['discordId'] ?? '';
            $title = $input['title'] ?? '';
            $desc = $input['description'] ?? '';
            $reqs = $input['requirements'] ?? '';
            $when = $input['when'] ?? '';

            $stmt = $pdo->prepare("INSERT INTO suggerimenti (discord_id, titolo, descrizione, requisiti, quando) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$discord_id, $title, $desc, $reqs, $when]);

            echo json_encode(['success' => true]);
        }
    } else {
        throw new Exception('Metodo non consentito: ' . $method);
    }
} catch (PDOException $e) {
    //file_put_contents('debug.log', date('Y-m-d H:i:s') . " - DB ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'error' => 'Errore database: ' . $e->getMessage()]);
} catch (Exception $e) {
    //file_put_contents('debug.log', date('Y-m-d H:i:s') . " - APP ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>