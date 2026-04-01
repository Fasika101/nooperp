"""
Export Telegram 1:1 chats + recent messages for Liba ERP (`php artisan telegram:import`).

Prerequisites:
    1. App credentials from https://my.telegram.org (api_id, api_hash).
    2. pip install -r requirements.txt
    3. TELEGRAM_API_ID and TELEGRAM_API_HASH in the environment.
    4. python export.py — first run prompts for phone + login code; session is saved next to this script.

Filtering (see env vars below):
    • By default there is **no** name filter — exports up to TELEGRAM_MAX_CHATS dialogs (groups + private).
    • Set TELEGRAM_NAME_PREFIX (e.g. cu) to restrict to private chats whose name/username tokens match.
    • Optional TELEGRAM_CONTACT_FILTER: substring on display name / @username (AND with prefix when set).
    • TELEGRAM_NAME_PREFIX empty / none / false / 0 — disable the name-prefix rule.
    • Name-prefix matching uses first/last name, full display name, @username, and any word in the chat
      title; if the dialog list has empty names, the script fetches the full User once from Telegram.
    • Each chat includes phone when Telegram exposes it (plus sender_phone on messages when present).

Output: ../storage/app/telegram_export.json relative to this folder.
"""

from __future__ import annotations

import asyncio
import json
import os
import sys
from datetime import datetime, timezone
from pathlib import Path

from telethon import TelegramClient
from telethon.tl.functions.users import GetFullUserRequest
from telethon.tl.types import Channel, Chat, User
from telethon.utils import get_display_name, get_peer_id

# --- Configuration ----------------------------------------------------------
SCRIPT_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = SCRIPT_DIR.parent.parent
OUTPUT_FILE = PROJECT_ROOT / "storage" / "app" / "telegram_export.json"
SESSION_NAME = str(SCRIPT_DIR / "telegram_session")

API_ID = int(os.environ.get("TELEGRAM_API_ID", "0") or "0")
API_HASH = os.environ.get("TELEGRAM_API_HASH", "").strip()

# --- Dialog / message caps (after filters) ----------------------------------
# TELEGRAM_MAX_CHATS: max dialogs to export (default 10). 0 = no cap.
# TELEGRAM_EXPORT_LIMIT: max messages per dialog (default 10).
#
# --- Contact selection --------------------------------------------------------
# TELEGRAM_NAME_PREFIX: optional; when set, private chats only (see _dialog_passes_filters). Defaults off.
# TELEGRAM_CONTACT_FILTER: optional substring on display name + username (AND with prefix).

def _env_int(name: str, default: int) -> int:
    raw = os.environ.get(name, "").strip()
    if raw == "":
        return default
    try:
        return int(raw)
    except ValueError:
        return default


def _env_str_filter(name: str, default: str = "") -> str:
    """Read optional filter string; empty env, or none/false/0/all → treated as disabled."""
    raw = os.environ.get(name)
    if raw is None:
        return default
    s = raw.strip()
    if s.lower() in {"", "none", "no", "off", "false", "0", "all"}:
        return ""

    return s


MAX_CHATS = _env_int("TELEGRAM_MAX_CHATS", 10)
MESSAGES_PER_DIALOG = _env_int("TELEGRAM_EXPORT_LIMIT", 10)
CONTACT_FILTER_PHRASE = _env_str_filter("TELEGRAM_CONTACT_FILTER", "")
NAME_PREFIX = _env_str_filter("TELEGRAM_NAME_PREFIX", "")


def _entity_identity_haystack(entity) -> str:
    """Lowercase string of name/title + username for substring filtering."""
    parts: list[str] = []
    if isinstance(entity, User):
        parts.extend(
            [
                getattr(entity, "first_name", None) or "",
                getattr(entity, "last_name", None) or "",
                getattr(entity, "username", None) or "",
            ]
        )
    else:
        parts.extend(
            [
                getattr(entity, "title", None) or "",
                getattr(entity, "username", None) or "",
            ]
        )
    return " ".join(p for p in parts if p).lower()


