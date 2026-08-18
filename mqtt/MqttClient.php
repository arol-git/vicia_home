<?php

namespace Mqtt;

/**
 * Class MqttClient
 *
 * Client MQTT 3.1.1 minimaliste, écrit sans dépendance externe, basé
 * sur un socket TCP (ou TLS) brut. Prend en charge CONNECT, PUBLISH
 * (QoS 0), SUBSCRIBE et la réception de messages PUBLISH entrants.
 *
 * Ce client couvre les besoins de la plateforme Vicia Home (publier
 * des commandes vers les modules ESP32, s'abonner aux topics de
 * télémétrie et d'alerte). Pour un usage nécessitant les niveaux de
 * qualité de service QoS 1/2 avec accusés de réception persistants,
 * il est recommandé de migrer vers la bibliothèque Composer
 * "php-mqtt/client", pleinement compatible avec cette même
 * configuration de connexion.
 */
class MqttClient  // il s'agit de la classe principale du client MQTT, qui gère la connexion, la publication, l'abonnement et la réception de messages. 
{
    private $socket;  // Le socket TCP/TLS utilisé pour la communication avec le broker MQTT.
    private string $host;  // private indique que la variable est accessible uniquement à l'intérieur de la classe MqttClient. Le type string indique que la variable doit contenir une chaîne de caractères.
    private int $port;
    private bool $tls;
    private bool $tlsVerify;
    private string $clientId;
    private string $username;
    private string $password;

    public function __construct(array $config)  // fonction permettant d'initialiser les paramètres de connexion à partir d'un tableau de configuration. Le constructeur est appelé lors de la création d'une instance de la classe MqttClient.
    {
        $this->host     = $config['host'];   //this permtet de récupérer l'adresse du broker MQTT à partir du tableau de configuration et de l'assigner à la propriété $host de l'objet MqttClient.
        $this->port     = (int) $config['port'];
        $this->tls      = (bool) ($config['tls'] ?? false);
        $this->tlsVerify = (bool) ($config['tls_verify'] ?? true);
        $this->clientId = $config['client_id'] . '_' . substr(md5(uniqid('', true)), 0, 6);
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
    }

    /**
     * Ouvre la connexion TCP/TLS et envoie le paquet CONNECT MQTT.
     */
    public function connect(int $keepAlive = 60): bool
    {
        $scheme = $this->tls ? 'ssl' : 'tcp';
        $sslOptions = [
            'verify_peer'      => $this->tlsVerify,
            'verify_peer_name' => $this->tlsVerify,
        ];

        if (!$this->tlsVerify) {
            $sslOptions['allow_self_signed'] = true;
            $sslOptions['verify_depth'] = 0;
        }

        $context = stream_context_create([
            'ssl' => $sslOptions,
        ]);

        $this->socket = @stream_socket_client(
            "$scheme://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            app_log("[MQTT] Connexion échouée à {$this->host}:{$this->port} — $errstr ($errno)");
            return false;
        }

        $packet  = "\x00\x04MQTT\x04"; // Nom + niveau de protocole (4 = MQTT 3.1.1)
        $connectFlags = 0x02; // Clean session
        if ($this->username !== '') {
            $connectFlags |= 0x80;
            if ($this->password !== '') {
                $connectFlags |= 0x40;
            }
        }
        $packet .= chr($connectFlags);
        $packet .= $this->encodeShort($keepAlive);
        $packet .= $this->encodeString($this->clientId);

        if ($this->username !== '') {
            $packet .= $this->encodeString($this->username);
            if ($this->password !== '') {
                $packet .= $this->encodeString($this->password);
            }
        }

        $this->writePacket(0x10, $packet);
        $response = fread($this->socket, 4);

        // Le 4e octet du CONNACK contient le code de retour (0 = accepté)
        return isset($response[3]) && ord($response[3]) === 0;
    }

    /**
     * Publie un message sur un topic donné (QoS 0 — remise au mieux,
     * suffisant pour les commandes de pilotage d'équipements où la
     * lecture d'état vient confirmer l'application de la commande).
     */
    public function publish(string $topic, string $payload, bool $retain = false): void
    {
        $variableHeader = $this->encodeString($topic);
        $body = $variableHeader . $payload;

        $flags = 0x30; // PUBLISH, QoS 0
        if ($retain) {
            $flags |= 0x01;
        }

        $this->writePacket($flags, $body);
    }

    /**
     * S'abonne à un ou plusieurs topics (QoS 0).
     */
    public function subscribe(array $topics): void
    {
        static $packetId = 1;
        $body = $this->encodeShort($packetId++);
        foreach ($topics as $topic) {
            $body .= $this->encodeString($topic) . "\x00";
        }
        $this->writePacket(0x82, $body);
        fread($this->socket, 5); // consomme le SUBACK
    }

    /**
     * Boucle de réception : lit les paquets entrants et invoque le
     * callback fourni pour chaque message PUBLISH reçu.
     *
     * @param callable $onMessage function(string $topic, string $payload): void
     */
    public function loop(callable $onMessage, int $timeoutSeconds = 0): void
    {
        stream_set_timeout($this->socket, 15);
        $start = time();

        while (!feof($this->socket)) {
            $header = fread($this->socket, 1);
            if ($header === '' || $header === false) {
                // Pas de donnée immédiate : envoie un PINGREQ pour
                // maintenir la connexion active puis continue la boucle.
                $this->writePacket(0xC0, '');
                usleep(200000);
            } else {
                $type = ord($header) & 0xF0;
                $length = $this->readRemainingLength();
                $payload = $length > 0 ? fread($this->socket, $length) : '';

                if ($type === 0x30) { // PUBLISH
                    $topicLength = (ord($payload[0]) << 8) | ord($payload[1]);
                    $topic = substr($payload, 2, $topicLength);
                    $message = substr($payload, 2 + $topicLength);
                    $onMessage($topic, $message);
                }
            }

            if ($timeoutSeconds > 0 && (time() - $start) >= $timeoutSeconds) {
                break;
            }
        }
    }

    public function disconnect(): void
    {
        if ($this->socket) {
            $this->writePacket(0xE0, '');
            fclose($this->socket);
        }
    }

    // -------------------- utilitaires bas niveau --------------------

    private function writePacket(int $header, string $body): void
    {
        fwrite($this->socket, chr($header) . $this->encodeRemainingLength(strlen($body)) . $body);
    }

    private function encodeString(string $value): string
    {
        return $this->encodeShort(strlen($value)) . $value;
    }

    private function encodeShort(int $value): string
    {
        return chr(($value >> 8) & 0xFF) . chr($value & 0xFF);
    }

    private function encodeRemainingLength(int $length): string
    {
        $bytes = '';
        do {
            $digit = $length % 128;
            $length = intdiv($length, 128);
            if ($length > 0) {
                $digit |= 0x80;
            }
            $bytes .= chr($digit);
        } while ($length > 0);

        return $bytes;
    }

    private function readRemainingLength(): int
    {
        $multiplier = 1;
        $value = 0;
        do {
            $byte = ord(fread($this->socket, 1));
            $value += ($byte & 127) * $multiplier;
            $multiplier *= 128;
        } while (($byte & 128) !== 0);

        return $value;
    }
}
