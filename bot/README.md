# Vicia Home — Bot Telegram (Python)

Bot Telegram (FastAPI + python-telegram-bot, async) servant d'interface de
contrôle et de notification pour la plateforme Vicia Home. Le bot ne
communique jamais directement avec la base de données de la plateforme :
toutes les actions transitent par l'API REST sécurisée (JWT).

## Mise en place

```bash
git clone <repo> vicia-telegram-bot-py
cd vicia-telegram-bot-py
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env
```

Renseigner dans `.env` au minimum :

- `TELEGRAM_BOT_TOKEN`
- `TELEGRAM_WEBHOOK_SECRET`
- `VICIA_API_BASE_URL`
- `VICIA_WEBHOOK_SECRET`
- les identifiants de la base `vicia_bot` (`DB_*`)

## Lancer le serveur

```bash
uvicorn bot.main:app --host 0.0.0.0 --port 8000
```

En production, placer ce processus derrière un reverse proxy HTTPS
(Nginx ou Apache en mode `mod_proxy`) et le superviser avec systemd ou
Docker. Exemple de bloc Nginx :

```nginx
server {
    listen 443 ssl;
    server_name bot.vicia-home.example;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
```

## État actuel du projet

Module 1 (squelette) : configuration typée (pydantic-settings), gestion
centralisée des erreurs FastAPI, journalisation avec rotation, et câblage
propre de l'Application python-telegram-bot (démarrage/arrêt via le
lifespan FastAPI) sont en place.

Les routes `/webhook/telegram` et `/webhook/vicia-events` sont déclarées
mais répondent `501 Not Implemented` tant que les modules correspondants
(middlewares de sécurité, contrôleurs métier) n'ont pas été développés —
voir le document d'analyse, section 9, pour l'ordre des modules à venir.

Un endpoint `/health` est disponible pour la supervision.

## Qualité de code

```bash
ruff check bot routes
pytest
```
