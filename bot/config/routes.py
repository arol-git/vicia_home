"""Chemins HTTP exposés par le bot.

Centralisés ici pour éviter de les dupliquer entre les routeurs FastAPI
et une éventuelle configuration de reverse proxy.
"""

TELEGRAM_WEBHOOK_PATH = "/webhook/telegram"
VICIA_WEBHOOK_PATH = "/webhook/vicia-events"