def _dialog_matches_contact_filter(entity, phrase: str) -> bool:
    if not phrase:
        return True
    return phrase.lower() in _entity_identity_haystack(entity)


def _name_prefix_tokens(entity: User, dialog) -> set[str]:
    """Strings to match against TELEGRAM_NAME_PREFIX (lowercase tokens / full display)."""
    tokens: set[str] = set()
    for raw in (
        getattr(entity, "first_name", None) or "",
        getattr(entity, "last_name", None) or "",
        get_display_name(entity),
        getattr(dialog, "name", None) or "",
    ):
        s = (raw or "").strip().lower()
        if s:
            tokens.add(s)
            for part in s.split():
                if part:
                    tokens.add(part)
    username = getattr(entity, "username", None)
    if username:
        tokens.add(str(username).lower())

    return tokens


def _tokens_match_prefix(tokens: set[str], prefix: str) -> bool:
    p = prefix.lower()
    return any(t.startswith(p) for t in tokens if t)


def _user_matches_name_prefix_sync(entity: User, prefix: str, dialog) -> bool:
    if not prefix:
        return True
    return _tokens_match_prefix(_name_prefix_tokens(entity, dialog), prefix)


async def _dialog_passes_filters(client: TelegramClient, dialog) -> bool:
    """Apply substring + name-prefix filters. Refreshes User via API when dialog list omits names."""
    entity = dialog.entity
    if CONTACT_FILTER_PHRASE and not _dialog_matches_contact_filter(entity, CONTACT_FILTER_PHRASE):
        return False
    if not NAME_PREFIX:
        return True
    if not isinstance(entity, User):
        return False
    if _user_matches_name_prefix_sync(entity, NAME_PREFIX, dialog):
        return True
    try:
        fresh = await client.get_entity(entity)
        if isinstance(fresh, User) and _user_matches_name_prefix_sync(fresh, NAME_PREFIX, dialog):
            return True
    except Exception:
        pass

    return False


def _entity_type(entity) -> str:
    if isinstance(entity, User):
        return "user"
    if isinstance(entity, Chat):
        return "group"
    if isinstance(entity, Channel):
        return "channel" if getattr(entity, "broadcast", False) else "supergroup"
    return type(entity).__name__


def _entity_title(entity) -> str | None:
    if isinstance(entity, User):
        parts = [getattr(entity, "first_name", None) or "", getattr(entity, "last_name", None) or ""]
        t = " ".join(p for p in parts if p).strip()
        return t or None
    return getattr(entity, "title", None)


def _entity_username(entity) -> str | None:
    u = getattr(entity, "username", None)
    return str(u) if u else None


async def _resolve_user_phone(client: TelegramClient, user: User) -> str | None:
    """Telegram only exposes phone when privacy allows (e.g. contacts)."""
    raw = getattr(user, "phone", None)
    if raw:
        return str(raw)
    try:
        full = await client(GetFullUserRequest(user))
        u = full.user
        raw = getattr(u, "phone", None)
        return str(raw) if raw else None
    except Exception:
        return None


async def _serialize_message(client: TelegramClient, message) -> dict:
    sender_peer_id = None
    sender_name = None
    sender_phone = None
    try:
        sender = await message.get_sender()
        if sender is not None:
            sender_peer_id = str(get_peer_id(sender))
            if isinstance(sender, User):
                parts = [sender.first_name or "", sender.last_name or ""]
                sender_name = " ".join(p for p in parts if p).strip() or sender.username
                p = getattr(sender, "phone", None)
                if p:
                    sender_phone = str(p)
            else:
                sender_name = getattr(sender, "title", None) or getattr(sender, "username", None)
    except Exception:
        pass

    return {
        "id": int(message.id),
        "date": message.date.isoformat() if message.date else None,
        "out": bool(message.out),
        "sender_peer_id": sender_peer_id,
        "sender_name": sender_name,
        "sender_phone": sender_phone,
        "text": message.text or "",
        "raw": {},
    }


