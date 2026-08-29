# Discord Port Notes

Discord managed-role state must retain the Discord user ID and configured guild/role identity durably. Grant/removal must be awaited and observed/retried; destructive customer cleanup must not discard the identity mapping before managed-role removal is proven or durable retry state exists.
