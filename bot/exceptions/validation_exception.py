"""Exception levée lorsqu'un paramètre libre (callback_data, argument de
commande, corps de webhook) ne respecte pas le format attendu.
"""

from __future__ import annotations


class ValidationException(Exception):
    def __init__(self, errors: dict[str, str], message: str = "Validation échouée.") -> None:
        super().__init__(message)
        self.errors = errors
