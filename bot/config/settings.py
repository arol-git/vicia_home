"""Configuration applicative.

Toutes les valeurs proviennent des variables d'environnement (fichier
.env chargé automatiquement par pydantic-settings). Ce module ne
contient aucune valeur sensible : uniquement des noms de variables et
des valeurs par défaut sûres.
"""

from __future__ import annotations

from functools import lru_cache
from pathlib import Path

from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict

BASE_PATH = Path(__file__).resolve().parents[2]


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=str(BASE_PATH / ".env"),
        env_file_encoding="utf-8",
        extra="ignore",
    )

    # --- Application ---
    app_env: str = Field(default="production", alias="APP_ENV")
    app_debug: bool = Field(default=False, alias="APP_DEBUG")
    app_timezone: str = Field(default="UTC", alias="APP_TIMEZONE")
    app_url: str = Field(default="", alias="APP_URL")

    # --- Telegram ---
    telegram_bot_token: str = Field(default="", alias="TELEGRAM_BOT_TOKEN")
    telegram_webhook_secret: str = Field(default="", alias="TELEGRAM_WEBHOOK_SECRET")
    telegram_admin_chat_ids: str = Field(default="", alias="TELEGRAM_ADMIN_CHAT_IDS")

    # --- API REST Vicia Home ---
    vicia_api_base_url: str = Field(default="", alias="VICIA_API_BASE_URL")
    vicia_api_timeout: float = Field(default=8.0, alias="VICIA_API_TIMEOUT")

    # --- JWT ---
    jwt_issuer: str = Field(default="vicia-telegram-bot", alias="JWT_ISSUER")
    jwt_access_ttl: int = Field(default=900, alias="JWT_ACCESS_TTL")
    jwt_refresh_ttl: int = Field(default=604_800, alias="JWT_REFRESH_TTL")

    # --- Webhook entrant Vicia (notifications) ---
    vicia_webhook_secret: str = Field(default="", alias="VICIA_WEBHOOK_SECRET")
    vicia_webhook_max_skew_seconds: int = Field(default=60, alias="VICIA_WEBHOOK_MAX_SKEW_SECONDS")

    # --- Base de données propre au bot ---
    db_host: str = Field(default="127.0.0.1", alias="DB_HOST")
    db_port: int = Field(default=3306, alias="DB_PORT")
    db_database: str = Field(default="vicia_bot", alias="DB_DATABASE")
    db_username: str = Field(default="", alias="DB_USERNAME")
    db_password: str = Field(default="", alias="DB_PASSWORD")

    # --- Rate limiting ---
    rate_limit_max_requests: int = Field(default=20, alias="RATE_LIMIT_MAX_REQUESTS")
    rate_limit_window_seconds: int = Field(default=60, alias="RATE_LIMIT_WINDOW_SECONDS")

    # --- Logs ---
    log_level: str = Field(default="INFO", alias="LOG_LEVEL")

    @property
    def admin_chat_ids(self) -> list[str]:
        """Liste des chat_id autorisés à exécuter des commandes d'administration."""
        return [chat_id.strip() for chat_id in self.telegram_admin_chat_ids.split(",") if chat_id.strip()]

    @property
    def logs_path(self) -> Path:
        return BASE_PATH / "logs"

    @property
    def storage_path(self) -> Path:
        return BASE_PATH / "storage"

    @property
    def uploads_path(self) -> Path:
        return BASE_PATH / "uploads"


@lru_cache
def get_settings() -> Settings:
    """Instance unique et mise en cache des réglages de l'application."""
    return Settings()
