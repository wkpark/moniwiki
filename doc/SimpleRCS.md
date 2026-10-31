# SimpleRCS text history

The SimpleRCS PHP backend is licensed under Apache-2.0, matching the original
Python SimpleRCS project. See `lib/SimpleRCS.LICENSE` for the license text.

Set `$version_class='SimpleRCS';` in `config.php` to use a separate SimpleRCS
history for UTF-8 text pages. Histories are stored as `data/text/RCS/Page,srcs`
(the actual path follows the configured text directory and encoded page key).
The default version backend remains unchanged.

Full v2 hash verification is disabled by default. Set
`$simplercs_verify_hashes=1` in `config.php` to verify hashes while reading,
restoring, and saving history. Structural format and hash-chain link checks
remain enabled in either mode; disabling verification does not authenticate
history contents.

This backend reads and extends SimpleRCS v1 text histories and v2 text histories
with the `sha256` algorithm and `utf-8` encoding. It supports text snapshots,
revision history, comparison, recall, restoration, and rename. It does not
support binary content, signatures, other encodings or hash algorithms, or the
RCS import/export and merge plugins. Unsupported histories are rejected rather
than rewritten.

GNU RCS `,v` and SimpleRCS `,srcs` are different formats. This backend does not
convert existing `,v` files. If a page has a `,v` history but no `,srcs`
history, editing that page is refused to protect its existing history. Back up
the wiki and use the existing version backend until a migration is available.

Writes use a temporary file and replace the archive after the new contents are
flushed. This rewrites the history and is not an append-only implementation.
Page data and history are separate files and are not committed as one
transaction. Concurrent edits and interruption during page saving require
additional coordination.
