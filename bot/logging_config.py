"""Initialisation du logger applicatif.

Un seul logger nommé "vicia_bot" est utilisé dans tout le projet, avec
rotation quotidienne des fichiers et conservation de 14 jours d'historique,
équivalent du RotatingFileHandler côté Monolog dans la version PHP.
"""

from __future__ import annotations

import logging
from logging.handlers import TimedRotatingFileHandler

from bot.config.settings import Settings

_LOGGER_NAME = "vicia_bot"
_configured = False


def configure_logging(settings: Settings) -> logging.Logger:
    global _configured

    logger = logging.getLogger(_LOGGER_NAME)

    if _configured:
        return logger

    settings.logs_path.mkdir(parents=True, exist_ok=True)

    formatter = logging.Formatter(
        fmt="%(asctime)s | %(levelname)-8s | %(name)s | %(message)s",
        datefmt="%Y-%m-%d %H:%M:%S",
    )

    file_handler = TimedRotatingFileHandler(
        settings.logs_path / "bot.log",
        when="midnight",
        backupCount=14,
        encoding="utf-8",
    )
    file_handler.setFormatter(formatter)

    logger.setLevel(settings.log_level.upper())
    logger.addHandler(file_handler)
    logger.propagate = False

    _configured = True

    return logger


def get_logger() -> logging.Logger:
    """Récupère le logger applicatif ; suppose que configure_logging a déjà été appelé."""
    return logging.getLogger(_LOGGER_NAME)
