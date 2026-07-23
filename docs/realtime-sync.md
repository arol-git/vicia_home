# Synchronisation temps reel ESP32

Vicia Home considere la base de donnees comme l'etat confirme par l'ESP32.
Une commande Web, API, Telegram ou automatisation publie seulement une demande
sur MQTT. L'interface change ensuite lorsque le subscriber recoit un retour
d'etat du module.

## Topics attendus

- Commande vers un equipement : `<mqtt_topic>/set` avec `1` ou `0`.
- Etat confirme d'un equipement : `<mqtt_topic>/state` avec `1`, `0`, `on`,
  `off`, `open` ou `closed`.
- Le topic exact `<mqtt_topic>` est aussi accepte comme retour d'etat si le
  payload correspond a un etat binaire.
- Disponibilite ESP32 : `<base_topic>/<house_slug>/status` ou
  `<base_topic>/<house_slug>/availability` avec `online` ou `offline`.
- Demande de snapshot publiee par la plateforme :
  `<base_topic>/<house_slug>/sync/request`.
- Snapshot complet attendu en reponse :
  `<base_topic>/<house_slug>/state/all`.

## Formats de snapshot acceptes

```json
{
  "equipments": [
    { "topic": "home/maison/lighting/salon/led", "state": 1 },
    { "id": 12, "state": 0 }
  ]
}
```

ou :

```json
{
  "home/maison/lighting/salon/led": 1,
  "home/maison/security/entree/porte": 0
}
```

Le firmware doit publier un snapshot apres chaque reception de
`sync/request`, au demarrage, et apres reconnexion au broker. Il doit aussi
publier l'etat confirme apres une action manuelle locale sur interrupteur.
