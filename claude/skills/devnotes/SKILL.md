---
name: devnotes
description: How to write a good devnote before calling the devnotes MCP tools (add-note, update-note). Use whenever you are about to add or edit a note in the devnotes pot, when the user says "add a devnote", "make a note of that", or "worth a devnote?", and when deciding whether something discovered mid-session is worth capturing for the team. Also covers what must never go into a note (people's names, credentials).
---

# Writing devnotes

Devnotes is a small dev team's shared pot of gotchas and lessons. The reader of
any note is a stranger: a teammate or an agent session months from now, with no
memory of today. Every rule below follows from that.

## Is it note-worthy?

Worth a note:

- It surprised you, or cost real time to figure out.
- It is specific to a version, an environment, or one of our servers - the kind
  of thing official docs will never cover.
- The fix was non-obvious and you can imagine someone hitting the same wall.

Not worth a note:

- Anything the framework or package docs answer directly. A note that
  paraphrases documentation is noise in every future search.
- Routine how-tos and session-specific detail ("restarted the queue worker
  today") that will not help the next reader.

When unsure, lean towards capturing it when it seems to be a 'formal' or 
'employer' project - notes are cheap and soft-deleted, and a mediocre note 
beats a lost lesson. But say what you are about to write before calling add-note, 
so the developer can veto or sharpen it.

**Never** write a note without asking the user first.  They have the context and overall 
view of their own team and the broader organisationn that you don't.  But make it friendly, 
collegial - "That sqlite vs mysql thing really caused us a lot of pain, should we make a
devnote about it?"



## Write for the stranger

- Title = the symptom, in the words someone would search for.
- Body: symptom, cause, fix - then breadcrumbs: exact error text, package
  versions, file paths, the command that proved the fix.
- Markdown, GitHub-flavoured. Link related notes inline as #code (for example
  "see #abq4x") - add-note returns the code, search-notes finds others.
- Search before adding. If a note already covers the topic, update-note it
  rather than minting a near-duplicate; two half-notes are worse than one.

## What never goes in

- **People's names - hard block, no exceptions.** No colleagues, managers,
  academics, students; not in the title, the body, or an example. Notes are
  durable, visible to the whole team, and surfaced to every connecting agent.
  Who-did-what lives in the app's activity log, not in prose. If the developer
  is venting about a person, that is a conversation, not a note - do not
  capture it.
- **Credentials.** No passwords, API keys, tokens, or private keys, even
  expired ones.

Hostnames, IPs, and server names are welcome - this is an internal IT team
tool, and knowing *which* box the gotcha bit is often the whole point of the
note. Do not mask or genericise them.
