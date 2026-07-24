"""Point d'entrée ASGI de l'application.

Lance avec :

    uvicorn bot.main:app --host 0.0.0.0 --port 8000

En production, ce processus tourne derrière un reverse proxy HTTPS
(Nginx/Apache) ou un service géré ; ce fichier ne fait aucune
hypothèse sur le déploiement.
"""

from __future__ import annotations

import logging
from contextlib import asynccontextmanager
from typing import AsyncIterator

from fastapi import FastAPI, Request, status
from fastapi.responses import JSONResponse
from telegram.ext import Application

from bot.config.settings import get_settings
from bot.exceptions import ApiException, UnauthorizedException, ValidationException
from bot.logging_config import configure_logging
from routes.web import build_router

settings = get_settings()
logger = configure_logging(settings)


@asynccontextmanager
async def lifespan(app: FastAPI) -> AsyncIterator[None]:
    """Démarre et arrête proprement l'Application python-telegram-bot.

    Le webhook Telegram (module suivant) réutilisera cette instance via
    ``app.state.telegram_application`` plutôt que d'en recréer une.
    """
    telegram_application: Application | None = None

    if settings.telegram_bot_token:
        telegram_application = Application.builder().token(settings.telegram_bot_token).updater(None).build()
        await telegram_application.initialize()
        await telegram_application.start()
        logger.info("Application python-telegram-bot démarrée.")
    else:
        logger.warning(
            "TELEGRAM_BOT_TOKEN absent : l'Application python-telegram-bot n'est pas initialisée."
        )

    app.state.telegram_application = telegram_application

    yield

    if telegram_application is not None:
        await telegram_application.stop()
        await telegram_application.shutdown()
        logger.info("Application python-telegram-bot arrêtée proprement.")


app = FastAPI(
    title="Vicia Home — Bot Telegram",
    debug=settings.app_debug,
    lifespan=lifespan,
)

app.include_router(build_router())


@app.exception_handler(UnauthorizedException)
async def handle_unauthorized(request: Request, exc: UnauthorizedException) -> JSONResponse:
    logger.warning("Accès refusé sur %s : %s", request.url.path, exc)
    return JSONResponse(status_code=status.HTTP_401_UNAUTHORIZED, content={"status": "error", "message": str(exc)})


@app.exception_handler(ValidationException)
async def handle_validation(request: Request, exc: ValidationException) -> JSONResponse:
    logger.info("Validation échouée sur %s : %s", request.url.path, exc.errors)
    return JSONResponse(
        status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
        content={"status": "error", "message": str(exc), "errors": exc.errors},
    )


@app.exception_handler(ApiException)
async def handle_api_exception(request: Request, exc: ApiException) -> JSONResponse:
    logger.error(
        "Échec API Vicia Home (%s) sur %s : %s", exc.endpoint, request.url.path, exc
    )
    return JSONResponse(status_code=status.HTTP_502_BAD_GATEWAY, content={"status": "error", "message": str(exc)})


@app.exception_handler(Exception)
async def handle_unexpected_error(request: Request, exc: Exception) -> JSONResponse:
    logger.error("Erreur non gérée sur %s : %s", request.url.path, exc, exc_info=exc)

    message = str(exc) if settings.app_debug else "Erreur interne du serveur."
    return JSONResponse(
        status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
        content={"status": "error", "message": message},
    )


@app.get("/health")
async def health_check() -> dict[str, str]:
    """Vérification légère utilisable par le reverse proxy ou un superviseur (systemd, Docker)."""
    return {"status": "ok"}
