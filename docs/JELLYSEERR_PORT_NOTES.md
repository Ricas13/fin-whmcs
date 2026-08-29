# Jellyseerr Port Notes

Jellyseerr account/permission lifecycle must be ported from the actual `fin-fusion` adapter or verified API documentation. Do not invent endpoint semantics merely to satisfy feature parity. The shared desired-state diff and HTTP/recovery contracts are already in place so the concrete adapter can remain thin and testable.