async def export_dialog(client: TelegramClient, dialog) -> dict:
    entity = dialog.entity
    peer_id = str(get_peer_id(entity))
    phone = None
    if isinstance(entity, User):
        phone = await _resolve_user_phone(client, entity)
    messages_out: list[dict] = []

    async for message in client.iter_messages(entity, limit=MESSAGES_PER_DIALOG):
        # Skip non-message types (service, etc.) without id
        if not getattr(message, "id", None):
            continue
        messages_out.append(await _serialize_message(client, message))

    # Oldest first for readability in CRM (optional)
    messages_out.sort(key=lambda m: m.get("date") or "")

    return {
        "peer_id": peer_id,
        "type": _entity_type(entity),
        "title": _entity_title(entity),
        "username": _entity_username(entity),
        "phone": phone,
        "meta": {
            "dialog_unread_count": getattr(dialog, "unread_count", None),
        },
        "messages": messages_out,
    }


async def main_async() -> None:
    global API_ID, API_HASH
    if API_ID <= 0 or not API_HASH:
        print(
            "Set TELEGRAM_API_ID and TELEGRAM_API_HASH environment variables "
            "(from https://my.telegram.org).",
            file=sys.stderr,
        )
        sys.exit(1)

    OUTPUT_FILE.parent.mkdir(parents=True, exist_ok=True)

    async with TelegramClient(SESSION_NAME, API_ID, API_HASH) as client:
        # limit=None → all dialogs (limit=0 in Telethon yields zero dialogs; never pass 0 here).
        all_dialogs = await client.get_dialogs(limit=None)
        print(f"Retrieved {len(all_dialogs)} dialog(s) from Telegram.", flush=True)

        dialogs: list = []
        for d in all_dialogs:
            if await _dialog_passes_filters(client, d):
                dialogs.append(d)
        print(
            f"Filters: substring={CONTACT_FILTER_PHRASE or '(none)'} | name_prefix={NAME_PREFIX or '(none)'} -> "
            f"{len(dialogs)} of {len(all_dialogs)} dialog(s).",
            flush=True,
        )

        if len(all_dialogs) == 0:
            print(
                "No dialogs from Telegram — session may be logged out or the account has no chats.",
                file=sys.stderr,
            )
        elif len(dialogs) == 0:
            print(
                "No dialogs matched filters. Export everyone: leave TELEGRAM_NAME_PREFIX and "
                "TELEGRAM_CONTACT_FILTER unset or set them to empty / none / 0. "
                "For names starting with 'cu': TELEGRAM_NAME_PREFIX=cu",
                file=sys.stderr,
            )

        if MAX_CHATS > 0:
            dialogs = dialogs[:MAX_CHATS]
        print(
            f"Exporting {len(dialogs)} chat(s), up to {MESSAGES_PER_DIALOG} message(s) each…",
            flush=True,
        )

        chats: list[dict] = []
        for i, d in enumerate(dialogs, start=1):
            title = getattr(d, "name", None) or str(get_peer_id(d.entity))
            print(f"  [{i}/{len(dialogs)}] {title}", flush=True)
            try:
                chats.append(await export_dialog(client, d))
            except Exception as e:
                chats.append(
                    {
                        "peer_id": str(get_peer_id(d.entity)),
                        "type": "error",
                        "title": None,
                        "username": None,
                        "phone": None,
                        "meta": {"export_error": str(e)},
                        "messages": [],
                    }
                )

        payload = {
            "exported_at": datetime.now(timezone.utc).isoformat(),
            "chats": chats,
        }

        with OUTPUT_FILE.open("w", encoding="utf-8") as f:
            json.dump(payload, f, ensure_ascii=False, indent=2)

    print(f"Wrote {len(chats)} chats to {OUTPUT_FILE}")


def main() -> None:
    asyncio.run(main_async())


if __name__ == "__main__":
    main()
