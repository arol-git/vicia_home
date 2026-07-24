"""Exception levée lorsqu'un appel à l'API REST Vicia Home échoue :
erreur réseau, réponse non-2xx, ou corps de réponse invalide.
"""

from __future__ import annotations


class ApiException(Exception):
    def __init__(
        self,
        message: str,
        status_code: int | None = None,
        endpoint: str | None = None,
    ) -> None:
        super().__init__(message)
        self.status_code = status_code
        self.endpoint = endpoint
