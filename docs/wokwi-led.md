# Test Wokwi ESP32 + LED

Configuration MQTT actuelle du site :

- Broker : `broker.hivemq.com`
- Port : `1883`
- TLS : non
- Identifiant / mot de passe : vide

Dans le site, ajoute une LED dans `Équipements` avec un topic comme :

```text
home/villa-yaounde/lighting/salon/led1
```

Quand tu cliques sur l'interrupteur du site, Vicia Home publie sur :

```text
home/villa-yaounde/lighting/salon/led1/set
```

Payload envoyé :

- `1` : allumer la LED
- `0` : éteindre la LED

Dans Wokwi, ton ESP32 doit donc s'abonner au topic `/set` :

```cpp
const char* mqtt_server = "broker.hivemq.com";
const int mqtt_port = 1883;
const char* command_topic = "home/villa-yaounde/lighting/salon/led1/set";
```

Si tu utilises une autre maison, remplace `villa-yaounde` par le slug affiché dans le topic MQTT proposé par le site.
