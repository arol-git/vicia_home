"""Exception levée par les middlewares de sécurité (whitelist, JWT,
signature webhook) lorsqu'une requête ne peut pas être authentifiée
ou autorisée.
"""

from __future__ import annotations


class UnauthorizedException(Exception):
    @classmethod
    def chat_not_whitelisted(cls, chat_id: int | str) -> UnauthorizedException:
        return cls(f"Le chat_id {chat_id} n'est pas autorisé à utiliser ce bot.")

    @classmethod
    def chat_blacklisted(cls, chat_id: int | str) -> UnauthorizedException:
        return cls(f"Le chat_id {chat_id} est bloqué.")

    @classmethod
    def invalid_or_expired_token(cls) -> UnauthorizedException:
        return cls("Le jeton d'authentification est invalide ou a expiré.")

    @classmethod
    def invalid_webhook_signature(cls) -> UnauthorizedException:
        return cls("La signature de la requête webhook est invalide ou a expiré.")
